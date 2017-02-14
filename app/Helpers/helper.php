<?php

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
 * @param string $string
 * @return string
 */
function safe_filename($string)
{
    // Lower case everything
    // $string = strtolower($string); Is this necessery?
    // Make alphanumeric (removes all other characters)
    $string = preg_replace("/[^\pL\pN\s.\(\)_-]/u", '', $string);

    // Clean up multiple dashes or whitespaces
    $string = preg_replace("/[\s-]+/", " ", $string);

    // Convert whitespaces and underscore to dash
    $string = preg_replace("/[\s_]/", "-", $string);

    return $string;
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
