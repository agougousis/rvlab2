<?php

namespace App\Exceptions;

if (!function_exists('App\Exceptions\is_mobile')) {
    function is_mobile()
    {
        return true;
    }
}
