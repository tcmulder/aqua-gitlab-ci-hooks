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

log_status('wp_site included', 'TITLE');

// if this is a wordpress site
if('wordpress' == $proj_type){
    log_status('is type wordpress', 'NOTE');

    // grab all the helpers needed
    include_once 'lib/functions/db_helpers.php';
    include_once 'lib/functions/wp_helpers.php';

    // update the .htaccess file for new path
    wp_htaccess_update($dir_proj);
    // update the wp-config.php with new database values
    wp_update_config($dir_proj, $server);
    // stand up the wordpress database from mysqldump
    if($wp_db_creds){
        log_status('database credentials exist', 'SUCCESS');
        wp_db_standup($dir_proj, $wp_db_creds, $server, $client, $proj);
    }
}
