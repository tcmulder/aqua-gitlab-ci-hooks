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
    log_status("\n\n: called");
    log_status('database credentials received');
    log_status('they are '.flatten_db_creds($db_creds,1));
    // connect to mysql
    $link = mysql_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    if($link) {
        log_status('connected to mysql as root');
        // create the database
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database doesn't exist already
        if (!$db) {
            log_status('database '.$db_creds['name'].' does not exist');
            mysql_query('CREATE DATABASE IF NOT EXISTS '.$db_creds['name'], $link);
            log_status('ran create database '.$db_creds['name']);
// mysql_query('GRANT USAGE ON *.* TO '. $config['mysql_user'].'@localhost IDENTIFIED BY \''.$config['mysql_pass'].'\'', $link);
// mysql_query('GRANT ALL PRIVILEGES ON '.$db_creds['name'].'.* TO '.$config['mysql_user'].'@localhost', $link);
// log_status('created user '.$config['mysql_user'].' with privaleges for '.$db_creds['name']);
// mysql_query('FLUSH PRIVILEGES', $link);
// log_status('privileges flushed');
        } else {
            log_status('database already exists');
            mysql_close($link);
            return false;
        }
    }
    mysql_close($link);
}

// export (mysqldump) a database
function db_export($db_creds, $db_dir){
    global $config;
    log_status("\n\n: db_export: called");
    log_status('database credentials received');
    log_status('they are '.flatten_db_creds($db_creds,1));
    log_status('database directory is '.$db_dir);
    // if the /.db/ directory doesn't exist
    if(!file_exists($db_dir)){
        log_status('create /.db/ directory');
        // create the directory
        mkdir($db_dir);
    } else {
        log_status('/.db/ directory exists');
    }
    // dump the database
    log_status('export /.db/db.sql');
    log_exec('/usr/bin/mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name'].' > '.$db_dir .'db.sql');
}

// import a database
function db_import($db_creds, $db_dir){
    global $config;
    log_status("\n\n: db_import: called");
    log_status('db_import: database credentials received');
    log_status('the credentials are '.flatten_db_creds($db_creds,1));
    log_status('db_import: database directory is '.$db_dir);
    // variable to store sql dump
    $db_dump = $db_dir.'db.sql';
    // if there is a /.db/db.sql file
    if(file_exists($db_dump)){
        log_status('db_import: file exists '.$db_dump);
        // drop the database's tables
        log_status('db_import: drop databases tables');
        exec('mysqldump -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' --no-data '.$db_creds['name'].' | grep ^DROP | mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name']);
        // import the /.db/db.sql file
        log_status('db_import: import file '.$db_dump);
        exec('mysql -h'.$config['mysql_host'].' -u'.$config['mysql_user'].' -p\''.$config['mysql_pass'].'\' '.$db_creds['name'].' < '.$db_dump);
        return true;
    // if there is no /.db/db.sql
    } else {
        // report import as failed
        log_status('db_import: file does not exist '.$db_dump);
        return false;
    }
}

// find and replace in a database
function db_far($db_creds, $server, $client, $proj) {
    global $config;
    log_status("\n\n: db_far: called");
    log_status('database credentials received');
    log_status('they are '.flatten_db_creds($db_creds,1));
    log_status('server is '.$server);
    log_status('client is '.$client);
    log_status('project is '.$proj);
    // if we have enough info
    if(count($db_creds) == 7 && $server && $client && $proj){
        log_status('run far');
        // create find and replace command
        $far = 'php lib/functions/far.php ';
        $far .= '\''.$db_creds['name'].'\' ';
        $far .= '\''.$config['mysql_user'].'\' ';
        $far .= '\''.$config['mysql_pass'].'\' ';
        $far .= '\''.$config['mysql_host'].'\' ';
        $far .= '\''.$db_creds['char'].'\' ';
        $far .= '\''.preg_replace("(^https?:)", "", $db_creds['homeurl']).'\' '; // protocol-relative url
        $far .= '\'//'.$server.'.zenman.com/sites/'.$client.'/'.$proj.'\'';
        //execute find and replace
        $output = shell_exec($far);
        log_status('ran with output: ');
        log_status($output);
    // if we do not have all the info
    } else {
        if(count($db_creds) != 7){
            log_status('7 perimeters not received');
        }
        if(!$server){
            log_status('server not set');
        }
        if(!$client){
            log_status('client not set');
        }
        if(!$proj){
            log_status('project not set');
        }
        return false;
    }
}

// get and return the homeurl
function wp_homeurl($db_creds){
    global $config;
    log_status("\n\n: wp_homeurl: called");
    log_status('database credentials received');
    log_status('they are '.flatten_db_creds($db_creds,1));

    // connect as the admin mysql user
    $link = mysql_connect('localhost', $config['mysql_user'], $config['mysql_pass']);
    // if the connection succeeded
    if($link) {
        log_status('connected to mysql as root user');
        // see if the database exists
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database exists
        if($db) {
            log_status('database '.$db_creds['name'].' found');
            // close connection to mysql
            mysql_close($link);
            // reopen a connection with just this database selected
            $mysqli = @new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_pass'], $db_creds['name']);
            if ($mysqli->connect_errno) {
                log_status('failed to connect to mysql with root user: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            } else {
                log_status('connected to '.$config['mysql_host'].' '.$config['mysql_user'].' '.$config['mysql_pass'].' '.$db_creds['name'].' with prefix '.$db_creds['prefix']);
                // check the homeurl and return it
                $homeurl = $mysqli->query('SELECT option_value FROM '.$db_creds['prefix'].'options WHERE option_name = "home"');
                if($homeurl){
                    $homeurl_val = $homeurl->fetch_object()->option_value;
                    if($homeurl_val){
                        log_status('home url is "'.$homeurl_val.'"');
                        return $homeurl_val;
                    } else {
                        log_status('home url value undetermined');
                        return false;
                    }
                } else {
                    log_status('database query for home url unsuccessful');
                    return false;
                }
            }
        }
    // if the connection failed
    } else {
        mysql_close($link);
        log_status('connection failed as root user');
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