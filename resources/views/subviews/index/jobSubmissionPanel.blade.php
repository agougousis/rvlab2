<div class="panel panel-default" style="margin-top: 20px">
    <div class="panel-heading">
        <span class="glyphicon glyphicon-edit" aria-hidden="true"></span>
        <strong>Submit a new Job</strong>
        <div style="float: right">
            <a id="documentation_link" href="http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/taxondive.html" target="_blank">
                <img id="documentation_icon" src='{{ asset('images/help.png') }}' style='width:20px; margin-right: 5px' title="taxa2dist documentation">
            </a>
        </div>
        <div style="clear: both"></div>
    </div>
    <div class="panel-body" style="background-color: #F2F3F9">
        <script type="text/javascript">
            var selected_function = "{{ $last_function_used }}";
        </script>

        <div class="container" style="width: 100%">

            <div class="row">
                <div class="col-sm-5">
                    <div style="color: blue; font-weight: bold; margin-top: 7px">Statistical Function</div>
                </div>
                <div class="col-sm-7">
                    <form id="new_description_form" class="form-horizontal">
                        <select class="form-control" id="selected_function">
                            @foreach($r_functions as $codename => $title)
                                <option value="{{ $codename }}">{{ $title }}</option>
                            @endforeach
                            <option></option>
                        </select>
                    </form>
                </div>
            </div>

            @include("js.csv_headers")

            @foreach($forms as $form)
                {!! $form !!}
            @endforeach

        </div>
    </div>
</div>
