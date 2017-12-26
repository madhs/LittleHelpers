<?php

$st = microtime(true);

define('COLUMN_FIRST_ROW', true);
define('NL', "\n");                # preferred new line

// $date_columns, $int_columns *must* be arrays of the INT datatype
$date_columns = array(3, 7, 8, 11);
$int_columns = array(0);
$date_format = 'DD-MON-YYYY HH24:MI:SS';

$table_name = 'MAILER_ENGIN_DATA_171209';
$commit_point = 10;
$insert_all_quantity = 100;

$row = 1;
$insert_all_count = 0;
$insert_sql_count = 0;
$can_commit = false;

$sql_body = array();
$file_chunk = array();

// FIX ME: Make sure none of the columns in $date_column are in $int_columns and vice versa
// FIX ME: For clobs, split the string to 4000 chars,
//          convert using TO_CLOB and concatenate before insert
// FIX ME: Ideally generate the $date_columns, and $int_columns
//          using DATA_TYPE from USER_TAB_COLS value

$fn = 'MAILER_ENGIN_DATA_backup.csv';
$csv_fh = fopen($fn, 'r');

$op_fn = 'MAILER_ENGIN_DATA_171209.sql';
$op_fh = fopen($op_fn, 'w');

$file_header = 'SET DEFINE OFF;' . NL
                . 'SET SERVEROUTPUT ON;' . NL
                . 'declare' . NL
                . 'time_str varchar2(20);' . NL
                . 'begin' . NL . NL
                . "select to_char(sysdate, 'DD-MON-YYYY HH24:MI:SS') into time_str from dual;" . NL
                . 'dbms_output.put_line(time_str);' . NL . NL;

fwrite($op_fh, $file_header);

while (($csv_arr = fgetcsv($csv_fh, 1000000, ",")) !== false) {
    if ($row === 1 && COLUMN_FIRST_ROW) {
        $columns = $csv_arr;
        $columns_str = implode(', ', $csv_arr);
        $row++;
        continue;
    }

    $sql_body[] = insert_all_row($csv_arr);

    if ($row % $insert_all_quantity == 0) {
        write_insert_all($sql_body);
        $insert_sql_count++;
        // echo (microtime() - $st) . "  ";
    
        $can_commit = ($insert_sql_count % $commit_point == 0) ? true : false;

        if ($can_commit) {
            write_commit($file_chunk);
            echo "Commit : " . (microtime(true) - $st) . NL;
            $can_commit = false;
        }
    }
    
    $row++;
}

if (count($sql_body) > 0) {
    write_insert_all($sql_body);
    write_commit();
}

$file_footer = "select to_char(sysdate, 'DD-MON-YYYY HH24:MI:SS') into time_str from dual;" . NL
    . 'dbms_output.put_line(time_str);' . NL . NL
    . 'end;' . NL . '/' . NL;
fwrite($op_fh, $file_footer);
fclose($op_fh);
echo NL . "Rows: $row" . NL;

function insert_all_row($csv_arr) {
    global $date_columns, $int_columns, $table_name, $columns_str, $date_format;

    $values_arr = array();
    
    foreach ($csv_arr as $k => $v) {
        if(in_array($k, $int_columns)) {
            $values_arr[] = "$v";
            continue;
        }
        
        if(in_array($k, $date_columns)) {
            $values_arr[] = "to_date('$v', '$date_format')";
            continue;
        }

        # Default handling
        $values_arr[] = ($v == '') ? "''" : "q'[$v]'";
    }
    
    $values_str = implode(', ', $values_arr);
    return "into $table_name ($columns_str) values ($values_str)";
}

function write_insert_all(&$sql_body) {
    global $op_fh;
    
    $final_sql = "insert all" . NL;
    $final_sql .= implode(NL, $sql_body);
    $final_sql .= NL . "select * from dual;" . NL;

    fwrite($op_fh, $final_sql);
    $sql_body = array();
}

function write_commit() {
    global $op_fh;
    fwrite($op_fh, "commit;" . NL . NL);
}
