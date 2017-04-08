<div class="row">
    @if($deletion_info['total'] == $deletion_info['deleted'])
        <div class='col-sm-12'>
            <div class='alert alert-success alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                All selected jobs deleted successfully!
            </div>
        </div>
    @elseif($deletion_info['deleted'] > 0)
        <div class='col-sm-12'>
            <div class='alert alert-warning alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                <strong>Warning!</strong> Some jobs couldn't be deleted!
            </div>
        </div>
        @foreach($deletion_info['messages'] as $message)
            <div class='col-sm-12'>
                <div class='alert alert-danger alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error:</strong> {{ $message }}
                </div>
            </div>
        @endforeach
    @else
        <div class='col-sm-12'>
            <div class='alert alert-danger alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                <strong>Error:</strong> None of the selected jobs could be deleted!
            </div>
        </div>
        @foreach($deletion_info['messages'] as $message)
            <div class='col-sm-12'>
                <div class='alert alert-danger alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error:</strong> {{ $message }}
                </div>
            </div>
        @endforeach
    @endif
</div>