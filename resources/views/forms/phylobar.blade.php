<?php $function = "phylobar"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'phylobar_form','style'=>'display:none')) !!}

{!! form_function_about('phylobar',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('phylobar-box','Select newick tree format file',$tooltips,$workspace_files) !!}
    {!! form_radio_files('phylobar-box2','Select annotation file',$tooltips,$workspace_files) !!}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>

    {!! form_textinput('phylobar-top_nodes','Number of nodes','21',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}