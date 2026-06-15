{include file="customer/header.tpl"}

{if $stats}
<div class="row">
    <div class="col-sm-3 col-xs-6">
        <div class="small-box bg-aqua"><div class="inner">
            <h4>{$stats.total}</h4><p>{Lang::T('Total Used')}</p>
        </div><div class="icon"><i class="fa fa-database"></i></div></div>
    </div>
    <div class="col-sm-3 col-xs-6">
        <div class="small-box bg-blue"><div class="inner">
            <h4>{$stats.download}</h4><p>{Lang::T('Download')}</p>
        </div><div class="icon"><i class="fa fa-download"></i></div></div>
    </div>
    <div class="col-sm-3 col-xs-6">
        <div class="small-box bg-red"><div class="inner">
            <h4>{$stats.upload}</h4><p>{Lang::T('Upload')}</p>
        </div><div class="icon"><i class="fa fa-upload"></i></div></div>
    </div>
    <div class="col-sm-3 col-xs-6">
        <div class="small-box bg-green"><div class="inner">
            <h4>{$stats.sessions}</h4><p>{Lang::T('Sessions')}</p>
        </div><div class="icon"><i class="fa fa-list"></i></div></div>
    </div>
</div>

{if $stats.quota_pct !== null}
<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">{Lang::T('Quota Usage')}</div>
    <div class="panel-body">
        <div class="progress">
            <div class="progress-bar {if $stats.quota_pct >= 90}progress-bar-danger{elseif $stats.quota_pct >= 70}progress-bar-warning{else}progress-bar-success{/if}"
                 style="width:{$stats.quota_pct}%">{$stats.quota_pct}%</div>
        </div>
        <small>{$stats.total} {Lang::T('of')} {$stats.quota} {Lang::T('used')}</small>
    </div>
</div>
{/if}
{/if}

<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">{Lang::T('Daily Usage Trend')}</div>
    <div class="panel-body"><canvas height="90" id="trendChart"></canvas></div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">{Lang::T('Download')} / {Lang::T('Upload')}</div>
            <div class="panel-body"><canvas height="300" id="dataUsageChart"></canvas></div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">{Lang::T('Total Per Session')}</div>
            <div class="panel-body"><canvas height="300" id="sessionTotalChart"></canvas></div>
        </div>
    </div>
</div>

<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">{Lang::T('My Sessions')}</div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead><tr>
                <th>#</th><th>{Lang::T('Download')} (MB)</th><th>{Lang::T('Upload')} (MB)</th><th>{Lang::T('Total')} (MB)</th><th>{Lang::T('Status')}</th><th>{Lang::T('Date')}</th>
            </tr></thead>
            <tbody>
            {foreach $data as $row}
                <tr>
                    <td>{$row.no}</td>
                    <td>{$row.downloadMB}</td>
                    <td>{$row.uploadMB}</td>
                    <td>{$row.totalMB}</td>
                    <td>{$row.status}</td>
                    <td>{$row.sdate|escape}</td>
                </tr>
            {foreachelse}
                <tr><td colspan="6" class="text-center">{Lang::T('No data usage records found.')}</td></tr>
            {/foreach}
            </tbody>
        </table>
    </div>
    {include file="pagination.tpl"}
</div>

{* Local Chart.js first (offline-safe), CDN fallback only if missing. *}
<script src="{$app_url}/system/plugin/ui/assets/chart.min.js"></script>
<script>window.Chart||document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@3.5.1/dist/chart.min.js"><\/script>');</script>
<script type="text/javascript">
    (function () {
        if (typeof Chart === 'undefined') return;
        var trend = document.getElementById('trendChart');
        if (trend) new Chart(trend.getContext('2d'), {
            type: 'line',
            data: { labels: {$trend_labels}, datasets: [
                { label: '{Lang::T('Download')} (MB)', backgroundColor: 'rgba(54,162,235,0.2)', borderColor: 'rgba(54,162,235,1)', borderWidth: 2, data: {$trend_download} },
                { label: '{Lang::T('Upload')} (MB)',   backgroundColor: 'rgba(255,99,132,0.2)', borderColor: 'rgba(255,99,132,1)', borderWidth: 2, data: {$trend_upload} }
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });

        var labels = {$chart_labels};
        var du = document.getElementById('dataUsageChart');
        if (du) new Chart(du.getContext('2d'), {
            type: 'line',
            data: { labels: labels, datasets: [
                { label: '{Lang::T('Download')} (MB)', backgroundColor: 'rgba(54,162,235,0.2)', borderColor: 'rgba(54,162,235,1)', borderWidth: 2, data: {$chart_download} },
                { label: '{Lang::T('Upload')} (MB)',   backgroundColor: 'rgba(255,99,132,0.2)', borderColor: 'rgba(255,99,132,1)', borderWidth: 2, data: {$chart_upload} }
            ]},
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        var st = document.getElementById('sessionTotalChart');
        if (st) new Chart(st.getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [
                { label: '{Lang::T('Total')} (MB)', backgroundColor: 'rgba(75,192,192,0.2)', borderColor: 'rgba(75,192,192,1)', borderWidth: 2, data: {$chart_total} }
            ]},
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    })();
</script>

{include file="customer/footer.tpl"}
