#!/usr/bin/php
<?php
/*------------------------------------*\
    ::Aqua Hooks - Database Find and Replace
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0

    First Written 2009-05-25 by David Coveney of Interconnect IT Ltd (UK)
    http://www.davidcoveney.com or http://www.interconnectit.com
    and released under the WTFPL *
\*------------------------------------*/

// if all the values were received from shell
if(empty($argv[8]) && ($argv[1] != '' && $argv[2] != '' && $argv[3] != '' && $argv[4] != '' && $argv[5] != '' && $argv[6] != '' && $argv[7] != '')){

    // put the values in an array
    $wp_config_array = array(
        'name'  => $argv[1],
        'user'  => $argv[2],
        'pass'  => $argv[3],
        'host'  => $argv[4],
        'char'  => $argv[5],
        'from'  => $argv[6],
        'to'    => $argv[7]
    );

    // output the values
    echo "far input values:\n";
    echo " input name......".$wp_config_array['name']."\n";
    echo " input user......".$wp_config_array['user']."\n";
    echo " input pass......".$wp_config_array['pass']."\n";
    echo " input host......".$wp_config_array['host']."\n";
    echo " input char......".$wp_config_array['char']."\n";
    echo " input from......".$wp_config_array['from']."\n";
    echo " input to........".$wp_config_array['to'];

    // run far on them
    $appDatabaseScrub = new AppDatabaseScrub($wp_config_array);
// if all the values were not received by shell
} else {
    echo "far encountered an error.\n";
}

class AppDatabaseScrub{

    function AppDatabaseScrub($wp_config_array){
        global $appPromoteSiteSettings;
        // Warn if we're running in safe mode as we'll probably time out.
        if (ini_get('safe_mode')) {
                echo "Warning\n";
                printf('Safe mode is on so you may run into problems if it takes longer than %s seconds to process your request.', ini_get('max_execution_time'));
        }
        // Check and clean all vars
        $this->errors = array();

        // DB details
        $this->host = $wp_config_array['host'];
        $this->data = $wp_config_array['name'];
        $this->user = $wp_config_array['user'];
        $this->pass = $wp_config_array['pass'];
        $this->char = $wp_config_array['char'];

        // Search replace details
        $this->srch = $wp_config_array['from']; //find this
        $this->rplc = $wp_config_array['to']; //replace with this

        // Tables to scanned
        $this->check_db_load_tables($wp_config_array);

        // Check and clean the tables array
        $this->tables = array_filter($this->all_tables, array('appDatabaseScrub','check_table_array'));

        // Do we want to skip changing the guid column
        $this->guid = 1;
        $this->exclude_cols = array('guid'); // Add columns to be excluded from changes to this array. If the GUID checkbox is ticked they'll be skipped.

        $this->scrub_data();

    }

    /**
     * Used to check the $_post tables array and remove any that don't exist.
     *
     * @param array $table The list of tables from the $_post var to be checked.
     *
     * @return array    Same array as passed in but with any tables that don'e exist removed.
     */
    function check_table_array($table = ''){
        return in_array($table, $this->all_tables);
    }

    /**
     * Simple html esc
     *
     * @param string $string Thing that needs escaping
     * @param bool $echo   Do we echo or return?
     *
     * @return string    Escaped string.
     */
    function esc_html_attr($string = '', $echo = false){
        $output = htmlentities($string, ENT_QUOTES, 'UTF-8');
        if ($echo) {
                echo $output;
        } else {
            return $output;
        }
    }

