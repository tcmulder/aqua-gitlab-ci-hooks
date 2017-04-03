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
function db_create($db_creds){
    global $config;
    log_status('db_create called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($db_creds,1));
    // connect to mysql
    $link = mysql_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    if($link) {
        log_status('connected to mysql as root', 'NOTE');
        // create the database
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database doesn't exist already
        if (!$db) {
            log_status('database '.$db_creds['name'].' does not exist', 'NOTE');
            mysql_query('CREATE DATABASE IF NOT EXISTS '.$db_creds['name'], $link);
            log_status('ran create database '.$db_creds['name'], 'NOTE');
// mysql_query('GRANT USAGE ON *.* TO '. $config['mysql_user'].'@localhost IDENTIFIED BY \''.$config['mysql_pass'].'\'', $link);
// mysql_query('GRANT ALL PRIVILEGES ON '.$db_creds['name'].'.* TO '.$config['mysql_user'].'@localhost', $link);
// log_status('created user '.$config['mysql_user'].' with privaleges for '.$db_creds['name']);
// mysql_query('FLUSH PRIVILEGES', $link);
// log_status('privileges flushed');
        } else {
            log_status('database already exists', 'SUCCESS');
            mysql_close($link);
            return false;
        }
    }
    mysql_close($link);
}

// export (mysqldump) a database
function db_export($db_creds, $db_dir){
    global $config;
    log_status('db_export called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($db_creds,1), 'NOTE');
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
    log_exec('/usr/bin/mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name'].' > '.$db_dir .'db.sql');
}

// import a database
function db_import($db_creds, $db_dir){
    global $config;
    log_status('db_import called', 'TITLE');
    log_status('db_import: database credentials received', 'SUCCESS');
    log_status('the credentials are '.flatten_db_creds($db_creds,1), 'NOTE');
    log_status('db_import: database directory is '.$db_dir, 'NOTE');
    // variable to store sql dump
    $db_dump = $db_dir.'db.sql';
    // if there is a /.db/db.sql file
    if(file_exists($db_dump)){
        log_status('db_import: file exists '.$db_dump, 'SUCCESS');
        // drop the database's tables
        log_status('db_import: drop databases tables', 'NOTE');
        exec('mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' --no-data '.$db_creds['name'].' | grep ^DROP | mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name']);
        // import the /.db/db.sql file
        log_status('db_import: import file '.$db_dump, 'NOTE');
        exec('mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name'].' < '.$db_dump);
        return true;
    // if there is no /.db/db.sql
    } else {
        // report import as failed
        log_status('db_import: file does not exist '.$db_dump, 'WARNING');
        return false;
    }
}

// find and replace in a database
function db_far($db_creds, $server, $client, $proj) {
    global $config;
    log_status('db_far called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($db_creds,1), 'NOTE');
    log_status('server is '.$server, 'NOTE');
    log_status('client is '.$client, 'NOTE');
    log_status('project is '.$proj, 'NOTE');
    // if we have enough info
    if(count($db_creds) == 7 && $server && $client && $proj){
        log_status('call made to run find and replace', 'NOTE');
        // create find and replace command
        $far = 'php lib/functions/far.php ';
        $far .= '\''.$db_creds['name'].'\' ';
        $far .= '\''.$config['mysql_user'].'\' ';
        $far .= '\''.$config['mysql_pass'].'\' ';
        $far .= '\''.$config['mysql_host'].'\' ';
        $far .= '\''.$db_creds['char'].'\' ';
        $far .= '\''.preg_replace("(^https?:)", "", $db_creds['home_url']).'\' '; // protocol-relative url
        $far .= '\'//'.$server.'.'.$config['base_url'].'/'.$client.'/'.$proj.'\'';

        //execute find and replace
        $output = shell_exec($far);
        log_status('ran with output: ', 'NOTE');
        log_status($output);
    // if we do not have all the info
    } else {
        if(count($db_creds) != 7){
            log_status('7 perimeters not received', 'WARNING');
        }
        if(!$server){
            log_status('server not set', 'WARNING');
        }
        if(!$client){
            log_status('client not set', 'WARNING');
        }
        if(!$proj){
            log_status('project not set', 'WARNING');
        }
        return false;
    }
}

// get and return the home_url
function wp_home_url($db_creds){
    global $config;
    log_status('wp_home_url called', 'TITLE');
    log_status('database credentials received', 'SUCCESS');
    log_status('they are '.flatten_db_creds($db_creds,1));

    // connect as the admin mysql user
    $link = mysql_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    // if the connection succeeded
    if($link) {
        log_status('connected to mysql as root user', 'SUCCESS');
        // see if the database exists
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database exists
        if($db) {
            log_status('database '.$db_creds['name'].' found', 'SUCCESS');
            // close connection to mysql
            mysql_close($link);
            // reopen a connection with just this database selected
            $mysqli = @new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_pass'], $db_creds['name']);
            if ($mysqli->connect_errno) {
                log_status('failed to connect to mysql with root user: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error, 'WARNING');
            } else {
                log_status('connected to '.$config['mysql_host'].' '.$config['mysql_user'].' '.$config['mysql_pass'].' '.$db_creds['name'].' with prefix '.$db_creds['prefix'], 'SUCCESS');
                // check the home_url and return it
                $home_url = $mysqli->query('SELECT option_value FROM '.$db_creds['prefix'].'options WHERE option_name = "home"');
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
        mysql_close($link);
        log_status('connection failed as root user', 'WARNING');
        return false;
    }
    mysql_close($link);
}

// flatten db creds for log output
function flatten_db_creds($db_creds){
    $creds_line = '';
    foreach($db_creds as $key => $value){
        $creds_line .= $key.'='.$value.'/';
    }
    $creds_line = substr($creds_line, 0, -1);
    return $creds_line;
    // note: use this for tabbed array format: return str_replace("\n", "\n\t", print_r($db_creds,1))
}