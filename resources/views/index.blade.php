<script type='text/javascript'>

    function delete_job(jobId){
        $( "#deleteJob"+jobId+"Form" ).submit();
    }

</script>

@if(!empty($deletion_info))
    @include("subviews.index.jobDeletionMessage")
@endif

<div class="row">

    @if($is_admin)
        @include("admin.bar")
    @endif

    <div style="text-align: right; margin-right: 20px">
        <a href="https://play.google.com/store/apps/details?id=com.Hcmr.LifeWatch" class="btn btn-xs btn-success">Download the mobile app from Google Play</a>
    </div>

    <div class="col-sm-6">
        @include("subviews.index.workspacePanel")

        @include("subviews.index.jobList")
    </div>

    <div class="col-sm-6">
        @include("subviews.index.helpPanel")

        @include("subviews.index.jobSubmissionPanel")

        @include("js.per_page.index")
    </div>

    <div style="margin: 20px; font-size: 13px">
        <span style="color: gray">If you use RvLab in a publication, please cite:</span>
        "Varsos C, Patkos T, Oulas A, Pavloudi C, Gougousis A, Ijaz U, Filiopoulou I, Pattakos N,
        Vanden Berghe E, Fernández-Guerra A, Faulwetter S, Chatzinikolaou E, Pafilis E, Bekiari C,
        Doerr M, Arvanitidis C (2016) Optimized R functions for analysis of ecological community
        data using the R virtual laboratory (RvLab). Biodiversity Data Journal 4: e8357."
        <a href="https://doi.org/10.3897/BDJ.4.e8357">https://doi.org/10.3897/BDJ.4.e8357</a>
    </div>


</div>




