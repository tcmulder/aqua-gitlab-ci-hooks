<?php
/*------------------------------------*\
    ::Aqua Hooks - Configuration File
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// customize this file to match your unique server setup
$config = array(

    /*------------------------------------*\
        ::Project Configuration
    \*------------------------------------*/
    // client name (e.g. for parent directory)
    'client'            => 'CLIENTNAME', // populate dynamically
    // project name (e.g. for sub directory)
    'project'           => 'PROJECTNAME', // populate dynamically
    // project type (assumed to be static unless set to 'wordpress')
    'project_type'      => 'wordpress', // recommended to populate dynamically

    /*------------------------------------*\
        ::Paths Configuration
    \*------------------------------------*/
    // base url (e.g. www.example.com becomes example.com)
    'base_url'          => 'example.com',
    // root directory shared by all subdomains (often /var/www/)
    'root_dir'          => '/var/www/',
    // subdomain directory without the subdomain prefix
    'sub_dir'           => 'example.com/public_html/', // IMPORTANT: not subdomain.example.com/public_html/

    /*------------------------------------*\
        ::Database Configuration
    \*------------------------------------*/
    // mysql user with root privileges
    'mysql_user'        => 'root',
    // password for that mysql user
    'mysql_pass'        => 'root',
    // host for mysql
    'mysql_host'        => 'localhost',

    /*------------------------------------*\
        ::Git Configuration
    \*------------------------------------*/
    // branch requested to be pulled in (used also to determine subdomain, database name, etc.)
    'branch'            => 'dev',// populate dynamically
    // cloneable url path
    'repo'              => 'git@gitlab.com:example.git',// populate dynamically
    // the git sha that will be active after the push (used to ensure before/after sha's don't match)
    'sha_after'         => 'abcdefgexample',// populate dynamically
    // email for git to commit under
    'user_email'        => 'example@example.com',
    // username for git to commit under
    'user_name'         => 'example@example.com',
    // a specific branch to pull (usually left empty unless doing something unusual)
    'pull'              => '',

    /*------------------------------------*\
        ::WordPress Configuration
    \*------------------------------------*/
    // file name of the config (will search root and subdirectories to find it)
    'config_file'       => 'wp-config.php',

    /*------------------------------------*\
        ::Debug Configuration
    \*------------------------------------*/
    // 'debug' for line numbers or 'basic' for basic (comment out to turn off)
    'log'               => 'debug',
    // timezone for debug logs
    'timezone'          => 'America/Denver',
);