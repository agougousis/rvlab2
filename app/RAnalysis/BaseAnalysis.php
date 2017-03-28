<?php

namespace App\RAnalysis;

/**
 * Description of BaseAnalysis
 *
 * @author Alexandros
 */
class BaseAnalysis {

    /**
     * The submitted form
     *
     * @var array
     */
    private $form;

    /**
     * The job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * The local path to the job folder
     *
     * @var string
     */
    private $job_folder;

    /**
     * The remote path (in cluster's filesystem) to the job folder
     *
     * @var string
     */
    private $remote_job_folder;

    /**
     * The local path to the user's workspace
     *
     * @var string
     */
    private $user_workspace;

    /**
     * The remote path (in cluster's filesystem) to the user's workspace
     *
     * @var string
     */
    private $remote_user_workspace;

    /**
     * A list of all the selected filenames to be used as input
     * separated by semicolons
     *
     * @var string
     */
    private $inputs;

    /**
     * A list of all the input parameters. Each parameters is expressed as a
     * key-value pair separated from tge others by a semicolon. Keys are
     * separated from values by a colon character.
     *
     * @var string
     */
    private $params;

    public function __construct($form, $job_id, $job_folder, $remote_job_folder, $user_workspace, $remote_user_workspace, &$inputs, &$params)
    {
        $this->form = $form;
        $this->job_id = $job_id;
        $this->job_folder = $job_folder;
        $this->remote_job_folder = $remote_job_folder;
        $this->user_workspace = $user_workspace;
        $this->remote_user_workspace = $remote_user_workspace;
        $this->params = $params;

        if(is_array($form['box'])) {
            $this->inputs = implode(';',$form['box']);
        } else {
            $this->inputs = $form['box'];
        }
    }
}
