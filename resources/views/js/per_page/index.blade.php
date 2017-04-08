<script type="text/javascript">

    // The headers of the first file can be used by every form to initialize the dropdown
    // fields that are related to CSV headers. It is not necessery for each form to make
    // a separate request in order to get these headers. The forms should be loaded before
    // we try to access their fields.
    //
    // In case there are not files in user's workspace, there is no meaning loading headers
    @if ($count_workspace_files > 0)
        var firstFileHeaders = getCsvHeaders($("#taxa2dist_form input[name='box']").first().val());

        // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
        loadCsvHeaders2(firstFileHeaders,"anova_form","Factor_select1");
        loadCsvHeaders2(firstFileHeaders,"anova_form","Factor_select2");
        loadCsvHeaders2(firstFileHeaders,"anova_form","Factor_select3");
        loadCsvHeaders2(firstFileHeaders,"anosim_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"cca_form","Factor_select1");
        loadCsvHeaders2(firstFileHeaders,"cca_form","Factor_select2");
        loadCsvHeaders2(firstFileHeaders,"cca_form","Factor_select3");
        loadCsvHeaders2(firstFileHeaders,"cca_visual_form","Factor_select1");
        loadCsvHeaders2(firstFileHeaders,"cca_visual_form","Factor_select2");
        loadCsvHeaders2(firstFileHeaders,"cca_visual_form","Factor_select3");
        loadCsvHeaders2(firstFileHeaders,"hclust_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"mapping_tools_div_visual_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"metamds_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"pca_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"permanova_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"permanova_form","column_select2");
        loadCsvHeaders2(firstFileHeaders,"regression_form","Factor_select1");
        loadCsvHeaders2(firstFileHeaders,"regression_form","Factor_select2");
        loadCsvHeaders2(firstFileHeaders,"regression_form","Factor_select3");
        loadCsvHeaders2(firstFileHeaders,"simper_form","column_select");
        loadCsvHeaders2(firstFileHeaders,"taxondive_form","column_select");
        loadInitialHeaders(firstFileHeaders); // This function is defined in /views/forms/convert2r.blade.php

    @endif

    function show_more(functionName){
        var aboutTeaserId = functionName+'-about-teaser';
        var aboutAllId = functionName+'-about-all';
        $('#'+aboutTeaserId).hide();
        $('#'+aboutAllId).show();
    }

    $('.change_column_icon').on('click',function(){
        if($('th.job_size_column').is(":hidden")){
            $(".job_date_column").hide();
            $(".job_size_column").show();
        } else {
            $(".job_size_column").hide();
            $(".job_date_column").show();
        }
    });

    // we need this to preserve the checked checkboxes during job list refreshing
    var jobs_to_delete = new Array();

    function delete_jobs(){
        var selected_jobs = "";
        var counter = 0;

        // Get all selected jobs for deletion
        $(".job_checkbox").each(function(index1,value1){
           if($(this).is(":checked")){
                selected_jobs = selected_jobs+";"+$(this).val();
                counter++;
           }
        });

        // Check if at least one was selected
        if(counter > 0){
            // trim initial ';'
            selected_jobs = selected_jobs.substr(1);
            $('#jobIdList').val(selected_jobs);
            document.jobs_deletion_form.submit();
        }
    }

    $(document).ready(function(){
        // when a new job is selected/unselected for deletion
        // (for the list we use a variable that is not affected by ajax refreshing)
         $("#jobListTable").on('click','.job_checkbox',function(){
             // get job id
             var checkboxId = $(this).attr('id');
             if($(this).is(":checked")){
                 // add the job id to the list
                 jobs_to_delete.push(checkboxId);
             } else {
                 // remove the job id from the list
                 jobIndex = jobs_to_delete.indexOf(checkboxId);
                 if (jobIndex > -1) {
                    jobs_to_delete.splice(jobIndex, 1);
                }
             }
        });

        // Enable the bootstrap popovers
        $('[data-toggle="popover"]').popover();

        // Set the displayed form to the one that was used last
        var default_function = 'taxa2dist';
        var default_form_name = default_function+"_form";
        $("#"+default_form_name).hide();
        var new_form_name = selected_function+"_form";
        $("#"+new_form_name).show();
        $("#selected_function").val(selected_function);
        $('#documentation_icon').attr('title',selected_function+' documentation');
        $('#documentation_link').attr('href',documentation_links[selected_function]);

    });

    var documentation_links = {
        'taxa2dist' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/taxondive.html',
        'taxondive' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/taxondive.html',
        'vegdist'   : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/vegdist.html',
        'hclust'    : 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/hclust.html',
        'bict'      : 'http://marine.lifewatch.eu/vibrant-bict',
        'pca'       : 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/princomp.html',
        'cca'       : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/cca.html',
        'anosim'    : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/anosim.html',
        'anova'     : 'https://stat.ethz.ch/R-manual/R-devel/library/stats/html/aov.html',
        'permanova' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/adonis.html',
        'mantel'    : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/mantel.html',
        'metamds'   : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/metaMDS.html',
        'second_metamds' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/metaMDS.html',
        'metamds_visual' : 'http://userweb.eng.gla.ac.uk/umer.ijaz/bioinformatics/summarize_v0.2/summarize.html',
        'cca_visual' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/cca.html',
        'mapping_tools_visual': '',
        'mapping_tools_div_visual': '',
        'radfit'    : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/radfit.html',
        'bioenv'    : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/bioenv.html',
        'simper'    : 'http://finzi.psych.upenn.edu/library/vegan/html/simper.html',
        'regression': 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/lm.html',
        'parallel_taxa2dist': "{{ url('help/documentation/parallel_taxa2dist') }}",
        'parallel_anosim'   : "http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/anosim.html",
        'parallel_mantel'   : "http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/mantel.html",
        'parallel_taxa2taxon' : "{{ url('help/documentation/parallel_taxa2taxon') }}",
        'parallel_permanova' : 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/adonis.html',
        'parallel_bioenv': '',
        'parallel_simper': '',
        'convert2r' : "http://exposurescience.org/heR.doc/library/reshape/html/cast-9g.html"
    };

    (function job_refresher() {
        $.ajax({
            url: '{{ url("get_user_jobs") }}',
            type: "GET",
            dataType : 'json',
            success: function(data) {
                $('#recent-jobs-wrapper table tbody').empty();
                var tableString = '';
                var jobList = data;
                if(jobList.length == 0){
                    tableString = "<tr><td colspan='4'>No job submitted recently</td></tr>";
                }
                for(var i = 0; i < jobList.length; i++) {
                    var job = jobList[i];
                    tableString = tableString+"<tr>";
                    tableString = tableString+"<td><a href='"+"{{ url('job') }}/"+job.id+"'>Job"+job.id+"</a></td>";
                    tableString = tableString+"<td>"+job.function+"</td>";
                    tableString = tableString+"<td style='text-align:center'>";
                    switch(job.status){
                        case 'creating':
                            tableString = tableString+"<div class='job_status status_creating'>Creating...</div>";
                            break;
                        case 'submitted':
                            tableString = tableString+"<div class='job_status status_submitted'>Submitted</div>";
                            break;
                        case 'queued':
                            tableString = tableString+"<div class='job_status status_queued'>Queued</div>";
                            break;
                        case 'running':
                            tableString = tableString+"<div class='job_status status_queued'>Running</div>";
                            break;
                        case 'completed':
                            tableString = tableString+"<div class='job_status status_completed'>Completed</div>";
                            break;
                        case 'failed':
                            tableString = tableString+"<div class='job_status status_failed'>Failed</div>";
                            break;
                    }
                    tableString = tableString+"</td>";
                    if($("th.job_date_column").is(":hidden")){
                        tableString = tableString+"<td class='job_date_column' style='display:none'>"+job.submitted_at+"</td>";
                    } else {
                        tableString = tableString+"<td class='job_date_column'>"+job.submitted_at+"</td>";
                    }
                    if($("th.job_size_column").is(":hidden")){
                        tableString = tableString+"<td class='job_size_column' style='display:none'>";
                    } else {
                        tableString = tableString+"<td class='job_size_column'>";
                    }

                    if(job.jobsize > 1000000){
                        jobsizeText = (job.jobsize/1000000).toFixed(2) +" GB";
                    } else if(job.jobsize > 1000) {
                        jobsizeText = (job.jobsize/1000).toFixed(1) +" MB";
                    } else {
                        jobsizeText = job.jobsize+" KB";
                    }
                    tableString = tableString+jobsizeText+"</td>";
                    tableString = tableString+"<td>";
                    if((job.status == 'creating')||(job.status == 'completed')||(job.status == 'failed')){
                            action = "{{ url('job/delete') }}";
                            tableString = tableString+"<input type='checkbox' id='job_checkbox_"+job.id+"' class='job_checkbox' name='jobs_to_delete' value='"+job.id+"'>";
                    }
                    tableString = tableString+"</td>";
                    tableString = tableString+"</tr>";
                }
                $('#recent-jobs-wrapper table tbody').html(tableString);

                // Schedule the next request when the current one's complete
                setTimeout(job_refresher, {{ $refresh_rate }});
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if(jqXHR.status == 401){
                    toastr.error("You are probably not logged in or your session has expired!");
                } else {
                    toastr.error("Job status could not be refreshed automatically.");
                }
            }
        });
    })();

    $("#selected_function").change(function(){
       var old_function = selected_function;
       // Hide the previous form
       var old_form_name = old_function+"_form";
       $("#"+old_form_name).hide();
       // Show the new form
       selected_function = $(this).val();
       var form_name = selected_function+"_form";
       $("#"+form_name).show();

       //
       $('#documentation_icon').attr('title',selected_function+' documentation');
       $('#documentation_link').attr('href',documentation_links[selected_function]);

    });

    $("input[type='file']").change(function(){
        ul = $(this).parent().parent().parent().find('ul');
        ul.empty();

        for(var i=0; i< this.files.length; i++){
           var file = this.files[i];
           name = file.name.toLowerCase();
           size = file.size;
           type = file.type;
           ul.append("<li><span class='glyphicon glyphicon-file'></span> "+name+"</li>");
        }
     });

     $('.workspace-glyphicon').click(function(){
        $('#workspace-panel-body').slideToggle('slow',function(){

            // Determine the current tab status
            var status = "";
            if($('#workspace-panel-body').is(":hidden")){
                status = "closed";
            } else {
                status = "open";
            }

            // Store the tab status in session
            var postData = {
                new_status: status
            };

            $.ajax(
            {
                url : "{{ url('workspace/tab_status') }}",
                type: "POST",
                data : postData,
                success:function(data, textStatus, jqXHR)
                {

                },
                error: function(jqXHR, textStatus, errorThrown)
                {

                }
            });
        });
        $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
     });

     $('.help-glyphicon').click(function(){
        $('#help-panel-body').slideToggle();
        $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
     });

</script>
