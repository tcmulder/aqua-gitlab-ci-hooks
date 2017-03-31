<?php
/*------------------------------------*\
    ::Aqua Hooks - Database Functions
    ----------------------------------
    author:     Tomas Mulder <tomas@zenman.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if access isn't from git.zenman.com
if($_SERVER['REMOTE_ADDR'] != 'YOUR_IP_ADDRESS'){ exit; }

// create a database
function db_create($db_creds){
    log_status("\n\n: called");
    log_status('database credentials received');
    log_status('they are '.str_replace("\n", "\n\t", print_r($db_creds,1)));
    // connect to mysql
    $link = mysql_connect('localhost', 'root', 'YOUR_PASSWORD');
    if($link) {
        log_status('connected to mysql as root');
        // create the database
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database doesn't exist already
        if (!$db) {
            log_status('database '.$db_creds['name'].' does not exist');
            mysql_query('CREATE DATABASE IF NOT EXISTS '.$db_creds['name'], $link);
            log_status('ran create database '.$db_creds['name']);
            mysql_query('GRANT USAGE ON *.* TO '. $db_creds['user'].'@localhost IDENTIFIED BY \''.$db_creds['pass'].'\'', $link);
            mysql_query('GRANT ALL PRIVILEGES ON '.$db_creds['name'].'.* TO '.$db_creds['user'].'@localhost', $link);
            log_status('created user '.$db_creds['user'].' with privaleges for '.$db_creds['name']);
            mysql_query('FLUSH PRIVILEGES', $link);
            log_status('privileges flushed');
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
    log_status("\n\n: db_export: called");
    log_status('database credentials received');
    log_status('they are '.str_replace("\n", "\n\t", print_r($db_creds,1)));
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
    log_exec('/usr/bin/mysqldump -h'.$db_creds['host'].' -u'.$db_creds['user'].' -p\''.$db_creds['pass'].'\' '.$db_creds['name'].' > '.$db_dir .'db.sql');
}

// import a database
function db_import($db_creds, $db_dir){
    log_status("\n\n: db_import: called");
    log_status('db_import: database credentials received');
    log_status('the credentials are '.str_replace("\n", "\n\t", print_r($db_creds,1)));
    log_status('db_import: database directory is '.$db_dir);
    // variable to store sql dump
    $db_dump = $db_dir.'db.sql';
    // if there is a /.db/db.sql file
    if(file_exists($db_dump)){
        log_status('db_import: file exists '.$db_dump);
        // drop the database's tables
        log_status('db_import: drop databases tables');
        exec('mysqldump -h'.$db_creds['host'].' -u'.$db_creds['user'].' -p\''.$db_creds['pass'].'\' --no-data '.$db_creds['name'].' | grep ^DROP | mysql -h'.$db_creds['host'].' -u'.$db_creds['user'].' -p\''.$db_creds['pass'].'\' '.$db_creds['name']);
        // import the /.db/db.sql file
        log_status('db_import: import file '.$db_dump);
        exec('mysql -h'.$db_creds['host'].' -u'.$db_creds['user'].' -p\''.$db_creds['pass'].'\' '.$db_creds['name'].' < '.$db_dump);
        return true;
    // if there is no /.db/db.sql
    } else {
        // report import as failed
        log_status('db_import: file does not exist '.$db_dump);
        return false;
    }
}

// find and replace in a database
function db_far($db_creds, $server, $server_version, $client, $proj) {
    log_status("\n\n: db_far: called");
    log_status('database credentials received');
    log_status('they are '.str_replace("\n", "\n\t", print_r($db_creds,1)));
    log_status('server is '.$server);
    log_status('client is '.$client);
    log_status('project is '.$proj);
    // if we have enough info
    if(count($db_creds) == 7 && $server && $client && $proj){
        log_status('run far');
        // create find and replace command
        $far = 'php lib/functions/far.php ';
        $far .= '\''.$db_creds['name'].'\' ';
        $far .= '\''.$db_creds['user'].'\' ';
        $far .= '\''.$db_creds['pass'].'\' ';
        $far .= '\''.$db_creds['host'].'\' ';
        $far .= '\''.$db_creds['char'].'\' ';
        $far .= '\''.preg_replace("(^https?:)", "", $db_creds['siteurl']).'\' '; // protocol-relative url
        $far .= '\'//'.$server.$server_version.'.zenman.com/sites/'.$client.'/'.$proj.'\'';
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

// get and return the siteurl
function wp_siteurl($db_creds){
    log_status("\n\n: wp_siteurl: called");
    log_status('database credentials received');
    log_status('they are '.str_replace("\n", "\n\t", print_r($db_creds,1)));

    // aside: it seems ludicrous to me that it'd be impossible to test a database connection NOT as the
    // root user without throwing a php error, but I have to do it this way currently or it just fails

    // connect as the admin mysql user
    $link = mysql_connect('localhost', 'root', 'YOUR_PASSWORD');
    // if the connection succeeded
    if($link) {
        log_status('connected to mysql as root user');
        // see if the database exists
        $db = mysql_select_db($db_creds['name'], $link);
        // if the database exists
        if($db) {
            log_status('database '.$db_creds['name'].' found');
            // close the connection as the administrator
            mysql_close($link);
            // reopen a connection with the database credentials
            $mysqli = @new mysqli($db_creds['host'], $db_creds['user'], $db_creds['pass'], $db_creds['name']);
	    if ($mysqli->connect_errno) {
		    log_status('failed to connect to mysql as user: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	    } else {
		    log_status('connected to '.$db_creds['host'].' '.$db_creds['user'].' '.$db_creds['pass'].' '.$db_creds['name'].' with prefix '.$db_creds['prefix']);
		    // check the siteurl and return it
		    $siteurl = $mysqli->query('SELECT option_value FROM '.$db_creds['prefix'].'options WHERE option_name = "siteurl"');
		    if($siteurl){
			    $siteurl_val = $siteurl->fetch_object()->option_value;
			    if($siteurl_val){
				    log_status('siteurl is "'.$siteurl_val.'"');
				    return $siteurl_val;
			    } else {
				    log_status('siteurl value undetermined');
				    return false;
			    }
		    } else {
			    log_status('database query for siteurl unsuccessful');
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
