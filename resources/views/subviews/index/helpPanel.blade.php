<div class="panel panel-default" style="margin-top: 20px">
    <div class="panel-heading"  id="help-panel-heading">
        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
        <strong>Help</strong>
        <div class="help-glyphicon">
            <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
        </div>
    </div>
    <div class="panel-body" style="display: none; background-color: #F2F3F9" id="help-panel-body">
        <img src='{{ asset('images/goal.png') }}' style='display: inline; width:17px; margin-right: 5px'> {{ link_to('files/R_vlab_about.pdf','About R vLab') }}
        <br><br>
        <img src='{{ asset('images/man.png') }}' style='display: inline; width:17px; margin-right: 5px'> {{ link_to('files/RvLab_manual.pdf','User manual') }}
        <br><br>
        <img src='{{ asset('images/bookq.png') }}' style='display: inline; width:17px; margin-right: 5px'> {{ link_to('help/storage_policy','Storage and Usage Policy') }}
        <br><br>
        <img src='{{ asset('images/video.png') }}' style='display: inline; width:17px; margin-right: 5px'> {{ link_to('help/video','R vLab video presentation') }}
        <br><br>
        {{ Form::open(array('url'=>'workspace/add_example_data','name'=>'addExampleData')) }}
            <img src='{{ asset('images/files.png') }}' style='display: inline; width:17px; margin-right: 5px'>
            <label onclick="javascript:document.addExampleData.submit();" class="linkStyle">Add example data to your workspace</label>
        {{ Form::close() }}
    </div>
</div>
