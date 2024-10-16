{include file="customer/header.tpl"}

<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                Session Total Data Usage
            </div>
            <div class="table-responsive">
                <canvas class="center-block" height="600" width="800" id="sessionTotalChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                User Data Usage Overview
            </div>
            <div class="table-responsive">
                <canvas class="center-block" height="600" width="800" id="dataUsageChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                User In/Out Data Details
            </div>
            <div class="table-responsive">
                <table id="datatable" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Download (MB)</th>
                        <th>Upload (MB)</th>
                        <th>Total (MB)</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach $data as $row}
                        <tr>
                            <td>{$row.id}</td>
                            <td>{$row.acctInputOctets}</td>
                            <td>{$row.acctOutputOctets}</td>
                            <td>{$row.totalBytes}</td>
                            <td>{$row.status}</td>
                            <td>{$row.sdate}</td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="text/javascript">
    var chartData = {
        labels: [{foreach $data as $row} "{$row.sdate}", {/foreach}],
        datasets: [
            {
                label: 'Download (MB)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 3,
                data: [{foreach $data as $row} {$row.acctInputOctets}, {/foreach}]
            },
            {
                label: 'Upload (MB)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 3,
                data: [{foreach $data as $row} {$row.acctOutputOctets}, {/foreach}]
            }
        ]
    };

    var ctx = document.getElementById('dataUsageChart').getContext('2d');
    var dataUsageChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: false,
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Data (MB)'
                    }
                }
            }
        }
    });

    var sessionTotalData = {
        labels: [{foreach $data as $row} "{$row.sdate}", {/foreach}],
        datasets: [
            {
                label: 'Total Data (MB)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 3,
                data: [{foreach $data as $row} {$row.totalBytes}, {/foreach}]
            }
        ]
    };

    var ctxsession = document.getElementById('sessionTotalChart').getContext('2d');
    var sessionTotalChart = new Chart(ctxsession, {
        type: 'bar',
        data: sessionTotalData,
        options: {
            responsive: false,
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Data (MB)'
                    }
                }
            }
        }
    });
</script>

{include file="customer/footer.tpl"}
