<?php $function = "anova"; ?>

{!! Form::open(array('url'=>'job/visual','class'=>'form-horizontal','id'=>'anova_form','style'=>'display:none')) !!}

    {!! form_function_about('anova',$tooltips) !!}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {!! form_radio_files('anova-box','Select envirmomental factor file data from loaded files',$tooltips,$workspace_files) !!}

    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>

    <div class='radio_wrapper'>
        <div class='configuration-label'>
            <strong>Factor File</strong>
            Fit an analysis of variance model by a call to lm for each stratum according to the following formulas:
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="one_or_two_way" value="one" checked>
              One way Anova- aov.ex1<-aov(Factor1~Factor2, data)
            </label>
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="one_or_two_way" value="two">
              Two way Anova- aov.ex2<-aov(Factor1~Factor2*Factor3, data)
            </label>
        </div>
    </div>

    {!! form_dropdown('anova-Factor_select1','Select Column in Factor File (Factor1)',array(),'',$tooltips) !!}
    {!! form_dropdown('anova-Factor_select2','Select Column in Factor File (Factor2)',array(),'',$tooltips) !!}
    {!! form_dropdown('anova-Factor_select3','Select Column in Factor File <br>(Factor3 - optional for two way Anova)',array(),'',$tooltips) !!}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{!! Form::close() !!}

<script type="text/javascript">

    // If user selects another file call the function that updates the dropdowns
    $(document).on('change', '#anova_form input[name="box"]', function(){
        var selectedValue = $("#anova_form input[name='box']:checked").val();
        var fileHeaders = getCsvHeaders(selectedValue);
        if(fileHeaders){
            loadCsvHeaders2(fileHeaders,"anova_form","Factor_select1");
            loadCsvHeaders2(fileHeaders,"anova_form","Factor_select2");
            loadCsvHeaders2(fileHeaders,"anova_form","Factor_select3");
        } else {
            toastr.error("File headers could not be retrieved!");
        }
    });

</script>
