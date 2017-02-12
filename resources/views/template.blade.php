<!doctype html>
<html lang="en">
<head> 
	<meta charset="UTF-8">
	<title>{{ $title }}</title>
        {!! $head !!}

        <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />

        <script type="text/javascript" src="{{ asset('js/custom.js') }}"></script>
</head>
<body>
        {!! $body_top !!}

        {!! $content !!}

        {!! $body_bottom !!}
        <img src="{{ url('/images/loading.gif') }}" style="display:none" id="loading-image" />

</body>
</html>
@if(Session::has('toastr'))
    <?php $toastr = Session::get('toastr'); Session::forget('toastr'); ?>
    <script type="text/javascript">
        switch('{{ $toastr[0] }}'){
            case 'info':
                toastr.info('{{ $toastr[1] }}');
                break;
            case 'success':
                toastr.success('{{ $toastr[1] }}');
                break;
            case 'warning':
                toastr.warning('{{ $toastr[1] }}');
                break;
            case 'error':
                toastr.error('{{ $toastr[1] }}');
                break;
        }
    </script>
@endif