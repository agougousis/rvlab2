<?php

namespace App\ClassHelpers;

use DB;
use App\Exceptions\AuthorizationException;

/**
 * Contains small authorization checks that may be needed by various controller
 * methods.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthorizationChecker
{
    /**
     * Checks if a job with specific jod ID belongs to a certain user
     *
     * @param int $job_id
     * @param string $user_email
     * @throws AuthorizationException
     */
    public static function jobBelongsToUser($job_id, $user_email)
    {
        $result = DB::table('jobs')
        ->where('id', $job_id)
        ->where('user_email', $user_email)
        ->first();

        if (empty($result)) {
            $exception = new AuthorizationException('Job ID '.$job_id.' does not belong to user '.$user_email);
            $exception->setUserMessage('Unauthorized action!');
            throw $exception;
        }
    }
}