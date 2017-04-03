<?php
/*------------------------------------*\
    ::Aqua Hooks - Database Functions
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

// create a database
function db_create(){
    global $config;
    log_status('db_create called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($config['wp_db_creds'],1));
    // connect to mysql
    $link = mysqli_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    if($link) {
        log_status('connected to mysql as root', 'NOTE');
        // create the database
        $db = mysqli_select_db($config['wp_db_creds']['name'], $link);
        // if the database doesn't exist already
        if (!$db) {
            log_status('database '.$config['wp_db_creds']['name'].' does not exist', 'NOTE');
            mysqli_query('CREATE DATABASE IF NOT EXISTS '.$config['wp_db_creds']['name'], $link);
            log_status('ran create database '.$config['wp_db_creds']['name'], 'NOTE');
        } else {
            log_status('database already exists', 'SUCCESS');
            mysqli_close($link);
            return false;
        }
    }
    mysqli_close($link);
}

// export (mysqldump) a database
function db_export(){
    global $config;
    log_status('db_export called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
    log_status('database directory is '.$db_dir, 'NOTE');
    // if the /.db/ directory doesn't exist
    if(!file_exists($db_dir)){
        log_status('create /.db/ directory requested', 'NOTE');
        // create the directory
        mkdir($db_dir);
    } else {
        log_status('/.db/ directory exists', 'NOTE');
    }
    // dump the database
    log_status('export /.db/db.sql requested', 'NOTE');
    log_exec('/usr/bin/mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$config['wp_db_creds']['name'].' > '.$db_dir .'db.sql');
}

// import a database
function db_import(){
    global $config;
    $db_dir = $config['dir_project'].'.db/';
    log_status('db_import called', 'TITLE');
    log_status('db_import: database credentials received', 'SUCCESS');
    log_status('the credentials are '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
    log_status('db_import: database directory is '.$db_dir, 'NOTE');
    // variable to store sql dump
    $db_dump = $db_dir.'db.sql';
    // if there is a /.db/db.sql file
    if(file_exists($db_dump)){
        log_status('db_import: file exists '.$db_dump, 'SUCCESS');
        // drop the database's tables
        log_status('db_import: drop databases tables', 'NOTE');
        exec('mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' --no-data '.$config['wp_db_creds']['name'].' | grep ^DROP | mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$config['wp_db_creds']['name']);
        // import the /.db/db.sql file
        log_status('db_import: import file '.$db_dump, 'NOTE');
        exec('mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$config['wp_db_creds']['name'].' < '.$db_dump);
        return true;
    // if there is no /.db/db.sql
    } else {
        // report import as failed
        log_status('db_import: file does not exist '.$db_dump, 'WARNING');
        return false;
    }
}

// find and replace in a database
function db_far(){
    global $config;
    log_status('db_far called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
    log_status('server is '.$config['server'], 'NOTE');
    log_status('client is '.$config['client'], 'NOTE');
    log_status('project is '.$config['project'], 'NOTE');
    // if we have enough info
    if(count($config['wp_db_creds']) == 7 && $config['server'] && $config['client'] && $config['project']){
        log_status('call made to run find and replace', 'NOTE');
        // create find and replace command
        $far = 'php lib/functions/far.php ';
        $far .= '\''.$config['wp_db_creds']['name'].'\' ';
        $far .= '\''.$config['mysql_user'].'\' ';
        $far .= '\''.$config['mysql_pass'].'\' ';
        $far .= '\''.$config['mysql_host'].'\' ';
        $far .= '\''.$config['wp_db_creds']['char'].'\' ';
        $far .= '\''.preg_replace("(^https?:)", "", $config['wp_db_creds']['home_url']).'\' '; // protocol-relative url
        $far .= '\'//'.$config['server'].'.'.$config['base_url'].'/'.$config['client'].'/'.$config['project'].'\'';

        //execute find and replace
        $output = shell_exec($far);
        log_status('ran with output: ', 'NOTE');
        log_status($output);
    // if we do not have all the info
    } else {
        if(count($config['wp_db_creds']) != 7){
            log_status('7 perimeters not received', 'WARNING');
        }
        if(!$config['server']){
            log_status('server not set', 'WARNING');
        }
        if(!$config['client']){
            log_status('client not set', 'WARNING');
        }
        if(!$config['project']){
            log_status('project not set', 'WARNING');
        }
        return false;
    }
}

// get and return the home_url
function wp_home_url(){
    global $config;
    log_status('wp_home_url called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($config['wp_db_creds'],1));

    // connect as the admin mysql user
    $link = mysqli_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    // if the connection succeeded
    if($link) {
        log_status('connected to mysql as root user', 'SUCCESS');
        // see if the database exists
        $db = mysqli_select_db($config['wp_db_creds']['name'], $link);
        // if the database exists
        if($db) {
            log_status('database '.$config['wp_db_creds']['name'].' found', 'SUCCESS');
            // close connection to mysql
            mysqli_close($link);
            // reopen a connection with just this database selected
            $mysqli = @new mysqli($config['wp_db_creds']['host'], $config['wp_db_creds']['user'], $config['wp_db_creds']['pass'], $config['wp_db_creds']['name']);
            if ($mysqli->connect_errno) {
                log_status('failed to connect to mysql with root user: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error, 'WARNING');
            } else {
                log_status('connected to '.$config['mysql_host'].' '.$config['mysql_user'].' '.$config['mysql_pass'].' '.$config['wp_db_creds']['name'].' with prefix '.$config['wp_db_creds']['prefix'], 'SUCCESS');
                // check the home_url and return it
                $home_url = $mysqli->query('SELECT option_value FROM '.$config['wp_db_creds']['prefix'].'options WHERE option_name = "home"');
                if($home_url){
                    $home_url_val = $home_url->fetch_object()->option_value;
                    if($home_url_val){
                        log_status('home url is "'.$home_url_val.'"', 'SUCCESS');
                        return $home_url_val;
                    } else {
                        log_status('home url value undetermined', 'WARNING');
                        return false;
                    }
                } else {
                    log_status('database query for home url unsuccessful', 'WARNING');
                    return false;
                }
            }
        }
    // if the connection failed
    } else {
        mysqli_close($link);
        log_status('connection failed as root user', 'WARNING');
        return false;
    }
    mysqli_close($link);
}

// flatten db creds for log output
function flatten_db_creds(){
    $creds_line = '';
    foreach($config['wp_db_creds'] as $key => $value){
        $creds_line .= $key.'='.$value.'/';
    }
    $creds_line = substr($creds_line, 0, -1);
    return $creds_line;
    // note: use this for tabbed array format: return str_replace("\n", "\n\t", print_r($config['wp_db_creds'],1))
}