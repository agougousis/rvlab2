<div class="top-label">
    Job{{ $job->id }} Information/Results <span style="color: #ef7c61; margin-left: 20px; font-weight: normal">({{ $function }})</span>
    <a href="{{ url('/') }}" style="float:right; margin-right: 10px; font-size: 19px" title="Home Page"><span class="glyphicon glyphicon-home" aria-hidden="true"></span></a>
</div>

<div style='padding: 20px'>
This job has not been executed yet. So, there are no results available for the moment.
</div>

<script type='text/javascript'>

(function job_refresher() {
    $.ajax({
        url: '{{ url("get_job_status/".$job->id) }}', 
        type: "GET",
        dataType : 'json',
        success: function(data) {
            
            if((data.status == 'completed')||(data.status == 'failed')){
                location = location;
            }
            
        },
        complete: function() {
            // Schedule the next request when the current one's complete
            setTimeout(job_refresher, {{ $refresh_rate }});
        },
        error: function(jqXHR, textStatus, errorThrown) 
        {
            toastr.error("Job status could not be refreshed automatically.");
        }
    });
})();

</script>