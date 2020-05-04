<?php
/*------------------------------------*\
    ::Aqua Hooks - Wordpress Site
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.1
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

log_status('wp_site included', 'TITLE');

// if this is a wordpress site
if('wordpress' == $config['project_type']){
    log_status('is type wordpress', 'NOTE');
    // grab all the helpers needed
    include_once 'lib/functions/db_helpers.php';
    include_once 'lib/functions/wp_helpers.php';
    // get the config file (2nd time: ensures it's grabbed even if it moves/changes name)
    $config['config_path'] = wp_find_config($config['dir_project']);
    // get the wordpress database credentials (2nd time: ensures it's grabbed even if they've changed)
    $wp_db_creds = wp_db_creds($config['dir_project'], $config['server']);
    // update the .htaccess file for new path
    wp_htaccess_update($config['dir_project']);
    // if there are wp database credentials
    if($wp_db_creds){
        log_status('database credentials exist', 'SUCCESS');
        // stand up the database
        wp_db_standup($config['dir_project'], $wp_db_creds, $config['server'], $config['client'], $config['project']);
        // update the wp-config.php with this server's database values
        wp_update_config($config['dir_project'], $config['server'], $wp_db_creds);
    } else {
        throw new Exception("No database credentials found for WordPress site");
    }
}
