@include("admin.bar")

<div class="col-sm-12">
    <div style='font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 30px'>Total Storage Utilization</div>

    <style type='text/css'>
        #workspace_util_table td {
            border-top: 0px;
        }
    </style>

    <table class='table thin-table' id='workspace_util_table'>
        <tbody>
            <tr>
                <td style='text-align: left'><label></label></td>
                <td style='width: 90%'>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" aria-valuenow="{{ $storage->utilization }}" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: {{ $storage->utilization }}%">
                          {{ $storage->utilization }}%
                        </div>
                    </div>
                </td>
                <td style='min-width: 150px; text-align: left'>{{ $storage->getUtilizedText() }}</td>
            </tr>
        </tbody>
    </table>

    <div style='font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 30px'>Per User Storage Utilization</div>

    <table class='table thin-table' id='workspace_util_table'>
        <tbody>
            @foreach($storage->getUserTotals() as $user_email => $totalInfo)
                @if($totalInfo['progress'] <= 100)
                    <tr>
                        <td style='text-align: left'><label>{{ $user_email }}</label></td>
                        <td style='width: 90%'>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" aria-valuenow="{{ $totalInfo['progress'] }}" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: {{ $totalInfo['progress'] }}%">
                                  {{ $totalInfo['progress'] }}%
                                </div>
                            </div>
                        </td>
                        <td style='min-width: 150px; text-align: left'>{{ $totalInfo['size_text'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td style='text-align: left'><label>{{ $user_email }}</label></td>
                        <td style='width: 90%'>
                            <div class="progress">
                                <div class="red-progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: 100%">
                                  {{ $totalInfo['progress'] }}%
                                </div>
                            </div>
                        </td>
                        <td style='min-width: 150px; text-align: left'>{{ $totalInfo['size_text'] }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>


