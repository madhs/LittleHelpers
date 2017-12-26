<?php


class TextTable {


    public $rownum = false;
    public $header_flag = false;

    protected $header;

    private $rows = array();
    private $min_pad = 2;
    private $maj_pad = 4;

    private $max_rownum_width = 0;
    private $max_col_width = array();
    private $total_width = 0;
    private $final_output = '';

    function __construct() {

        if (!defined('TT_COL_ALIGN_RIGHT')) {
            define('TT_COL_ALIGN_RIGHT', 0);
        }

        if (!defined('TT_COL_ALIGN_LEFT')) {
            define('TT_COL_ALIGN_LEFT', 1);
        }
    }

    function header(array $arr) {
        foreach ($arr as $key => $value) {
            $v = (string) $value;
            if (is_scalar($v)) {
                $this->header[] = $v;
            }
            else {
                echo 'Non scalar value found in header array. Please check the datatype';
            }
        }
        $this->header_flag = true;
    }

    function row(array $arr) {
        $this->rows[] = $arr;
    }

    private function _render_line() {
        static $l = '';
        if (empty($l)) {
            for ($i = 0; $i < $this->total_width; $i++) { 
                $l .= '-';
            }
        }
        return $l;
    }

    private function _render_header() {
        $h_cols = array();
        $partial_pad = $this->maj_pad + $this->min_pad;
        foreach ($this->header as $cn => $h) {
            $total_pad = $partial_pad + $this->max_col_width[$cn] - strlen($h);
            $lpad = ceil($total_pad/2);
            $rpad = $total_pad - $lpad;
            $padded_header = str_pad($h, strlen($h)+$lpad, ' ',STR_PAD_LEFT);
            $h_cols[] = str_pad($padded_header, strlen($padded_header)+$rpad, ' ',STR_PAD_RIGHT);
        }

        $final_header = implode('|', $h_cols);
        $final_header = "|$final_header|" . PHP_EOL;
        
        if ($this->rownum) {
            $empty_space = str_pad('', $this->max_rownum_width+2, ' ', STR_PAD_RIGHT);
            $final_header = "|$empty_space" . "$final_header";
        }

        # Upper border for header
        $final_header .= $this->_render_line();
        return $final_header;
    }

