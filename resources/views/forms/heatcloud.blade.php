<?php $function = "heatcloud"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'heatcloud_form','style'=>'display:none')) !!}

{!! form_function_about('heatcloud',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('heatcloud-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('heatcloud-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'none',$tooltips) !!}

    <div style="text-align: right; margin-bottom: 5px">
        <a href="http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/decostand.html" style="color: gray" target="_blank">* info about transformation methods</a>
    </div>

    {!! form_checkbox('heatcloud-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>

    {!! form_textinput('heatcloud-top_species','Number of top ranked species','21',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}