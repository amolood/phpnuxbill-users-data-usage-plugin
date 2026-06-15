{include file="sections/header.tpl"}

{if isset($error)}
    <div class="alert alert-warning">{$error}</div>
{else}
<div class="row">
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-aqua"><div class="inner">
            <h4>{$stats.total}</h4><p>{Lang::T('Total Data')}</p>
        </div><div class="icon"><i class="fa fa-database"></i></div></div>
    </div>
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-blue"><div class="inner">
            <h4>{$stats.download}</h4><p>{Lang::T('Download')}</p>
        </div><div class="icon"><i class="fa fa-download"></i></div></div>
    </div>
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-red"><div class="inner">
            <h4>{$stats.upload}</h4><p>{Lang::T('Upload')}</p>
        </div><div class="icon"><i class="fa fa-upload"></i></div></div>
    </div>
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-green"><div class="inner">
            <h4>{$stats.online}</h4><p>{Lang::T('Online Now')}</p>
        </div><div class="icon"><i class="fa fa-wifi"></i></div></div>
    </div>
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-yellow"><div class="inner">
            <h4>{$stats.users}</h4><p>{Lang::T('Users')}</p>
        </div><div class="icon"><i class="fa fa-users"></i></div></div>
    </div>
    <div class="col-lg-2 col-xs-6">
        <div class="small-box bg-purple"><div class="inner">
            <h4>{$stats.sessions}</h4><p>{Lang::T('Sessions')}</p>
        </div><div class="icon"><i class="fa fa-list"></i></div></div>
    </div>
</div>

<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">{Lang::T('Daily Usage Trend')}</div>
    <div class="panel-body"><canvas height="80" id="trendChart"></canvas></div>
</div>

<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">
        {Lang::T('Users Data Usage')}
        <small class="pull-right">{Lang::T('Backend')}: {$mode}</small>
    </div>
    <div class="panel-body">
        <form method="get" action="{$_url}UserDataUsageAdmin" class="form-inline">
            <input type="hidden" name="view" value="{$view}">
            <div class="form-group">
                <input type="text" name="q" class="form-control" value="{$q|escape}" placeholder="{Lang::T('Username')}">
            </div>
            <div class="form-group">
                <input type="date" name="from" class="form-control" value="{$from|escape}" title="{Lang::T('From')}">
            </div>
            <div class="form-group">
                <input type="date" name="to" class="form-control" value="{$to|escape}" title="{Lang::T('To')}">
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">{Lang::T('All Status')}</option>
                    <option value="online" {if $status=='online'}selected{/if}>{Lang::T('Online')}</option>
                    <option value="offline" {if $status=='offline'}selected{/if}>{Lang::T('Offline')}</option>
                </select>
            </div>
            <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> {Lang::T('Filter')}</button>
            <a class="btn btn-default" href="{$_url}UserDataUsageAdmin?view={$view}">{Lang::T('Clear')}</a>
            <a class="btn btn-info" href="{$_url}UserDataUsageAdmin?export=csv&view={$view}&q={$q|escape}&from={$from|escape}&to={$to|escape}&status={$status|escape}"><i class="fa fa-file-excel-o"></i> {Lang::T('CSV')}</a>
        </form>
        <div class="btn-group" style="margin-top:10px">
            <a class="btn btn-sm {if $view=='summary'}btn-primary{else}btn-default{/if}"
               href="{$_url}UserDataUsageAdmin?view=summary&q={$q|escape}&from={$from|escape}&to={$to|escape}&status={$status|escape}">{Lang::T('Per User')}</a>
            <a class="btn btn-sm {if $view=='sessions'}btn-primary{else}btn-default{/if}"
               href="{$_url}UserDataUsageAdmin?view=sessions&q={$q|escape}&from={$from|escape}&to={$to|escape}&status={$status|escape}">{Lang::T('Sessions')}</a>
        </div>
    </div>

    <div class="table-responsive">
    {if $view=='summary'}
        <table class="table table-bordered table-striped">
            <thead><tr>
                <th>{Lang::T('Username')}</th><th>{Lang::T('Download')}</th><th>{Lang::T('Upload')}</th><th>{Lang::T('Total')}</th>
                <th>{Lang::T('Sessions')}</th><th>{Lang::T('Quota Used')}</th><th>{Lang::T('Last Seen')}</th>
            </tr></thead>
            <tbody>
            {foreach $summaryRows as $r}
                <tr>
                    <td>{$r.username|escape}</td>
                    <td>{$r.download}</td>
                    <td>{$r.upload}</td>
                    <td><b>{$r.total}</b></td>
                    <td>{$r.sessions}</td>
                    <td>
                        {if $r.quota_pct !== null}
                            <div class="progress" style="margin-bottom:3px">
                                <div class="progress-bar {if $r.quota_pct >= 90}progress-bar-danger{elseif $r.quota_pct >= 70}progress-bar-warning{else}progress-bar-success{/if}"
                                     style="width:{$r.quota_pct}%">{$r.quota_pct}%</div>
                            </div>
                            <small>{$r.total} / {$r.quota}</small>
                        {else}<span class="text-muted">—</span>{/if}
                    </td>
                    <td>{$r.last_seen|escape}</td>
                </tr>
            {foreachelse}
                <tr><td colspan="7" class="text-center">{Lang::T('No data usage records found.')}</td></tr>
            {/foreach}
            </tbody>
        </table>
        {include file="pagination.tpl"}
    {else}
        <table class="table table-bordered table-striped">
            <thead><tr>
                <th>{Lang::T('Username')}</th><th>{Lang::T('Download')}</th><th>{Lang::T('Upload')}</th><th>{Lang::T('Total')}</th><th>{Lang::T('Status')}</th><th>{Lang::T('Date')}</th>
            </tr></thead>
            <tbody>
            {foreach $data as $row}
                <tr>
                    <td>{$row.username|escape}</td>
                    <td>{$row.download}</td>
                    <td>{$row.upload}</td>
                    <td><b>{$row.totalBytes}</b></td>
                    <td>{$row.status}</td>
                    <td>{$row.sdate|escape}</td>
                </tr>
            {foreachelse}
                <tr><td colspan="6" class="text-center">{Lang::T('No data usage records found.')}</td></tr>
            {/foreach}
            </tbody>
        </table>
        {include file="pagination.tpl"}
    {/if}
    </div>
</div>
{/if}

{include file="sections/footer.tpl"}

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.5.1/dist/chart.min.js"></script>
<script type="text/javascript">
    (function () {
        var el = document.getElementById('trendChart');
        if (!el || typeof Chart === 'undefined') return;
        new Chart(el.getContext('2d'), {
            type: 'line',
            data: { labels: {$trend_labels}, datasets: [
                { label: '{Lang::T('Download')} (MB)', backgroundColor: 'rgba(54,162,235,0.2)', borderColor: 'rgba(54,162,235,1)', borderWidth: 2, data: {$trend_download} },
                { label: '{Lang::T('Upload')} (MB)',   backgroundColor: 'rgba(255,99,132,0.2)', borderColor: 'rgba(255,99,132,1)', borderWidth: 2, data: {$trend_upload} }
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
        $('#version').html('Plugin by: <a href="https://github.com/amolood">Amolood</a>');
    })();
</script>
