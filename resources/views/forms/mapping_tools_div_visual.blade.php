<?php $function = "mapping_tools_div_visual"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'mapping_tools_div_visual_form','style'=>'display:none')) !!}

{!! form_function_about('mapping_tools_div_visual_form',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('mapping_tools_div_visual-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('mapping_tools_div_visual-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'none',$tooltips) !!}

    <div style="text-align: right; margin-bottom: 5px">
        <a href="http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/decostand.html" style="color: gray" target="_blank">* info about transformation methods</a>
    </div>

    {!! form_checkbox('mapping_tools_div_visual-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}

    {!! form_radio_files('mapping_tools_div_visual-box2','Select coordinates file',$tooltips,$workspace_files) !!}

   {!! form_radio_files('mapping_tools_div_visual-box3','Select indices file',$tooltips,$workspace_files) !!}
    <br>
<div style="color: blue; font-weight: bold">Parameters</div>

{!! form_dropdown('mapping_tools_div-column_select','Select Column in Indices File:',array(),'',$tooltips) !!}

    {!! form_textinput('mapping_tools_div_visual-top_species','Number of top ranked species','21',$tooltips) !!}

        <div class='radio_wrapper'>
        <div class='configuration-label'>
        </div>
        </div>


    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#mapping_tools_div_visual_form input[name="box3"]', function(){
        var selectedValue = $("#mapping_tools_div_visual_form input[name='box3']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"mapping_tools_div_visual_form","column_select");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });

</script>

