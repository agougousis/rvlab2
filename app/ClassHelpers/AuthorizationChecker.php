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
     * @return Job
     * @throws AuthorizationException
     */
    public static function jobBelongsToUser($job_id, $user_email)
    {
        $job = DB::table('jobs')
            ->where('id', $job_id)
            ->where('user_email', $user_email)
            ->first();

        if (empty($job)) {
            $exception = new AuthorizationException('Job ID '.$job_id.' does not belong to user '.$user_email);
            $exception->setUserMessage('Unauthorized action!');
            throw $exception;
        }
    }

    /**
     * Checks that some jobs belong to the specified user
     *
     * @param array $job_records  Array of App\Models\Job
     * @param string $user_email
     * @param string $context A string describing the context under which the method is called
     * @throws AuthorizationException
     */
    public static function jobsBelongToUser($job_records, $user_email, $context = null)
    {
        foreach ($job_records as $job) {
            if ($job->user_email != $user_email) {
                $exception = new AuthorizationException("Job ID $job_id does not belong to user $user_email . Context: $context");
                $exception->setUserMessage('Unauthorized action!');
                throw $exception;
            }
        }
    }
}