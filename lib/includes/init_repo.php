<?php
/*------------------------------------*\
    ::Aqua Hooks - Initialize Repository
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

log_status('init_repo included', 'TITLE');
// ensure we're working from a base directory
if(file_exists($dir_base)){
    log_status('base directory is '.$dir_base, 'NOTE');
    // if the client directory doesn't exist
    if(!file_exists($dir_client)){
        log_status('client directory does not exists', 'NOTE');
        // create the client directory
        log_status('create client directory '.$dir_client, 'NOTE');
        mkdir($dir_client);
        log_status('create archive directory', 'NOTE');
        mkdir($dir_client.'archive');
        log_status('copy client_template/index.php', 'NOTE');
        copy($dir_base.'client_template/index.php', $dir_client.'index.php');
        // report request to create client directory
        log_status('new client directory creation requested', 'NOTE');
    } else {
        // report that this if statement was skipped
        log_status('did not create client directory', 'NOTE');
    }
    // if the project directory doesn't exist
    if(!file_exists($dir_proj)){
        log_status('project directory does not exists', 'NOTE');
        // create the proj directory
        log_status('create project directory '.$dir_proj, 'NOTE');
        mkdir($dir_proj);
        log_status('new project directory creation requested', 'NOTE');
    } else {
        // report that this if statement was skipped
        log_status('did not create project directory', 'NOTE');
    }
    // if the project isn't a git repo
    if(!file_exists($dir_proj . '.git')){
        log_status('not a git repository but project directory is present', 'NOTE');
        // change into the project directory
        chdir($dir_proj);
        // set up git
        log_exec('git init');
        // establish credentials
        log_exec('git config user.email "YOUR@EMAIL.com"');
        log_exec('git config user.name "YOUR_USERNAME"');
        // set up remote
        log_exec("git remote add gitlab $repo");
        // run init commit in order to rename the branch from master
        log_exec('echo "This file allows aqua-hooks to make an initial commit. Read more here: https://github.com/tcmulder/aqua-gitlab-ci-hooks" >> readme.md');
        log_exec('git add readme.md');
        log_exec('git commit -m "Initial commit"');
        log_exec('git branch -m gitlab_preview');
        // change back to the root directory
        chdir($dir_root);
        // report true to signify that initialization took place
        log_status('git init ran', 'NOTE');
        return true;
    } else {
        // report that this if statement was skipped
        log_status('git init not run', 'NOTE');
    }
// if the base directory doesn't exist (also true for non-supported branches)
} else {
    throw new Exception("Branch [$branch] does not match server $dir_base");
}