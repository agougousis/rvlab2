<?php

namespace App\Presenters;

/**
 * Carries and builds numbers and strings (related to user utilization) that
 * will be used in views.
 *
 * This class should contain no business logic. Its methods just reformat the
 * already calculated data/stats.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class StorageUtilization
{
    /**
     * Absolute total storage amount used by users (in KB)
     *
     * @var int
     */
    private $used_size;

    /**
     * An array containing the absolute amount of storage that is being used
     * by each user (in KB)
     *
     * @var array
     */
    private $user_totals;

    /**
     *
     * @var type
     */
    public $rvlab_storage_limit;

    /**
     * Maximum number of users that R vLab supports
     *
     * @var int
     */
    public $max_users_supported;

    /**
     * Total storage utilization (100% percentage)
     *
     * @var float
     */
    public $utilization;

    /**
     * Storage quota for users. This is a soft limit that is being enforced only
     * under certain conditions.
     *
     * @var float
     */
    public $user_soft_limit;

    public function __construct($used_size, $user_totals)
    {
        $this->used_size = $used_size;
        $this->user_totals = $user_totals;
    }

    /**
     * Return as formatted string the storage amount that is being used by users
     *
     * @return string
     */
    public function getUtilizedText()
    {
        if ($this->used_size > 1000000) {
            $utilized_text = number_format($this->used_size / 1000000, 2) . " GB";
        } elseif ($this->used_size > 1000) {
            $utilized_text = number_format($this->used_size / 1000, 2) . " MB";
        } else {
            $utilized_text = number_format($this->used_size, 2) . " KB";
        }

        return $utilized_text;
    }

    /**
     * Returns an array that contains (as formatted strings) the absolute
     * storage amount and the relevant percentage (of its storage quota) that
     * is being used by each user.
     *
     * @return array
     */
    public function getUserTotals()
    {
        $new_user_totals = [];
        foreach ($this->user_totals as $email => $size_number) {
            $sizeInfo = [];

            $progress = number_format(100 * $size_number / $this->user_soft_limit, 1);
            if ($size_number > 1000000) {
                $size_text = number_format($size_number / 1000000, 2) . " GB";
            } elseif ($size_number > 1000) {
                $size_text = number_format($size_number / 1000, 2) . " MB";
            } else {
                $size_text = number_format($size_number, 2) . " KB";
            }

            $sizeInfo['size_number'] = $size_number;
            $sizeInfo['size_text'] = $size_text;
            $sizeInfo['progress'] = $progress;

            $new_user_totals[$email] = $sizeInfo;
        }

        return $new_user_totals;
    }
}
