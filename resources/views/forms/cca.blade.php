<?php $function = "cca"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'cca_form','style'=>'display:none')) !!}

    {!! form_function_about('cca',$tooltips) !!}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('cca-box','Select community data file from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('cca-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) !!}
    {!! form_checkbox('cca-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    {!! form_radio_files('cca-box2','Select factor file',$tooltips,$workspace_files) !!}

    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>

    <div class='radio_wrapper'>
        <div class='configuration-label'>
            <strong>Factor File</strong>
            Numerous factors can be used to carry out Canonical Correspondence Analysis.
        </div>
    </div>

    {!! form_dropdown('cca-Factor_select1','Select Column in Factor File (Factor1)',array(),'',$tooltips) !!}
    {!! form_dropdown('cca-Factor_select2','Select Column in Factor File (Factor2)',array(),'',$tooltips) !!}
    {!! form_dropdown('cca-Factor_select3','Select Column in Factor File (Factor3)',array(),'',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#cca_form input[name="box2"]', function(){
        var selectedValue = $("#cca_form input[name='box2']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"cca_form","Factor_select1");
            loadCsvHeaders2(fileHeaders,"cca_form","Factor_select2");
            loadCsvHeaders2(fileHeaders,"cca_form","Factor_select3");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });


</script>