    /**
     * Take a serialised array and unserialise it replacing elements as needed and
     * unserialising any subordinate arrays and performing the replace on those too.
     *
     * @param string $from       String we're looking to replace.
     * @param string $to         What we want it to be replaced with
     * @param array  $data       Used to pass any subordinate arrays back to in.
     * @param bool   $serialised Does the array passed via $data need serialising.
     *
     * @return array    The original array with all elements replaced as needed.
     */
    function recursive_unserialize_replace($from = '', $to = '', $data = '', $serialised = false) {

        // some unseriliased data cannot be re-serialised eg. SimpleXMLElements
        try {
            if (is_string($data) && ($unserialized = @unserialize($data)) !== false) {
                $data = $this->recursive_unserialize_replace($from, $to, $unserialized, true);
            } elseif (is_array($data)) {
                $_tmp = array();
                foreach ($data as $key => $value) {
                    $_tmp[ $key ] = $this->recursive_unserialize_replace($from, $to, $value, false);
                }
                $data = $_tmp;
                unset($_tmp);
            } else {
                if (is_string($data)) {
                    $data = str_replace($from, $to, $data);
                }
            }
            if ($serialised) {
                return serialize($data);
            }
        } catch(Exception $error) {
            // not handled in original script
        }
        return $data;
    }

    /**
     * Is the string we're dealing with a serialised string? (NOT USED ANY MORE)
     *
     * @param string $data The string we want to check
     *
     * @return bool    true if serialised.
     */
    function is_serialized_string($data) {
        // if it isn't a string, it isn't a serialized string
        if (!is_string($data)){
            return false;
        }
        $data = trim($data);
        if (preg_match('/^s:[0-9]+:.*;$/s', $data)) {// this should fetch all serialized strings
            return true;
        }
        return false;
    }

