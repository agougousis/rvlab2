<?php $function = "permanova"; ?>

{!! Form::open(array('url'=>'job/serial','class'=>'form-horizontal','id'=>'permanova_form','style'=>'display:none')) !!}

{!! form_function_about('permanova',$tooltips) !!}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('permanova-box','Select community data file from loaded files',$tooltips,$workspace_files) !!}
    {!! form_dropdown('permanova-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) !!}
    {!! form_checkbox('taxondive-transpose','Check to transpose matrix','transpose',true,$tooltips) !!}
    {!! form_radio_files('permanova-box2','Select factor file',$tooltips,$workspace_files) !!}

    <div style="color: blue; font-weight: bold">Parameters</div>

    <div class='radio_wrapper'>
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="single" checked>
              Single parameter - adon<-adonis(abundance_data~Factor1, ENV_data, permutations, distance)
            </label>
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="multi">
              Multiple parameter - adon<-adonis(abundance_data~Factor1*Factor2, ENV_data, permutations, distance)
            </label>
        </div>
    </div>

    {!! form_dropdown('permanova-column_select','Select Column in Factor File (Factor1)',array(),'',$tooltips) !!}
    {!! form_dropdown('permanova-column_select2','Select Column in Factor File (Factor2)',array(),'',$tooltips) !!}

    {!! form_textinput('permanova-permutations','Permutations','999',$tooltips) !!}
    {!! form_dropdown('permanova-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#permanova_form input[name="box2"]', function(){
        var selectedValue = $("#permanova_form input[name='box2']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"permanova_form","column_select");
            loadCsvHeaders2(fileHeaders,"permanova_form","column_select2");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });

</script>
