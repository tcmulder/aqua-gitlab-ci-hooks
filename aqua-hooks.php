#!/usr/bin/php
<?php
/*------------------------------------*\
    ::Aqua Hooks - Controller
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

/*------------------------------------*\
    ::Initial Setup
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

// grab the $config configuration array
require_once 'config.php';

/*------------------------------------*\
    ::Set Up Error Logging
\*------------------------------------*/
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');
error_reporting(E_ALL & ~(E_WARNING | E_NOTICE | E_DEPRECATED | E_STRICT));
ignore_user_abort(true);
date_default_timezone_set($config['timezone']);
$dir_root = dirname(__FILE__) . '/';
try {


    /*------------------------------------*\
        ::Set Up Status Logging
    \*------------------------------------*/

    // log status by outputting text into the log
    function log_status($status){
        global $config;
        if('true' == $config['log'] || 'debug' == $config['log']){
            $file = $dir_root.'debug.log';
            // extra debug info
            $extra_debug = '';
            if('debug' == $config['log']){
                $bt = debug_backtrace();
                $caller = array_shift($bt);
                $debug_file = array_pop(explode('/', $caller['file']));
                $debug_line = $caller['line'];
                $extra_debug = str_pad(" [$debug_file:$debug_line] ",22) ;
            }
            // write the output to the log
            file_put_contents($file, $extra_debug."$status\n", FILE_APPEND | LOCK_EX);
            // truncate the log if it gets too large
            if($lines = count(file($file)) >= 100000){
                $truncated = shell_exec("tail -n 1000 $file");
                file_put_contents($file, $truncated, LOCK_EX);
            }
        } else {
            return false;
        }
    }
    // log status by executing and then outputting text into the log
    function log_exec($exec){
        global $config;
        if('true' == $config['log'] || 'debug' == $config['log']){
            global $dir_root;
            $file = $dir_root.'debug.log';
            // extra debug info
            $extra_debug = '';
            if('debug' == $config['log']){
                $bt = debug_backtrace();
                $caller = array_shift($bt);
                $debug_file = array_pop(explode('/', $caller['file']));
                $debug_line = $caller['line'];
                $extra_debug = str_pad(" [$debug_file:$debug_line] ",25) ;
            }
            // report what was called
            file_put_contents($file, $extra_debug."called on command line: \n\t$exec\n", FILE_APPEND | LOCK_EX);
            // execute and capture response
            exec("$exec 2>&1", $output);
            $exec_output = str_replace("\n", "\n\t", print_r($output,1));
            // write the output to the log
            file_put_contents($file, $extra_debug."prevous command output: \n\t$exec_output\n", FILE_APPEND | LOCK_EX);
            // truncate the log if it gets too large
            if($lines = count(file($file)) >= 100000){
                $truncated = shell_exec("tail -n 1000 $file");
                file_put_contents($file, $truncated, LOCK_EX);
            }
        } else {
            exec("$exec");
        }
    }

    log_status("\n\n\n\naqua-hooks start :::::::::::::::::::::::: [ ".date("Y-m-d H:i:s")." ]");

    /*------------------------------------*\
        ::Initialize Data
    \*------------------------------------*/
    $parsed_input = str_replace('\"', '"', $argv[1]);
    $gitlab = json_decode($parsed_input); //data from gitlab
    log_status('raw json: '.$parsed_input);
    log_status('gitlab data: '.($gitlab ? 'true' : 'false'));
    log_status('gitlab json: '.print_r($gitlab,1));
    // no need to continue if no data received or it's from an unauthorized source
    if($gitlab){

        // grab all the get data
        $client = (isset($config['client']) ? $config['client'] : false);
        if($client){
            log_status('client: '.$client);
        } else {
            throw new Exception('$config[\'client\'] does not exist');
        }
        $proj = (isset($config['project']) ? $config['project'] : false);
        if($proj){
            log_status('project: '.$proj);
        } else {
            throw new Exception('$config[\'project\'] does not exist');
        }
        $proj_type = (isset($gitlab->type) ? $gitlab->type : false);
        if($proj_type){
            log_status('project type: '.$proj_type);
        } else {
            log_status('no project type defined');
        }
        $pull_specific = (isset($config['pull']) ? $config['pull'] : false);
        log_status('pull specific commit: '.$pull_specific);

        // set up necessary variables and report their values
        $branch = null;
        if(!$pull_specific){
            $branch = $gitlab->ref;
        } else {
            $branch = $pull_specific;
        }
        log_status('branch: '.$branch);

        $branch_base_parts = explode('_', $branch);
        $server = $branch_base_parts[0];
        log_status('server: '.$server);

        // $subdomain = explode('.', $_SERVER['HTTP_HOST'])[0];
        // $server_version = substr($subdomain, -1, 1);
        // log_status('directory version: '.$server_version);

        $dir_base = $config['root_dir'] . $server . '.'. $config['domain'] . $config['sub_dir'];
        log_status('directory base: '.$dir_base);

        // exit if the server (based on branch prefix) doesn't exist
        if(!file_exists($dir_base)){
            throw new Exception("Server [$dir_base] does not exist");
        }

        // store directory locations and report where they are
        $dir_client = $dir_base . $client . '/';
        log_status('client directory: '.$dir_client);
        $dir_proj = $dir_client . $proj . '/';
        log_status('project directory: '.$dir_proj);

        // identify where the repo can be cloned from
        $repo = $gitlab->repository->url;
        log_status('repo: '.$repo);

        // check the commit sha
        $sha_after = $gitlab->after;
        $git = "git --git-dir=$dir_proj.git --work-tree=$dir_proj"; // run git commands in working directory
        //compare the current and after sha values
        $sha_cur = substr(shell_exec("$git rev-parse --verify HEAD"), 0, 40);
        log_status("the current sha is \"$sha_cur\"");
        log_status("the after sha is \"$sha_after\"");
        $sha_identical = ($sha_cur == $sha_after ? true : false);
        log_status('the current sha and after sha are ' . ($sha_cur == $sha_after ? 'equal' : 'not equal'));
        //check for empty after sha value
        $sha_zero = ($sha_after == '0000000000000000000000000000000000000000' ? true : false);
        log_status('the after sha ' . ($sha_after == '0000000000000000000000000000000000000000' ? 'is empty' : 'is not empty'));

        // if pull of no specific branch was requested
        if(!$pull_specific){
            log_status('no specific commit to pull');
            // if the current and after commit are the same or the after sha is empty
            if($sha_cur == $sha_after) {
                throw new Exception('Current and requested commits are identical');
            } elseif($sha_after == '0000000000000000000000000000000000000000'){
                throw new Exception('The new commit is empty');
            } else {
                log_status('requested commit is new');
            }
        }

        // for wordpress sites
        if('wordpress' == $proj_type){
            log_status('is type wordpress');
            // get all the database helper functions
            include_once 'lib/functions/db_helpers.php';
            // get the wordpress database credentials
            include_once 'lib/functions/wp_helpers.php';
            $wp_db_creds = wp_db_creds($dir_proj, $server);
        }


        /*------------------------------------*\
            ::Run All the Commands
        \*------------------------------------*/

        // try to initialize the repo if it needs to
        include_once 'lib/includes/init_repo.php';
        // update the branch
        include_once 'lib/includes/update_repo.php';
        // try to handle wp sites
        include_once 'lib/includes/wp_site.php';

        // run garbage collection to keep the repository size manageable
        $git = "git --git-dir=$dir_proj.git --work-tree=$dir_proj";
        log_status('git garbage collection script running');
        log_exec("$git gc");
        log_status('git garbage collection requested');

        log_status("\naqua-hooks end :::::::::::::::::::::::::: [ ".date("Y-m-d H:i:s")." ]\n");
    // if data isn't right
    } else {
        // if no data was received from gitlab
        throw new Exception('No data received from gitlab');
    }
} catch (Exception $e) {
    //output the log
    error_log(sprintf("%s >> %s", date('Y-m-d H:i:s'), "\n".$e));
    log_status("\naqua-hooks end :::::::::::::::::::::::::: [ ".date("Y-m-d H:i:s")." ]\n");
}