    /**
     * The main loop triggered in step 5. Up here to keep it out of the way of the
     * HTML. This walks every table in the db that was selected in step 3 and then
     * walks every row and column replacing all occurences of a string with another.
     * We split large tables into 50,000 row blocks when dealing with them to save
     * on memmory consumption.
     *
     * @param mysql  $connection The db connection object
     * @param string $search     What we want to replace
     * @param string $replace    What we want to replace it with.
     * @param array  $tables     The tables we want to look at.
     *
     * @return array    Collection of information gathered during the run.
     */
    function icit_srdb_replacer($connection, $search = '', $replace = '', $tables = array()) {
        global $guid, $exclude_cols;

        $report = array(
            'tables' => 0,
            'rows' => 0,
            'change' => 0,
            'updates' => 0,
            'start' => microtime(),
            'end' => microtime(),
            'errors' => array(),
        );

        if (is_array($tables) && ! empty($tables)) {
            foreach($tables as $table) {
                $report[ 'tables' ]++;
                $columns = array();

                // Get a list of columns in this table
                $fields = mysql_query('DESCRIBE ' . $table, $connection);
                    while($column = mysql_fetch_array($fields)){
                        $columns[ $column[ 'Field' ] ] = $column[ 'Key' ] == 'PRI' ? true : false;
                    }

                    // Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
                    $row_count = mysql_query('SELECT COUNT(*) FROM ' . $table, $connection);
                    $rows_result = mysql_fetch_array($row_count);
                    $row_count = $rows_result[ 0 ];
                    if ($row_count == 0){
                        continue;
                    }

                    $page_size = 50000;
                    $pages = ceil($row_count / $page_size);

                for($page = 0; $page < $pages; $page++) {
                    $current_row = 0;
                    $start = $page * $page_size;
                    $end = $start + $page_size;
                    // Grab the content of the table
                    $data = mysql_query(sprintf('SELECT * FROM %s LIMIT %d, %d', $table, $start, $end), $connection);
                    if (!$data){
                        $report[ 'errors' ][] = mysql_error();
                    }
                    while ($row = mysql_fetch_array($data)) {
                        $report[ 'rows' ]++; // Increment the row counter
                        $current_row++;

                        $update_sql = array();
                        $where_sql = array();
                        $upd = false;

                        foreach($columns as $column => $primary_key) {
                            if ($guid == 1 && in_array($column, $exclude_cols)){
                                continue;
                            }
                            $edited_data = $data_to_fix = $row[ $column ];

                            // Run a search replace on the data that'll respect the serialisation.
                            $edited_data = $this->recursive_unserialize_replace($search, $replace, $data_to_fix);

                            // Something was changed
                            if ($edited_data != $data_to_fix) {
                                $report[ 'change' ]++;
                                $update_sql[] = $column . ' = "' . mysql_real_escape_string($edited_data) . '"';
                                $upd = true;
                            }
                            if ($primary_key){
                                $where_sql[] = $column . ' = "' . mysql_real_escape_string($data_to_fix) . '"';
                            }
                        }
                        if ($upd && ! empty($where_sql)) {
                            $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $update_sql) . ' WHERE ' . implode(' AND ', array_filter($where_sql));
                            $result = mysql_query($sql, $connection);
                            if (! $result){
                                $report[ 'errors' ][] = mysql_error();
                            } else {
                                $report[ 'updates' ]++;
                            }
                        } elseif ($upd) {
                            $report[ 'errors' ][] = sprintf('"%s" has no primary key, manual change needed on row %s.', $table, $current_row);
                        }
                    }
                }
            }
        }
        $report[ 'end' ] = microtime();
        return $report;
    }


    /**
     * Take an array and turn it into an English formatted list. Like so:
     * array('a', 'b', 'c', 'd'); = a, b, c, or d.
     *
     * @param array $input_arr The source array
     *
     * @return string    English formatted string
     */
    function eng_list($input_arr = array(), $sep = ', ', $before = '"', $after = '"') {
        if (! is_array($input_arr)){
            return false;
        }
        $_tmp = $input_arr;

        if (count($_tmp) >= 2) {
            $end2 = array_pop($_tmp);
            $end1 = array_pop($_tmp);
            array_push($_tmp, $end1 . $after . ' or ' . $before . $end2);
        }
        return $before . implode($before . $sep . $after, $_tmp) . $after;
    }
    function check_db_load_tables($wp_config_array){
        $this->connection = @mysql_connect($this->host, $this->user, $this->pass);
        if (! $this->connection) {
            $this->errors[] = mysql_error();
        }
        if (! empty($this->char)) {
            if (function_exists('mysql_set_charset')){
                mysql_set_charset($this->char, $this->connection);
            } else{
                mysql_query('SET NAMES ' . $this->char, $this->connection);  // Shouldn't really use this, but there for backwards compatibility
            }
        }

        // Do we have any tables and if so build the all tables array
        $this->all_tables = array();
        @mysql_select_db($this->data, $this->connection);
        $this->all_tables_mysql = @mysql_query('SHOW TABLES', $this->connection);
        if (! $this->all_tables_mysql) {
            $this->errors[] = mysql_error();
        } else {
            while ($this->table = mysql_fetch_array($this->all_tables_mysql)) {
                $this->all_tables[] = $this->table[ 0 ];
            }
        }
    }
    function validate_search(){
        if (empty($this->srch)) {
            $this->errors[] = 'Missing search string.';
        }
        if (empty($this->rplc)) {
            $this->errors[] = 'Replace string is blank.';
        }
        if (! (empty($this->rplc) && empty($this->srch)) && $this->rplc == $this->srch) {
            $this->errors[] = 'Search and replace are the same, please check your values.';
        }
    }

    function scrub_data(){
        @ set_time_limit(60 * 10);
        // Try to push the allowed memory up, while we're at it
        @ ini_set('memory_limit', '1024M');

        // Process the tables
        if (isset($this->connection)){
            $this->report = $this->icit_srdb_replacer($this->connection, $this->srch, $this->rplc, $this->tables);
        }

        // Output any errors encountered during the db work.
        if (! empty($this->report[ 'errors' ]) && is_array($this->report[ 'errors' ])) {
            foreach($this->report[ 'errors' ] as $error){
                echo "$this->error\n";
            }
        }

        // Calc the time taken.
        $this->time = array_sum(explode(' ', $this->report[ 'end' ])) - array_sum(explode(' ', $this->report[ 'start' ]));
        printf("\n
 far result values:
 replaced........%s
 new string......%s
 tables..........%d
 row change......%d
 cell change.....%d
 db change.......%d
 seconds taken...%f\n",
            $this->srch,
            $this->rplc,
            $this->report[ 'tables' ],
            $this->report[ 'rows' ],
            $this->report[ 'change' ],
            $this->report[ 'updates' ],
            $this->time);

    }

}