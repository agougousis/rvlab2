<?php $function = "pca"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'pca_form','style'=>'display:none')) !!}

{!! form_function_about('pca',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('pca-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('pca-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) !!}
    {!! form_checkbox('pca-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    {!! form_radio_files('pca-box2','Select factor file (Optional)',$tooltips,$workspace_files) !!}
    {!! form_dropdown('pca-column_select','Select Column in Factor File:',array(),'',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#pca_form input[name="box2"]', function(){
        var selectedValue = $("#pca_form input[name='box2']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"pca_form","column_select");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });

</script>
