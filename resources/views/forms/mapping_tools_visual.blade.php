<?php $function = "mapping_tools_visual"; ?>

{!! Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'mapping_tools_visual_form','style'=>'display:none')) !!}

{!! form_function_about('mapping_tools_visual_form',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('mapping_tools_visual-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('mapping_tools_visual-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'none',$tooltips) !!}

    <div style="text-align: right; margin-bottom: 5px">
        <a href="http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/decostand.html" style="color: gray" target="_blank">* info about transformation methods</a>
    </div>

    {!! form_checkbox('mapping_tools_visual-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}

{!! form_radio_files('mapping_tools_visual-box2','Select coordinates file',$tooltips,$workspace_files) !!}
    <br>
<div style="color: blue; font-weight: bold">Parameters</div>

    {!! form_textinput('mapping_tools_visual-top_species','Number of top ranked species','21',$tooltips) !!}

        <div class='radio_wrapper'>
        <div class='configuration-label'>
        </div>
        </div>


    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