    function output() {
        $max_cols = 0;

        if (count($this->rows) === 0) {
            $this->rows = array(array(''));
        }

        # Calculate maximum column width and choose LEFT or RIGHT align
        foreach ($this->rows as $rn => $row) {
            $max_cols = count($row) > $max_cols ? count($row) : $max_cols;
            foreach ($row as $cn => $col) {
                if(is_scalar($col)) {
                    if (!isset($this->max_col_width[$cn])) {
                        $this->max_col_width[$cn] = 0;
                    }
                    $this->max_col_width[$cn] = (strlen($col) > $this->max_col_width[$cn]) ? strlen($col) : $this->max_col_width[$cn];

                    if (is_numeric($col)) {
                        if (!isset($align_col[$cn])) {
                            $align_col[$cn] = TT_COL_ALIGN_RIGHT;
                        }
                    }
                    else {
                        if (!isset($align_col[$cn])) {
                            $align_col[$cn] = TT_COL_ALIGN_LEFT;
                        }
                        elseif ($align_col[$cn] == TT_COL_ALIGN_RIGHT) {
                            $align_col[$cn] = TT_COL_ALIGN_LEFT;
                        }
                    }
                }
                else {
                    echo "Row number " . $rn+1 . ", column " . $cn+1 . " MUST be a string";
                    var_dump($col);
                }
            }
        }

        # Consider header to calc. $max_cols and max. col. width
        if ($this->header_flag) {
            $max_cols = count($this->header) > $max_cols ? count($this->header) : $max_cols;
            foreach ($this->header as $key => $header) {
                if (is_string($header)) {
                    if (isset($this->max_col_width[$key])) {
                        $this->max_col_width[$key] = strlen($header) > $this->max_col_width[$key] ? strlen($header) : $this->max_col_width[$key];
                    }
                    else {
                        $this->max_col_width[$key] = strlen($header);
                    }
                }
            }
        }

        # Fix empty header values
        if (count($this->header) < $max_cols) {
            $fill = $max_cols - count($this->header);
            for ($i = 0; $i < $fill; $i++) { 
                $this->header[] = '';
            }
        }

        # Fix empty row/col values
        foreach ($this->rows as $rn => &$row) {
            if (count($row) < $max_cols) {
                $fill = $max_cols - count($row);
                for ($i = 0; $i < $fill; $i++) { 
                    $row[] = '';
                }
            }
        }

        $op_rows = array();
        # Prepare to stuff column data with spaces to fit into max. col width, and then pad & align it to beautify
        foreach ($this->rows as $key => $row_val) {
            $op_cols = array();
            foreach ($row_val as $c => $col) {
                # Add stuffing, and then
                # Add beautification padding
                if (isset($align_col[$c]) && $align_col[$c] == TT_COL_ALIGN_LEFT) {
                    $stuffed = str_pad($col, $this->max_col_width[$c], ' ', STR_PAD_RIGHT);
                    $padded = str_pad($stuffed, strlen($stuffed)+$this->maj_pad, ' ', STR_PAD_RIGHT);
                    $padded = str_pad($padded, strlen($padded)+$this->min_pad, ' ', STR_PAD_LEFT);
                }
                else {
                    $stuffed = str_pad($col, $this->max_col_width[$c], ' ', STR_PAD_LEFT);
                    $padded = str_pad($stuffed, strlen($stuffed)+$this->maj_pad, ' ', STR_PAD_LEFT);
                    $padded = str_pad($padded, strlen($padded)+$this->min_pad, ' ', STR_PAD_RIGHT);
                }
                $op_cols[] = $padded;
            }
            $op_rows[] = implode('|', $op_cols);
        }

        # Prepare final output array
        $this->total_width = strlen("|{$op_rows[0]}|"); // All rows must be of the same size.
        if ($this->rownum) {
            # Check if rownum needs to be added
            $this->max_rownum_width = strlen(count($this->rows));
            $this->total_width += $this->max_rownum_width + 3; // +2 is for 1 blank space of either side of the number and a seperator between 1st column and rownum
        }

        # Upper border
        $this->final_output = $this->_render_line() . PHP_EOL;
        
        # Optional header
        if ($this->header_flag) {
            $this->final_output .= $this->_render_header() . PHP_EOL;
        }

        # Table data
        foreach ($op_rows as $r => $row) {
            if ($this->rownum) {
                $rnum = $r + 1;
                $rnum = str_pad($rnum, $this->max_rownum_width, ' ', STR_PAD_RIGHT);
                $this->final_output .= '| ' . $rnum . ' ';
            }
            $this->final_output .= "|$row|" . PHP_EOL;
        }

        # Bottom border
        $this->final_output .= $this->_render_line() . PHP_EOL;
        echo $this->final_output;
    }
}


$tab = new TextTable;
$tab->rownum = true;
$tab->row(array('London', 'United Kingdom', mt_rand(100, 999), 'Europe'));
$tab->row(array('Kuala Lumpur','Malaysia', mt_rand(100, 999), 'Asia'));
$tab->row(array('Singapore','Singapore', mt_rand(100, 999), 'Asia'));
$tab->row(array('Capetown','South Africa' , mt_rand(100, 999), 'Africa'));
$tab->row(array('Sydney','Australia' , mt_rand(100, 999), 'Australia'));
$tab->row(array('New Delhi','India' , mt_rand(100, 999), 'Asia'));
$tab->row(array('Lahore', 'Pakistan' , mt_rand(100, 999), 'Asia'));
$tab->row(array('Dhaka','Bangladesh' , mt_rand(100, 999), 'Asia'));
$tab->row(array('Rio De Janeiro','Brazil' , mt_rand(100, 999), 'South America'));
$tab->row(array('Toronto','Canada' , mt_rand(100, 999), 'North America'));
$tab->header(array('City', 'Country', 'Some Count', 'Continent'));
$tab->output();
