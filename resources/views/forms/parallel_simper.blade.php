<?php $function = "parallel_simper"; ?>

{!! Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_simper_form','style'=>'display:none')) !!}

{!! form_function_about('parallel_simper_form',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('parallel_simper-box','Select community data file from loaded files',$tooltips,$workspace_files) !!}
    {!! form_checkbox('parallel_simper-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    {!! form_radio_files('parallel_simper-box2','Select factor file',$tooltips,$workspace_files) !!}

    <div style="color: blue; font-weight: bold">Parameters</div>

    {!! form_dropdown('parallel_simper-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) !!}
    {!! form_textinput('parallel_simper-column_select','Select Column in Factor File:','1',$tooltips) !!}
    {!! form_textinput('parallel_simper-permutations','Permutations:','999',$tooltips) !!}
    {!! form_dropdown('parallel_simper-trace','trace',array('FALSE','TRUE'),'FALSE',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}