<?php
/*------------------------------------*\
    ::Aqua Hooks - Configuration File
    ----------------------------------
    author:     Tomas Mulder <tomas@zenman.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

$config = array(
    'gitlab_ip'         => '127.0.0.1', // ip address of your gitlab instance
    'timezone'          => 'America/Denver', // timezone for debug logs
    'client'            => 'CLIENTNAME', // client slug (e.g. for parent directory)
    'project'           => 'PROJECTNAME', // project slug (e.g. for sub directory)
    'type'              => 'wordpress', // use 'wp' for wordpress projects or leave blank for static sites
    'root_dir'          => '/var/www/', // root directory shared by all subdomains (often /var/www/)
    'subdomain_dir'     => 'thinkaquamarine.com/public_html/', // IMPORTANT: don't include the subdomain itself (e.g. dev.example.com/public_html becomes example.com/public_html)

    'pull'              => '', // specific branch to pull (ignores what was pushed)
    'log'               => 'debug', // 'debug' for line numbers or 'true' for basic (comment out to turn off)
);
