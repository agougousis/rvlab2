<?php

namespace App\ClassHelpers;

/**
 * Handles the parsing of a job outout file in order to identify potential
 * execution errors.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobOutputParser
{
    /**
     * An array containing error lines from job output or custom descriptions
     * about errors happened during the parsing procedure.
     *
     * @var array
     */
    private $error_string;

    /**
     * Contains the text output of a job in case no errors where found
     *
     * @var array
     */
    private $output_string;

    /**
     * A temporary storage for text output lines during the parsing procedure
     *
     * @var array
     */
    private $buffer_string;

    public function __construct() {
        $this->error_string = array();
        $this->output_string = array();
    }

    /**
     * Parses a job log file and looks for errors
     *
     * @param string $log_file
     * @return string
     */
    public function parse_log($log_file){
        $log_text = "";
        if(file_exists($log_file)){
            $handle = fopen($log_file, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $log_text .= "<br>".$line;
                }
                fclose($handle);
                return $log_text;
            } else {
                return "";
            }
        } else {
            return "";
        }
    }

    /**
     * Parses a job output file and looks for errors.
     *
     * @param string $filepath
     */
    public function parse_output($filepath){

        $this->error_string = array();
        $this->output_String = array();
        $this->buffer_string = array();

        try {
            $handle = fopen($filepath, "r");
            $found_nothing = true;
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $this->buffer_string[] = $line;
                    if (((strpos($line,'Error') !== false)||(strpos($line,'error') != false))&&(strpos($line,'Initializing database from') == false)) {
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif(strpos($line,'Primary job') !== false){
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif(strpos($line,'unable to launch the specified') !== false){
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif (strpos($line,'summary') !== false){
                        $found_nothing = false;
                        while (($line = fgets($handle)) !== false) {
                            $this->output_string[] = "<br>".$line;
                        }
                    }
                }
                if($found_nothing){
                    $this->output_string = $this->buffer_string;
                }
                fclose($handle);
            } else {
                $this->error_string[] = "Output of R script could not be opened!";
            }
        } catch (\Exception $ex) {
            $this->error_string[] = "Unexpected error happened when parsing the output of R script.";
            $this->error_string[] = "<br>Error message: ".$ex->getMessage();
        }

    }

    /**
     * Checks whether the parser has identified any errors to job execution
     *
     * @return boolean
     */
    public function hasFailed(){
        if(!empty($this->error_string)){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns any error line that has been identified during the parsing
     *
     * @return string
     */
    public function getOutput(){
        if(!empty($this->error_string)){
            return $this->error_string;
        } else {
            return $this->output_string;
        }
    }

}
