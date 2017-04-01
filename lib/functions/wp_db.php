<?php
/*------------------------------------*\
    ::Aqua Hooks - WordPress Database Functions
    ----------------------------------
    author:     Tomas Mulder <tomas@zenman.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

function wp_db($dir_proj, $server){
    global $config;
    log_status("\n\n:: wp_db called");
    log_status('project directory is '.$dir_proj);
    // variable to store wordpress database credentials
    $wp_db_creds = null;
    // set up the db prefix
    $db_prefix = $server . '_';
    log_status('database prefix is '.$db_prefix);
    // set location of the wp-config.php file
    $wp_file = $dir_proj . $config['config_file'];
    if(!file_exists($wp_file)){
        $dir_search = escapeshellcmd($dir_proj);
        $wp_file = trim(shell_exec('find '.$dir_search.' -name '.$config['config_file'].' -print -quit')); // may need to use -exit instead of -quit for NetBSD
    }
    // if there's a wp-config.php file then grab it's contents
    if(file_exists($wp_file) && is_file($wp_file) && is_readable($wp_file)) {
        log_status('file found '.$wp_file);
        $file = @fopen($wp_file, 'r');
        $file_content = fread($file, filesize($wp_file));
        @fclose($file);

        // // get wp defined project name if available
        // preg_match_all('/\$proj_name\s+=\s+\'(.+)\'/', $file_content, $wp_proj_name_matches);
        // $wp_proj_name = (!empty($wp_proj_name_matches[1][0]) ? $wp_proj_name_matches[1][0] : false);

        // // get wp defined password if available
        // preg_match_all('/\$db_pass\s+=\s+\'(.+)\'/', $file_content, $wp_db_pass_matches);
        // $wp_db_pass = (!empty($wp_db_pass_matches[1][0]) ? $wp_db_pass_matches[1][0] : false);

        //  match the db credentials
        preg_match_all('/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines);
// log_status('defines is '.print_r($defines,1));
        // make sure everything got grabbed
        if((isset($defines[2]) && ! empty($defines[2])) && (isset($defines[4]) && ! empty($defines[4]))) {
// log_status('did find stuff');
            // for each matched set of elements
            foreach($defines[2] as $key => $define) {
                switch($define) {
                    // start grabbing db creds
                    case 'DB_NAME':
                        // if(strstr($defines[4][$key], $db_prefix)){
                            $this_name = $defines[4][$key];
// if this wasn't customized then replace variable name
// $this_name = str_replace('$proj_name', $wp_proj_name, $this_name);
                            $key++;
                            $this_user = $defines[4][$key];
// // if this wasn't customized then replace variable name
// $this_user = str_replace('$proj_name', $wp_proj_name, $this_user);
                            $key++;
                            $this_pass = $defines[4][$key];
// // if this wasn't customized then replace variable name
// $this_pass = str_replace('$db_pass', $wp_db_pass, $this_pass);
                            $key++;
                            $this_host = $defines[4][$key];
                        // }
                        break;
                    // when we reach the end of what we're interested in
                    case 'DB_CHARSET':
                        $this_char = $defines[4][$key];
                        break;
                }
            }

            // create an array of the db constants
            $wp_db_creds = array('name' => $this_name, 'user' => $this_user, 'pass' => $this_pass, 'host' => $this_host, 'char' => $this_char);
            // add the db prefix to the array
            preg_match_all('/\$table_prefix\s+= \'(.+)\'/', $file_content, $db_prefix);
            $wp_db_creds['prefix'] = $db_prefix[1][0];
            // set home url
            $homeurl = wp_homeurl($wp_db_creds);
            if($homeurl){
                $wp_db_creds['homeurl'] = $homeurl;
            }
            log_status('resulting creds ');
            log_status(flatten_db_creds($wp_db_creds));
        }
        // if all credentials were generated
        if(count($wp_db_creds) == 7){
            log_status('return database credentials');
            log_status('they are '.flatten_db_creds($wp_db_creds));
            return $wp_db_creds;
        // if most credentials were generated (no home url)
        } elseif(!isset($wp_db_creds['homeurl']) && $wp_db_creds){
            log_status('return database credentials without home url');
            log_status('credentials are "'.flatten_db_creds($wp_db_creds).'"');
            return $wp_db_creds;
        // if the credentials were not generated
        } else {
            log_status('database credentials not generated');
            return false;
        }
    } else {
        log_status('no config file found');
    }
}
