<!-- Function loadCsvHeaders() is used by many forms -->
<script type="text/javascript">

    function getCsvHeaders(filename){
        var headers;
        $.ajax({
            url: '{{ url("workspace/convert2r") }}'+"/"+filename,
            type: "GET",
            dataType : 'json',
            async: false,
            success: function(data) {
                headers = data.headers;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                headers = false;
            }
        });
        return headers;
    }

    // Retrieves the CSV headers of the file and loads  the headers in the dropdown field
    function loadCsvHeaders(filename,formid,fieldname){
        $.ajax({
            url: '{{ url("workspace/convert2r") }}'+"/"+filename,
            type: "GET",
            dataType : 'json',
            success: function(data) {

                headers = data.headers;
                var selectElement = $('#'+formid+' select[name="'+fieldname+'"]');
                selectElement.empty();

                for(var i = 0; i < headers.length; i++) {

                    if((formid == 'cca_form')&&(fieldname == 'Factor_select3')){
                        // Factor_select3 parameter is cca is optional (special case)
                        if(i == 0){
                            selectElement.append("<option selected='selected'></option>");
                            selectElement.append("<option>"+headers[i]+"</option>");
                        } else {
                            selectElement.append("<option>"+headers[i]+"</option>");
                        }
                    } else if((formid == 'cca_visual_form')&&(fieldname == 'Factor_select3')){
                        // Factor_select3 parameter is cca is optional (special case)
                        if(i == 0){
                            selectElement.append("<option selected='selected'></option>");
                            selectElement.append("<option>"+headers[i]+"</option>");
                        } else {
                            selectElement.append("<option>"+headers[i]+"</option>");
                        }
                    } else {
                        // General case
                        if(i == 0){
                            selectElement.append("<option selected='selected'>"+headers[i]+"</option>");
                        } else {
                            selectElement.append("<option>"+headers[i]+"</option>");
                        }
                    }
                }

            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("File headers for "+formid+" could not be retrieved!");
            }
        });
    }

    // Loads  the headers in the dropdown field (used in case the headers have already been retrieved by a call to getCsvHeaders() )
    function loadCsvHeaders2(headers,formid,fieldname){

        var selectElement = $('#'+formid+' select[name="'+fieldname+'"]');
        selectElement.empty();

        for(var i = 0; i < headers.length; i++) {

            if((formid == 'cca_form')&&(fieldname == 'Factor_select3')){
                // Factor_select3 parameter is cca is optional (special case)
                if(i == 0){
                    selectElement.append("<option selected='selected'></option>");
                    selectElement.append("<option>"+headers[i]+"</option>");
                } else {
                    selectElement.append("<option>"+headers[i]+"</option>");
                }
            } else if((formid == 'cca_visual_form')&&(fieldname == 'Factor_select3')){
                // Factor_select3 parameter is cca is optional (special case)
                if(i == 0){
                    selectElement.append("<option selected='selected'></option>");
                    selectElement.append("<option>"+headers[i]+"</option>");
                } else {
                    selectElement.append("<option>"+headers[i]+"</option>");
                }
            } else {
                // General case
                if(i == 0){
                    selectElement.append("<option selected='selected'>"+headers[i]+"</option>");
                } else {
                    selectElement.append("<option>"+headers[i]+"</option>");
                }
            }
        }
    }

    function loadInitialHeaders(headers){
        var selectElement = $('#header1_id');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }

        selectElement = $('#header2_id');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }

        selectElement = $('#header3_id');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }

        selectElement = $('#header1_fact');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }

        selectElement = $('#header2_fact');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }

        selectElement = $('#header3_fact');
        selectElement.empty();
        for(var i = 0; i < headers.length; i++) {
            selectElement.append("<option>"+headers[i]+"</option>");
        }
    }

</script>