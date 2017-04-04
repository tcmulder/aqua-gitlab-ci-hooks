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
    if($config['wp_db_creds']){
        log_status('database credentials received', 'SUCCESS');
        log_status('database credentials are: '.flatten_db_creds($config['wp_db_creds'],1));
        // connect to mysql
        $mysqli = @new mysqli('localhost', $config['mysql_user'], $config['mysql_pass']);
        // report errors
        if ($mysqli->connect_errno) {
            throw new Exception('failed to connect to mysql with root user: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        // on connection success
        } else {
            // make sure the database doesn't already exist
            $db = $mysqli->select_db($config['wp_db_creds']['name']);
            if(!$db){
                log_status('connected to mysql as root', 'NOTE');
                // attempt to create the database
                $home_url = $mysqli->query('CREATE DATABASE IF NOT EXISTS '.$config['wp_db_creds']['name']);
                log_status('ran create database '.$config['wp_db_creds']['name'], 'NOTE');
                $db = $mysqli->select_db($config['wp_db_creds']['name']);
                // report success of database creation
                if($db){
                    log_status('new database created named '.$config['wp_db_creds']['name'], 'SUCCESS');
                } else {
                    throw new Exception('Attempt to create database failed');
                }
            } else {
                log_status('database exists named '.$config['wp_db_creds']['name'], 'SUCCESS');
            }
        }
        $mysqli->close();
    // if there are no database credentials to work with
    } else {
        throw new Exception('No database credentials established: set as'.flatten_db_creds($config['wp_db_creds']));
    }
}

// export (mysqldump) a database
function db_export(){
    global $config;
    log_status('db_export called', 'TITLE');
    if($config['wp_db_creds']){
        log_status('database credentials received', 'SUCCESS');
        log_status('database credentials are: '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
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
    // if there are no database credentials to work with
    } else {
        throw new Exception('No database credentials established: set as'.flatten_db_creds($config['wp_db_creds']));
    }
}

// import a database
function db_import(){
    global $config;
    $db_dir = $config['dir_project'].'.db/';
    log_status('db_import called', 'TITLE');
    if($config['wp_db_creds']){
        log_status('database credentials received', 'SUCCESS');
        log_status('database credentials are: '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
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
    // if there are no database credentials to work with
    } else {
        throw new Exception('No database credentials established: set as'.flatten_db_creds($config['wp_db_creds']));
    }
}

// find and replace in a database
function db_far(){
    global $config;
    log_status('db_far called', 'TITLE');
    if($config['wp_db_creds']){
        log_status('database credentials received', 'SUCCESS');
        log_status('database credentials are: '.flatten_db_creds($config['wp_db_creds'],1), 'NOTE');
        log_status('server is '.$config['server'], 'NOTE');
        log_status('client is '.$config['client'], 'NOTE');
        log_status('project is '.$config['project'], 'NOTE');
        // if we have enough info
        if(count($config['wp_db_creds']) == 7 && $config['server'] && $config['client'] && $config['project']){
            log_status('call made to run find and replace', 'NOTE');

            // create find and replace command
            $far =  'php lib/functions/db_far/srdb.cli.php ';
            $far .= '-h\''.$config['wp_db_creds']['host'].'\' ';
            $far .= '-u\''.$config['wp_db_creds']['user'].'\' ';
            $far .= '-p\''.$config['wp_db_creds']['pass'].'\' ';
            $far .= '-n\''.$config['wp_db_creds']['name'].'\' ';
            $far .= '-s\''.preg_replace("(^https?:)", "", $config['wp_db_creds']['home_url']).'\' '; // protocol-relative url
            $far .= '-r\'//'.$config['server'].'.'.$config['base_url'].'/'.$config['client'].'/'.$config['project'].'\' ';

            //execute find and replace
            $output = shell_exec($far);
            log_status('ran find and replace ran', 'NOTE');
            log_status('command was: '.$far);
            log_status('unable to determine success/fail but output was: '.$output);
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
    // if there are no database credentials to work with
    } else {
        throw new Exception('No database credentials established: set as'.flatten_db_creds($config['wp_db_creds']));
    }
}

// get and return the home_url
function wp_home_url(){
    global $config;
    log_status('wp_home_url called', 'TITLE');
    if($config['wp_db_creds']){
        log_status('database credentials received', 'SUCCESS');
        log_status('database credentials are: '.flatten_db_creds($config['wp_db_creds'],1));
        // connect as the admin mysql user
        $link = @new mysqli('localhost', $config['mysql_user'], $config['mysql_pass']);
        // if the connection succeeded
        if($link) {
            log_status('connected to mysql as root user', 'SUCCESS');
            // see if the database exists
            $db = $link->select_db($config['wp_db_creds']['name']);
            // if the database exists
            if($db) {
                log_status('database '.$config['wp_db_creds']['name'].' found', 'SUCCESS');
                // close connection to mysql
                $link->close();
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
                $mysqli->close();
            }
        // if the connection failed
        } else {
            $link->close();
            log_status('connection failed as root user', 'WARNING');
            return false;
        }
        $link->close();
    // if there are no database credentials to work with
    } else {
        throw new Exception('No database credentials established: set as'.flatten_db_creds($config['wp_db_creds']));
    }
}

// flatten db creds for log output
function flatten_db_creds($creds){
    $creds_line = '';
    foreach($creds as $key => $value){
        $creds_line .= $key.'='.$value.'/';
    }
    $creds_line = substr($creds_line, 0, -1);
    return $creds_line;
    // note: use this for tabbed array format: return str_replace("\n", "\n\t", print_r($config['wp_db_creds'],1))
}