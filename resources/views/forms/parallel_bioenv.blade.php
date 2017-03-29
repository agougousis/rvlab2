<?php $function = "parallel_bioenv"; ?>

{!! Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_bioenv_form','style'=>'display:none')) !!}

{!! form_function_about('parallel_bioenv_form',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('parallel_bioenv-box','Select community data file from loaded files',$tooltips,$workspace_files) !!}
    {!! form_checkbox('parallel_bioenv-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    {!! form_radio_files('parallel_bioenv-box2','Select enviromental variable factor file',$tooltips,$workspace_files) !!}

    <div style="color: blue; font-weight: bold">Parameters</div>

    {!! form_dropdown('parallel_bioenv-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) !!}
    {!! form_dropdown('parallel_bioenv-method_select','Method',array('spearman','pearson','canberra'),'spearman',$tooltips) !!}
    {!! form_dropdown('parallel_bioenv-index_select','Index:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) !!}
    {!! form_textinput('parallel_bioenv-upto','upto','2',$tooltips) !!}
    {!! form_dropdown('parallel_bioenv-trace','trace',array('FALSE','TRUE'),'FALSE',$tooltips) !!}
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}