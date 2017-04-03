<?php
/*------------------------------------*\
    ::Aqua Hooks - WordPress Helpers
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

// find wp-config.php file
function wp_find_config($dir_proj){
    global $config;
    // set location of the wp-config.php file
    $wp_file = $dir_proj . $config['config_file'];
    // determine if the file exists at root or in a subdirectory
    if(!file_exists($wp_file)){
        $dir_search = escapeshellcmd($dir_proj);
        $wp_file = trim(shell_exec('find '.$dir_search.' -name '.$config['config_file'].' -print -quit')); // may need to use -exit instead of -quit for NetBSD
        if($wp_file){
            log_status('config found at '.$wp_file, 'SUCCESS');
            return $wp_file;
        }
    // return file path if it's at root
    } else {
        log_status('config found at '.$wp_file, 'SUCCESS');
        return $wp_file;
    }
    // return false if the file can't be found
    log_status('config found at '.$wp_file, 'WARNING');
    return false;
}

// return db creds from wp-config.php
function wp_db_creds($dir_proj, $server){
    global $config;
    log_status('wp_db_creds called', 'TITLE');
    log_status('project directory is '.$dir_proj, 'NOTE');
    // variable to store wordpress database credentials
    $wp_db_creds = null;
    // if there's a wp-config.php file then grab it's contents
    $wp_file = $config['config_path'];
    if(file_exists($wp_file) && is_file($wp_file) && is_readable($wp_file)) {
        log_status('file found '.$wp_file, 'SUCCESS');
        $file = @fopen($wp_file, 'r');
        $file_content = fread($file, filesize($wp_file));
        @fclose($file);
        //  match the db credentials
        preg_match_all('/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines);
        if((isset($defines[2]) && ! empty($defines[2])) && (isset($defines[4]) && ! empty($defines[4]))) {

            // for each matched set of elements
            foreach($defines[2] as $key => $define) {
                switch($define) {
                    // start grabbing db creds
                    case 'DB_NAME':
                        $this_name = $defines[4][$key];
                        $key++;
                        $this_user = $defines[4][$key];
                        $key++;
                        $this_pass = $defines[4][$key];
                        $key++;
                        $this_host = $defines[4][$key];
                        break;
                    // when we reach the end of what we're interested in
                    case 'DB_CHARSET':
                        $this_char = $defines[4][$key];
                        break;
                }
            }
            // create an array of the db constants
            $wp_db_creds = array(
                'name' => $this_name,
                'user' => $this_user,
                'pass' => $this_pass,
                'host' => $this_host,
                'char' => $this_char,
            );
            // add the db prefix to the array
            preg_match_all('/\$table_prefix\s+= \'(.+)\'/', $file_content, $db_prefix);
            $wp_db_creds['prefix'] = $db_prefix[1][0];
            // set home url
            $home_url = wp_home_url($wp_db_creds);
            if($home_url){
                $wp_db_creds['home_url'] = $home_url;
            }
            log_status('creds found: ' . flatten_db_creds($wp_db_creds), 'NOTE');
            // update to creds for this server
            $wp_db_creds['name'] = (strtolower(str_replace('-', '_', $server."_".$config['client']."_".$config['project'])));
            $wp_db_creds['user'] = $config['mysql_user'];
            $wp_db_creds['pass'] = $config['mysql_pass'];
            $wp_db_creds['host'] = $config['mysql_host'];
            log_status('updated creds: '.flatten_db_creds($wp_db_creds), 'NOTE');
        }
        // if all credentials were generated
        if(count($wp_db_creds) == 7){
            log_status('returned database credentials: ', 'SUCCESS');
            log_status('they are '.flatten_db_creds($wp_db_creds), 'NOTE');
            return $wp_db_creds;
        // if most credentials were generated (no home url)
        } elseif(!isset($wp_db_creds['home_url']) && $wp_db_creds){
            log_status('return database credentials without home url', 'WARNING');
            log_status('credentials are "'.flatten_db_creds($wp_db_creds).'"', 'WARNING');
            return $wp_db_creds;
        // if the credentials were not generated
        } else {
            log_status('database credentials not generated', 'WARNING');
            return false;
        }
    } else {
        log_status('no config file found', 'WARNING');
    }
}

// stand up a wp database
function wp_db_standup($dir_proj, $wp_db_creds, $server, $client, $proj){
    log_status('wp_db_standup called', 'TITLE');
    // create a new database (returns false if it's already there and will use existing one)
    db_create($wp_db_creds);
    // import the database and store boolean success
    $import_success = db_import($wp_db_creds, $dir_proj . '.db/', $server, $client, $proj);
    // if the database import reports success
    if($import_success){
        // re-check home url (the first one was for the initial database)
        $home_url = wp_home_url($wp_db_creds);
        $wp_db_creds['home_url'] = $home_url;
        log_status('home url: '.$wp_db_creds['home_url']);
        // find and replace a database
        db_far($wp_db_creds, $server, $client, $proj);
    }
}

// update .htaccess path
function wp_htaccess_update($dir_proj){
    global $config;
    log_status('wp_htaccess_update called', 'TITLE');
    // find the file
    $htaccess = $dir_proj.'.htaccess';
    // if it's there and we can read it
    if(file_exists($htaccess) && is_file($htaccess) && is_writable($htaccess)){
        log_status('root .htaccess exists at '.$htaccess, 'SUCCESS');
        // open up the file and copy contents
        $file = fopen($htaccess, "r");
        $file_content = fread($file, filesize($htaccess));
        fclose($file);

        // make a backup copy of the .htaccess file
        $backup_file = $htaccess.'_aqua-hooks-backup';
        $backup_success = copy($htaccess, $backup_file);

        // if backup was successful
        if($backup_success){
            // create the new file text for base
            $pattern = '/(^\s*RewriteBase)\s+(\/+\S*\s*)$/m';
            $replace = " # replaced via aqua-hooks deployment script:\n";
            $replace .= "$1 /".$config['client']."/".$config['project']."/";
            $file_content = preg_replace($pattern, $replace, $file_content);

            // create new file text for rewrite rule
            $pattern = '/(^\s*RewriteRule\s*\.)\s*(\S*\/index.php)\s*(\[\s*L\s*\]\s*)$/m';
            $replace = " # replaced via aqua-hooks deployment script:\n";
            $replace .= "$1 /".$config['client']."/".$config['project']."/index.php $3";
            $file_content = preg_replace($pattern, $replace, $file_content);

            // create new file text for obfuscation
            $pattern = '/^(\s*RewriteCond\s+%{REQUEST_URI})\s+\^(\/\S*wp-admin\s*)$/m';
            $replace = " # replaced via aqua-hooks deployment script:\n";
            $replace .= "$1 ^/".$config['client']."/".$config['project']."$2";
            $file_content = preg_replace($pattern, $replace, $file_content);

            // reopen file and edit it
            $file = fopen($htaccess, "w");
            fwrite($file, "$file_content");
            fclose($file);

            log_status('attempted to update .htaccess to server paths', 'SUCCESS');

        // error if can't create backup
        } else {
            throw new Exception("Unable to backup .htaccess file $backup_success");
        }

    // if we can't edit the file then tell someone about it
    } else {
        log_status('no .htaccess exists or is unwritable at '.$htaccess, 'WARNING');
        log_status('continuing without .htaccess update', 'WARNING');
    }
}

// update wp-config.php values to this server
function wp_update_config($dir_proj, $server, $wp_db_creds){
    global $config;
    log_status('wp_update_config called', 'TITLE');
    // identify the file
    $wp_file = $config['config_path'];
    // if it's there and we can read it
    if(file_exists($wp_file) && is_file($wp_file) && is_writable($wp_file)){
        log_status('config file exists at '.$wp_file, 'SUCCESS');
        // open up the file and copy contents
        $file = fopen($wp_file, "r");
        $file_content = fread($file, filesize($wp_file));
        fclose($file);

        // make a backup copy of the file
        $backup_file = $wp_file.'_aqua-hooks-backup';
        $backup_success = copy($wp_file, $backup_file);

        // if backup was successful
        if($backup_success){

            // identify what needs updating
            $replacements = array(
                'db_name' => array(
                    'pattern'      => '/define\s*?\(\s*?([\'"])(DB_NAME)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si',
                    'replace'      => "define('"."$2"."', '".$wp_db_creds['name']."');",
                ),
                'db_user' => array(
                    'pattern'      => '/define\s*?\(\s*?([\'"])(DB_USER)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si',
                    'replace'      => "define('$2', '".$wp_db_creds['user']."');",
                ),
                'db_password' => array(
                    'pattern'      => '/define\s*?\(\s*?([\'"])(DB_PASSWORD)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si',
                    'replace'      => "define('$2', '".$wp_db_creds['pass']."');",
                ),
                'db_host' => array(
                    'pattern'      => '/define\s*?\(\s*?([\'"])(DB_HOST)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si',
                    'replace'      => "define('$2', '".$wp_db_creds['host']."');",
                ),
            );

            // apply each update and error if unable to
            foreach($replacements as $key => $options){
                $match = preg_match($options['pattern'], $file_content, $defines);
                if(!empty($defines)){
                    log_status('match found for replacing '.$defines[2], 'SUCCESS');
                    $file_content = preg_replace($options['pattern'], $options['replace'], $file_content);
                } else {
                    throw new Exception("Unable to replace config value for $key");
                }
            }

            // reopen file and edit it
            $file = fopen($wp_file, "w");
            fwrite($file, "$file_content");
            fclose($file);
            log_status('attempted to update config to server values', 'SUCCESS');

        // error if can't create backup
        } else {
            throw new Exception("Unable to backup .htaccess file $backup_success");
        }

    // if we can't edit the file then tell someone about it
    } else {
        log_status('no config exists or is is unwritable at '.$wp_file, 'WARNING');
        log_status('continuing without config update', 'WARNING');
    }






    die();
}