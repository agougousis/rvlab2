<div class="panel panel-default" style="margin-top: 20px">
    <div class="panel-heading" id="workspace-panel-heading">
        <span class="glyphicon glyphicon-file" aria-hidden="true"></span>
        <strong>Workspace File Management</strong>
        <div class="workspace-glyphicon">
            <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
        </div>
    </div>
    @if($workspace_tab_status == 'closed')
        <div class="panel-body" style="display: none; background-color: #F2F3F9" id="workspace-panel-body">
            {!! $workspace !!}
        </div>
    @else
        <div class="panel-body" style="background-color: #F2F3F9" id="workspace-panel-body">
            {!! $workspace !!}
        </div>
    @endif
</div>