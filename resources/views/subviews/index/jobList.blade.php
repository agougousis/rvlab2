<div style="font-weight: bold; margin-bottom: 5px">Recent Jobs:</div>
<div id="recent-jobs-wrapper">
    <table class="table table-bordered table-condensed">
        <thead>
            <th>Job ID</th>
            <th>Function</th>
            <th>Status</th>
            <th class='job_date_column'>Submitted At <span class="glyphicon glyphicon-retweet change_column_icon" aria-hidden="true" title='change to job size column'></span></th>
            <th class='job_size_column' style='display: none'>Folder Size <span class="glyphicon glyphicon-retweet change_column_icon" aria-hidden="true" title='change to job date column'></span></th>
            <th></th>
        </thead>
        <tbody>
            @if(empty($job_list))
            <tr>
                <td colspan="4">No job submitted recently</td>
            </tr>
            @endif
            @foreach($job_list as $job)
            <tr>
                <td>{{ link_to('job/'.$job->id,'Job'.$job->id) }}</td>
                <td>{{ $job->function }}</td>
                <td style="text-align: center">
                    <?php
                        switch($job->status){
                            case 'creating':
                                echo "<div class='job_status status_creating'>Creating...</div>";
                                break;
                            case 'submitted':
                                echo "<div class='job_status status_submitted'>Submitted</div>";
                                break;
                            case 'queued':
                                echo "<div class='job_status status_queued'>Queued</div>";
                                break;
                            case 'running':
                                echo "<div class='job_status status_queued'>Running</div>";
                                break;
                            case 'completed':
                                echo "<div class='job_status status_completed'>Completed</div>";
                                break;
                            case 'failed':
                                echo "<div class='job_status status_failed'>Failed</div>";
                                break;
                        }
                    ?>
                </td>
                <td class='job_date_column'>{{ dateToTimezone($job->submitted_at, $timezone) }}</td>
                <td class='job_size_column' style='display: none'>
                    @if($job->jobsize > 1000000)
                        {{ number_format($job->jobsize,2) }} GB
                    @elseif($job->jobsize > 1000)
                        {{ number_format($job->jobsize,2) }} MB
                    @else
                        {{ number_format($job->jobsize,2) }} KB
                    @endif
                </td>
                <td style="min-width: 20px">
                     @if(in_array($job->status, ['completed', 'failed', 'creating']))
                        <input type="checkbox" id="job_checkbox_{{ $job->id }}" class="job_checkbox" name="jobs_to_delete[]" value="{{ $job->id }}">
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="text-align: right">
        <button type="submit" onclick="delete_jobs()" style="float: right; margin:0px 0px 10px 0px">Delete selected jobs <span class="glyphicon glyphicon-remove delete_icon"></span></button>
    </div>
</div>
{{ Form::open(array('url'=>'job/delete_many','name'=>'jobs_deletion_form')) }}
    <input id="jobIdList" type="hidden" name="jobs_for_deletion" value="">
{{ Form::close() }}