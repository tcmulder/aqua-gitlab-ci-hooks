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

try {


    /*------------------------------------*\
        ::Set Up Status Logging
    \*------------------------------------*/

    include_once 'lib/functions/log_helpers.php';
    log_status("aqua-hooks start", 'SEPARATE');

    /*------------------------------------*\
        ::Initialize Data
    \*------------------------------------*/
    $parsed_input = str_replace('\"', '"', $argv[1]);
    $gitlab = json_decode($parsed_input); //data from gitlab
    log_status('raw json: '.$parsed_input, 'NOTE');
    log_status('gitlab data: '.($gitlab ? 'true' : 'false'), 'NOTE');
    log_status('gitlab json: '.print_r($gitlab,1), 'NOTE');
    // no need to continue if no data received
    if($gitlab){

        // set up the configuration values
        $config['client'] = $gitlab->client;
        if($config['client']){
            log_status('client: '.$config['client'], 'NOTE');
        } else {
            throw new Exception('$config[\'client\'] is not set');
        }
        $config['project'] = $gitlab->project;
        if($config['project']){
            log_status('project: '.$config['project'], 'NOTE');
        } else {
            throw new Exception('$config[\'project\'] is not set');
        }
        $config['project_type'] = $gitlab->type;
        if($config['project_type']){
            log_status('project type: '.$config['project_type'], 'NOTE');
        } else {
            log_status('no project type defined: defaulting to static', 'NOTE');
        }
        $config['pull'] = $gitlab->pull;
        log_status('pull specific commit: '.($config['pull'] ? $config['pull'] : 'none specified'), 'NOTE');

        // set up necessary variables and report their values
        if(!$config['pull']){
            $config['branch'] = $gitlab->ref;
        } else {
            $config['branch'] = $config['pull'];
        }
        if($config['branch']){
            log_status('branch: '.$config['branch'], 'NOTE');
        } else {
            throw new Exception('$config[\'branch\'] is not set');
        }

        $branch_base_parts = explode('_', $config['branch']);
        $config['server'] = $branch_base_parts[0];
        log_status('server: '.$config['server'], 'NOTE');

        $config['dir_hooks'] = dirname(__FILE__) . '/';

        $config['dir_base'] = $config['root_dir'].$config['server'].'.'.$config['domain'].$config['sub_dir'];
        log_status('directory base: '.$config['dir_base'], 'NOTE');

        // exit if the server (based on branch prefix) doesn't exist
        if(!file_exists($config['dir_base'])){
            throw new Exception('Server ['.$config['dir_base'].'] does not exist');
        }

        // store directory locations and report where they are
        $config['dir_client'] = $config['dir_base'].$config['client'].'/';
        log_status('client directory: '.$config['dir_client'], 'NOTE');
        $config['dir_project'] = $config['dir_client'].$config['project'].'/';
        log_status('project directory: '.$config['dir_project'], 'NOTE');

        // identify where the repo can be cloned from
        $config['repo'] = $gitlab->repository->url;
        log_status('repo: '.$config['repo'], 'NOTE');

        // check the commit sha
        $sha_after = $gitlab->after;
        $config['git'] = 'git --git-dir='.$config['dir_project'].'.git --work-tree='.$config['dir_project']; // run git commands in working directory
        //compare the current and after sha values
        $sha_cur = substr(shell_exec($config['git'].' rev-parse --verify HEAD'), 0, 40);
        log_status("the current sha is \"$sha_cur\"", 'NOTE');
        log_status("the after sha is \"$sha_after\"", 'NOTE');
        $sha_identical = ($sha_cur == $sha_after ? true : false);
        log_status('the current sha and after sha are ' . ($sha_cur == $sha_after ? 'equal' : 'not equal'), 'NOTE');
        //check for empty after sha value
        $sha_zero = ($sha_after == '0000000000000000000000000000000000000000' ? true : false);
        log_status('the after sha ' . ($sha_after == '0000000000000000000000000000000000000000' ? 'is empty' : 'is not empty'), 'NOTE');

        // if pull of no specific branch was requested
        if(!$config['pull']){
            log_status('no specific commit to pull', 'NOTE');
            // if the current and after commit are the same or the after sha is empty
            if($sha_cur == $sha_after) {
                throw new Exception('Current and requested commits are identical');
            } elseif($sha_after == '0000000000000000000000000000000000000000'){
                throw new Exception('The new commit is empty');
            } else {
                log_status('requested commit is new', 'NOTE');
            }
        }

        // for wordpress sites set up db creds (used initially for backups)
        if('wordpress' == $config['project_type']){
            log_status('is type wordpress', 'NOTE');
            // get all the helper functions needed
            include_once 'lib/functions/db_helpers.php';
            include_once 'lib/functions/wp_helpers.php';
            // get the wordpress wp-config.php file path
            $config['config_path'] = wp_find_config();
            // get the wordpress database credentials
            $config['wp_db_creds'] = wp_db_creds();
        }


        /*------------------------------------*\
            ::Run All the Commands
        \*------------------------------------*/

        // try to initialize the repo if it needs to
        include_once 'lib/includes/init_repo.php';
        // update the branch
        include_once 'lib/includes/update_repo.php';
        // try to handle wp sites if this is one
        include_once 'lib/includes/wp_site.php';

        // run garbage collection to keep the repository size manageable
        log_status('git garbage collection script running', 'NOTE');
        log_exec($config['git'].' gc');
        log_status('git garbage collection requested', 'NOTE');

        log_status("aqua-hooks end", 'SEPARATE');
    // if data isn't right
    } else {
        // if no data was received from gitlab
        throw new Exception('No data received from gitlab');
    }
} catch (Exception $e) {
    //output the log
    error_log(sprintf(colorize("%s >> %s", 'FAILURE'), date('Y-m-d H:i:s'), "\n".$e));
    log_status("aqua-hooks end", 'SEPARATE');
}
