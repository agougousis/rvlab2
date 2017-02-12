<fieldset class="myversion">
    <legend class="myversion">Input Files</legend>
    <br>
    <ul style="list-style-type: none">
        {{ Form::open(array('url'=>'workspace/remove_files')) }} 
        @foreach($workspace_files as $file)
            <li class="workspace-input-file">            
                <div style="display: inline">
                    <span class="glyphicon glyphicon-file"></span> 
                    <a href="{{ url('workspace/get/'.$file->filename) }}">{{ $file->filename }}</a>
                </div>
                <input type="checkbox" name="files_to_delete[]" value="i-checkbox-{{ $file->id }}" style="float: right; margin-right: 30px">
            </li> 
        @endforeach   
        <button type="submit" style="float: right; margin:10px 30px 10px 0px">Delete selected files <span class="glyphicon glyphicon-remove delete_icon"></span></button>
        <div style="clear: both"></div>
        {{ Form::close() }}
    </ul>
    
</fieldset>

<fieldset class="myversion">
    <legend class="myversion">Upload new input files</legend>
    <br>
    {{ Form::open(array('url'=>'workspace/add_files','class'=>'form-horizontal','enctype'=>'multipart/form-data')) }}
    <div class="row">
        <div class="col-sm-3">
            <span class="btn btn-default btn-file">
                Select file(s)... <input type="file" id="local_files" name="local_files[]" multiple="">
            </span>    
        </div>
        <div class="col-sm-9">
            <ul id="local_file_list" style="border: 1px solid gray; min-height: 70px; padding: 5px"></ul> 
        </div>        
    </div>
    <div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-9" style="text-align: right">
            <button class="btn btn-default btn-primary">Add Files</button>
        </div>
    </div>
    @foreach ($errors->all() as $error)
        <div class='alert alert-danger'>{{ $error }}</div>
    @endforeach

    {{ Form::close() }}
</fieldset>    

<div id="utilization-widget" style="display: none">
    <br>
    <div style="text-decoration: underline; margin-bottom: 10px; float: left">User's Storage Utilization:</div>
    <div style='color:gray; margin-left: 20px; float: left' id="util-size-text"></div>
    <div style='clear: both'></div>               
    <div id="progressBarWrapper" class="progress" style='background-color: white; margin-top: 10px'></div> 
</div>

<div class="btn btn-sm btn-default" onclick="checkUtilization()" id="checkUtilizationButton">Check your Storage Utilization</div>
    
<script type="text/javascript">            

    function checkUtilization(){
        $('#loading-image').center().show();
        $.ajax({
            url: "{{ url('workspace/user_storage_utilization') }}",
            type: 'GET',
            dataType: 'json',
            success: function( data ) {    
                var storage_utilization = data.storage_utilization;
                var totalsize = data.totalsize;
                
                var progress = storage_utilization.toFixed(1); 
                if(totalsize > 1000000){
                    size_text = (totalsize/1000000).toFixed(2)+" GB";
                } else if(totalsize > 1000) {
                    size_text = (totalsize/1000,2).toFixed(2)+" MB";
                } else {
                    size_text = (totalsize,2).toFixed(2)+" KB";
                }
                
                $('#util-size-text').val(size_text);
                if(progress <= 100){
                    progressHtml = "<div class='progress-bar' role='progressbar' aria-valuenow='"+progress+"' aria-valuemin='0' aria-valuemax='100' style='min-width: 2.5em; width: "+progress+"%'>"+progress+"%</div>";
                } else {
                    progressHtml = "<div class='red-progress-bar' role='progressbar' aria-valuenow='100' aria-valuemin='0' aria-valuemax='100' style='min-width: 2.5em; width: 100%'>"+progress+"%</div>";
                }
                $('#progressBarWrapper').html(progressHtml);
                
                $('#loading-image').hide();
                $('#utilization-widget').show();
                $('#checkUtilizationButton').hide();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#loading-image').hide();
                alert('Loading utilization information failed!!');                        
            }
        });
    }

    $("#local_files").change(function(){
        ul = $('#local_file_list');
        ul.empty();

        for(var i=0; i< this.files.length; i++){
           var file = this.files[i];
           name = file.name.toLowerCase();
           size = file.size;
           type = file.type;
           ul.append("<li><span class='glyphicon glyphicon-file'></span> "+name+"</li>");
        }
     });
    
    $('.workspace-input-file input[type="checkbox"]').mouseover(function(){
        $(this).parent().find("div").css('background-color','#DFE0E6');
    });
    
    $('.workspace-input-file input[type="checkbox"]').mouseout(function(){
        $(this).parent().find("div").css('background-color','');
    });
    
</script>
        
       