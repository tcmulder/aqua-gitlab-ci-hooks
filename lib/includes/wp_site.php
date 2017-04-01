<?php
/*------------------------------------*\
    ::Aqua Hooks - Wordpress Site
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

log_status("\n\n:: wp_site included");

// if this is a wordpress site
if('wordpress' == $proj_type){
    log_status('is type wordpress');

    // grab all the database helper functions
    include_once 'lib/functions/db_helpers.php';
    // get the wordpress helpers
    include_once 'lib/functions/wp_helpers.php';

    // get the wordpress database creds and stand it up
    $wp_db_creds = wp_db_creds($dir_proj, $server);
    if($wp_db_creds){
        log_status('database credentials exist');
        wp_db_standup($dir_proj, $wp_db_creds, $server, $client, $proj);
    }

    // update the .htaccess file for new path
    wp_htaccess_update($dir_proj);

}
