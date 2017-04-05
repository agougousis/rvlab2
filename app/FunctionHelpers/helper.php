<?php

use Illuminate\Http\Request;

/**
* Checks if the request comes from the mobile version of R vLab
*
* @return boolean
*/
function is_mobile()
{
    if (request()->hasHeader('AAAA1')) {
        return true;
    } else {
        return false;
    }
}

/**
 * Cleans a CSV column name.
 *
 * It removes from column name the new line character and quotes, replaces
 * any character that is not alphanumeric (or underscore) with dot, trims any
 * leading or trailing space and if the remaining string is comprised only by
 * digits it adds an 'X' at the front.
 * an 'X' character
 *
 * @param string $header_value
 * @return string
 */
function clean_csv_header($header_value)
{
    $header_value = trim(preg_replace('/\r\n|\r|\n/', '', $header_value));
    $header_value = trim(preg_replace('/\"/', '', $header_value));
    $header_value = trim(preg_replace('/[^A-Za-z0-9\_]/', '.', $header_value));

    // If first character is number, put an "X" in front of everything
    if (is_numeric(substr($header_value, 0, 1))) {
        $header_value = "X" . $header_value;
    }

    return $header_value;
}

/**
 * Filter an associative array keeping only the entries with specific keys
 *
 * @param Illuminate\Http\Request $request
 * @param array $keys
 * @return array
 */
function filter_request(Illuminate\Http\Request $request, array $keys)
{
    $request_data = [];

    foreach ($keys as $key) {
        if (!empty($request->$key)) {
            $request_data[$key] =  $request->$key;
        }
    }

    return $request_data;
}

/**
 * Change the timezone of a datetime string
 *
 * @param string $date_string
 * @param string $timezone
 * @return string
 */
function dateToTimezone($date_string, $timezone)
{
    $mydate = new DateTime($date_string);
    $target_timezone = new DateTimeZone($timezone);
    $mydate->setTimeZone($target_timezone);
    return $mydate->format('d M Y H:i:s');
}

/**
 * Retrieves all the leaf values of an array and stores them in a
 * one-dimensional array
 *
 * @param array $input_array
 * @return array
 */
function flatten($input_array)
{
    $output = array();

    array_walk_recursive($input_array, function ($current) use (&$output) {
        $output[] = $current;
    });

    return $output;
}

/**
 * Cleans a string that is going to be used as a filename
 *
 * It allows only alphanumerical characters and dashes
 *
 * @param string $original_filename
 * @return string
 */
function safe_filename($original_filename)
{
    // Remove any directory paths
    $new_filename = basename($original_filename);

    // Lower case everything
    // $string = strtolower($string); Is this necessery?
    // Make alphanumeric (removes all other characters)
    $new_filename = preg_replace("/[^\pL\pN\s.\(\)_-]/u", '', $original_filename);

    // Clean up multiple dashes or whitespaces
    $new_filename = preg_replace("/[\s-]+/", " ", $new_filename);

    // Convert whitespaces and underscore to dash
    $new_filename = preg_replace("/[\s_]/", "-", $new_filename);

    return $new_filename;
}

/**
 * Calculate directory size in KB
 *
 * @param string $directory
 * @return int
 */
function directory_size($directory)
{
    exec('du -sh ' . $directory, $output);

    $output_parts = preg_split('/\s+/', $output[0]);
    $size_info = $output_parts[0];
    $metric = substr($size_info, -1);

    switch ($metric) {
        case 'K':
            $multiplier = 1000;
            $number = substr($size_info, 0, -1);
            break;
        case 'M':
            $multiplier = 1000000;
            $number = substr($size_info, 0, -1);
            break;
        case 'G':
            $multiplier = 1000000000;
            $number = substr($size_info, 0, -1);
            break;
        default:
            $multiplier = 1;
            $number = $size_info;
    }

    $total = round($number * $multiplier / 1000);

    return $total;
}

/**
 * Deletes a directory alongside its contents
 *
 * @param string $dir
 * @return boolean
 */
function delTree($dir)
{
    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }

    return rmdir($dir);
}
