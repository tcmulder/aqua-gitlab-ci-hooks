<?php
/*------------------------------------*\
    ::Aqua Hooks - Log Helpers
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

// log status by outputting text into the log
function log_status($status, $type='NOTE'){
    global $config;
    if('true' == $config['log'] || 'debug' == $config['log']){
        $file = $config['dir_hooks'].'debug.log';
        // extra debug info
        $extra_debug = '';
        if('debug' == $config['log']){
            $bt = debug_backtrace();
            $caller = array_shift($bt);
            $debug_file = array_pop(explode('/', $caller['file']));
            $debug_line = $caller['line'];
            $extra_debug = str_pad(" [$debug_file:$debug_line] ",22) ;
        }
        // handle special types
        $start = "";
        $end = "\n";
        if('TITLE' == $type){
            $start = "\n::";
        }
        if('SEPARATE' == $type){
            $start = "\n\n\n\n ::";
            $end = " @ ".date("Y-m-d H:i:s")."\n\n";
        }
        // write the output to the log
        file_put_contents($file, colorize($start.$extra_debug."$status".$end, $type), FILE_APPEND | LOCK_EX);
        // truncate the log if it gets too large
        if($lines = count(file($file)) >= 100000){
            $truncated = shell_exec("tail -n 1000 $file");
            file_put_contents($file, $truncated, LOCK_EX);
        }
    } else {
        return false;
    }
}
// log status by executing and then outputting text into the log
function log_exec($exec, $type='NOTE'){
    global $config;
    if('true' == $config['log'] || 'debug' == $config['log']){
        $file = $config['dir_hooks'].'debug.log';
        // extra debug info
        $extra_debug = '';
        if('debug' == $config['log']){
            $bt = debug_backtrace();
            $caller = array_shift($bt);
            $debug_file = array_pop(explode('/', $caller['file']));
            $debug_line = $caller['line'];
            $extra_debug = str_pad(" [$debug_file:$debug_line] ",25) ;
        }
        // report what was called
        file_put_contents($file, colorize($extra_debug."called on command line: \n\t$exec\n", $type), FILE_APPEND | LOCK_EX);
        // execute and capture response
        exec("$exec 2>&1", $output);
        // parse response of execution for presentation
        $exec_output = '';
        if(!empty($output)){
            $exec_output = "\n\t".str_replace("\n", "\n\t", print_r($output,1))."\n";
        // shorten output for empty arrays
        } else {
            $exec_output = "Array()\n";
        }
        // write the output to the log
        file_put_contents($file, colorize($extra_debug."prevous command output: $exec_output", $type), FILE_APPEND | LOCK_EX);
        // truncate the log if it gets too large
        if($lines = count(file($file)) >= 100000){
            $truncated = shell_exec("tail -n 1000 $file");
            file_put_contents($file, $truncated, LOCK_EX);
        }
    } else {
        exec("$exec");
    }
}
// add colorizing to output
function colorize($text, $status) {
    $out = '';
    switch($status) {
        case 'TITLE':
            $out = "\e[0;36m"; // cyan
            break;
        case 'SEPARATE':
            $out = "\e[0;36m\e[40m"; // cyan on black
            break;
        case 'SUCCESS':
            $out = "\e[0;32m"; // green
            break;
        case 'FAILURE':
            $out = "\e[0;31m"; // red
            break;
        case 'WARNING':
            $out = "\e[0;33m"; // yellow
            break;
        case 'NOTE':
        default:
            $out = "\e[0m"; // no color added
            break;
    }
    return chr(27) . "$out" . "$text" . chr(27) . "\e[0m";
}