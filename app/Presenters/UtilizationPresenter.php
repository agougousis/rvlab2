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
class UtilizationPresenter
{
    /**
     * Absolute total storage amount used by users (in KB)
     *
     * @var int
     */
    private $usedSize;

    /**
     * An array containing the absolute amount of storage that is being used
     * by each user (in KB)
     *
     * @var array
     */
    private $userTotals;

    /**
     * Total storage utilization (100% percentage)
     *
     * @var float
     */
    private $utilization;

    /**
     * Storage quota for users. This is a soft limit that is being enforced only
     * under certain conditions.
     *
     * @var float
     */
    private $userSoftLimit;

    public function __construct($used_size, $user_totals)
    {
        $this->usedSize = $used_size;
        $this->userTotals = $user_totals;
    }

    /**
     * Setter
     *
     * @param float $utilization
     */
    public function setUtilization($utilization)
    {
        $this->utilization = $utilization;
    }

    /**
     * Setter
     *
     * @param int $userSoftLimit
     */
    public function setUserSoftLimit($userSoftLimit)
    {
        $this->userSoftLimit = $userSoftLimit;
    }

    /**
     * If there is no such method return the property itself
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * Return as formatted string the storage amount that is being used by users
     *
     * @return string
     */
    public function getUtilizedText()
    {
        if ($this->usedSize > 1000000) {
            $utilized_text = number_format($this->usedSize / 1000000, 2) . " GB";
        } elseif ($this->usedSize > 1000) {
            $utilized_text = number_format($this->usedSize / 1000, 2) . " MB";
        } else {
            $utilized_text = number_format($this->usedSize, 2) . " KB";
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
        foreach ($this->userTotals as $email => $size_number) {
            $sizeInfo = [];

            $progress = number_format(100 * $size_number / $this->userSoftLimit, 1);
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
