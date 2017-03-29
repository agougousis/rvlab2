<?php $function = "regression"; ?>

{!! Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'regression_form','style'=>'display:none')) !!}

    {!! form_function_about('regression',$tooltips) !!}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('regression-box','Select enviromental factor file data from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('regression-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) !!}
    {!! form_checkbox('regression-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}

    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>

    <div class='radio_wrapper'>
        <div class='configuration-label'>
            <strong>Factor File</strong>
            lm is used to fit linear models. It can be used to carry out regression according to the following formulas:
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="single" checked>
              Single linear regression - fit<-lm(Factor1~Factor2, data)
            </label>
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="multi">
              Multiple linear regression- fit2<-lm(Factor1~Factor2+Factor3, data)
            </label>
        </div>
    </div>

    {!! form_dropdown('regression-Factor_select1','Select Column in Factor File (Factor1)',array(),'',$tooltips) !!}
    {!! form_dropdown('regression-Factor_select2','Select Column in Factor File (Factor2)',array(),'',$tooltips) !!}
    {!! form_dropdown('regression-Factor_select3','Select Column in Factor File <br>(Factor3 - optional for multiple linear regression)',array(),'',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#regression_form input[name="box"]', function(){
        var selectedValue = $("#regression_form input[name='box']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"regression_form","Factor_select1");
            loadCsvHeaders2(fileHeaders,"regression_form","Factor_select2");
            loadCsvHeaders2(fileHeaders,"regression_form","Factor_select3");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });

</script>