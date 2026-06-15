<?php

/**
 * User Data Usage Plugin for PHPNuxBill  —  Customer view
 *
 * Shows the logged-in customer's own usage: stat cards (download/upload/total,
 * quota used), charts, and a paginated session list. Backend-agnostic via the
 * shared helpers in data_usage_admin.php (REST rad_acct or SQL radacct).
 */

register_menu("User Data Usage", false, "UserDataUsage", 'AFTER_DASHBOARD', 'fa fa-bar-chart');

function UserDataUsage()
{
    global $ui;
    _auth();
    $ui->assign('_title', 'User Data Usage');
    $ui->assign('_system_menu', '');
    $user = User::_info();
    $ui->assign('_user', $user);

    $schema = UserDataUsage_schema();
    if ($schema === null) {
        $ui->assign('data', []);
        $ui->assign('stats', null);
        foreach (['chart_labels', 'chart_download', 'chart_upload', 'chart_total'] as $k) {
            $ui->assign($k, '[]');
        }
        $ui->display('data_usage_user.tpl');
        return;
    }

    $username = $user['username'];

    // Per-user grand totals + quota (reuse the admin aggregation, scoped to me).
    $agg = ORM::for_table($schema['table'], $schema['conn'])
        ->where('username', $username)
        ->select_expr('SUM(COALESCE(acctinputoctets,0))', 'in_sum')
        ->select_expr('SUM(COALESCE(acctoutputoctets,0))', 'out_sum')
        ->select_expr('COUNT(*)', 'sessions')
        ->find_one();
    $in  = floatval($agg ? $agg->in_sum : 0);
    $out = floatval($agg ? $agg->out_sum : 0);
    $summary = [
        'username'    => $username,
        'total_bytes' => $in + $out,
        'sessions'    => intval($agg ? $agg->sessions : 0),
    ];
    UserDataUsage_attachQuota($summary);
    $ui->assign('stats', [
        'download' => UserDataUsage_human($out),
        'upload'   => UserDataUsage_human($in),
        'total'    => UserDataUsage_human($in + $out),
        'sessions' => $summary['sessions'],
        'quota'    => $summary['quota'],
        'quota_pct' => $summary['quota_pct'],
    ]);

    // Session list (newest first) + chart series in chronological order.
    $rows = Paginator::findMany(
        UserDataUsage_query($schema, ['username_exact' => $username]),
        [],
        20
    );
    $rows = $rows ?: [];
    $i = 0;
    foreach ($rows as $row) {
        UserDataUsage_decorate($row, $schema);
        $row->no = ++$i;
    }
    $ui->assign('data', $rows);

    $chrono = array_reverse($rows);
    $labels = $download = $upload = $total = [];
    foreach ($chrono as $row) {
        $labels[]   = $row->sdate;
        $download[] = $row->downloadMB;
        $upload[]   = $row->uploadMB;
        $total[]    = $row->totalMB;
    }
    $ui->assign('chart_labels', json_encode($labels));
    $ui->assign('chart_download', json_encode($download));
    $ui->assign('chart_upload', json_encode($upload));
    $ui->assign('chart_total', json_encode($total));

    // Daily usage trend for this customer
    $trend = UserDataUsage_trend($schema, ['username' => '', 'from' => '', 'to' => '', 'status' => ''], $username);
    $ui->assign('trend_labels', json_encode($trend['labels']));
    $ui->assign('trend_download', json_encode($trend['download']));
    $ui->assign('trend_upload', json_encode($trend['upload']));

    $ui->display('data_usage_user.tpl');
}
