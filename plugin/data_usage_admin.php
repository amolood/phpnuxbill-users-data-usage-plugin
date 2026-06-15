<?php

/**
 * User Data Usage Plugin for PHPNuxBill  —  Admin view
 *
 * Works with BOTH FreeRADIUS accounting backends:
 *   - REST : table `rad_acct` on the DEFAULT connection (written by radius.php).
 *            Online via acctstatustype='Start'; date column dateAdded.
 *   - SQL  : table `radacct` on the separate 'radius' connection (written by
 *            FreeRADIUS). Online via acctstoptime IS NULL; date acctstarttime.
 *
 * Features: per-session list, per-user aggregated summary, summary stat cards,
 * username search, date-range + status filters, plan quota context, CSV export.
 *
 * Shared helpers (schema/query/decorate/human) are defined here and reused by
 * data_usage_user.php (both files are auto-included at boot).
 */

define('USER_DATA_USAGE_VERSION', '2.1.0');

register_menu("User Data Usage", true, "UserDataUsageAdmin", 'SERVICES', 'fa fa-bar-chart');

/** Plugin metadata (name, version, author). */
function UserDataUsage_about()
{
    return [
        'name'    => 'User Data Usage',
        'version' => USER_DATA_USAGE_VERSION,
        'author'  => 'amolood',
        'url'     => 'https://github.com/amolood/phpnuxbill-users-data-usage-plugin',
    ];
}

/* ------------------------------------------------------------------ *
 *  Backend detection & shared helpers
 * ------------------------------------------------------------------ */

/**
 * Detect the active accounting backend.
 * @return array|null ['table','conn','date','status_mode'] or null if none.
 */
function UserDataUsage_schema()
{
    static $schema = false;
    if ($schema !== false) {
        return $schema;
    }
    $candidates = [
        ['table' => 'rad_acct', 'conn' => ORM::DEFAULT_CONNECTION, 'date' => 'dateAdded',     'status_mode' => 'statustype', 'status_col' => 'acctstatustype'],
        ['table' => 'radacct',  'conn' => 'radius',                'date' => 'acctstarttime', 'status_mode' => 'stoptime',   'status_col' => 'acctstoptime'],
    ];
    foreach ($candidates as $c) {
        // Probe the table AND the specific columns this plugin reads, so we
        // never commit to a backend whose schema we can't actually use. Pure
        // read; if the table/columns aren't present, move on.
        try {
            ORM::for_table($c['table'], $c['conn'])
                ->select_many('username', 'acctinputoctets', 'acctoutputoctets', $c['date'], $c['status_col'])
                ->limit(1)
                ->find_one();
            return $schema = $c;
        } catch (Throwable $e) {
            // table or a required column not available, try next candidate
        }
    }
    return $schema = null;
}

/** Base query for the detected backend, with optional username/date/status filters. */
function UserDataUsage_query($schema, $filters = [])
{
    $q = ORM::for_table($schema['table'], $schema['conn'])
        ->where_raw('(COALESCE(acctinputoctets,0) + COALESCE(acctoutputoctets,0)) > 0');

    if (!empty($filters['username'])) {
        $q = $q->where_like('username', '%' . $filters['username'] . '%');
    }
    if (!empty($filters['username_exact'])) {
        $q = $q->where('username', $filters['username_exact']);
    }
    if (!empty($filters['from'])) {
        $q = $q->where_gte($schema['date'], $filters['from'] . ' 00:00:00');
    }
    if (!empty($filters['to'])) {
        $q = $q->where_lte($schema['date'], $filters['to'] . ' 23:59:59');
    }
    if (!empty($filters['status'])) {
        if ($schema['status_mode'] === 'statustype') {
            if ($filters['status'] === 'online')  $q = $q->where('acctstatustype', 'Start');
            if ($filters['status'] === 'offline') $q = $q->where_not_equal('acctstatustype', 'Start');
        } else {
            if ($filters['status'] === 'online')  $q = $q->where_null('acctstoptime');
            if ($filters['status'] === 'offline') $q = $q->where_not_null('acctstoptime');
        }
    }
    return $q->order_by_desc($schema['date']);
}

/** Decorate a raw accounting row with display fields, backend-agnostic. */
function UserDataUsage_decorate($row, $schema)
{
    $in  = floatval($row->acctinputoctets);   // FROM client = upload
    $out = floatval($row->acctoutputoctets);  // TO client   = download

    $row->upload     = UserDataUsage_human($in);
    $row->download   = UserDataUsage_human($out);
    $row->totalBytes = UserDataUsage_human($in + $out);
    $row->uploadMB   = round($in / 1048576, 2);
    $row->downloadMB = round($out / 1048576, 2);
    $row->totalMB    = round(($in + $out) / 1048576, 2);

    if ($schema['status_mode'] === 'statustype') {
        $connected = (strcasecmp((string)$row->acctstatustype, 'Start') === 0);
    } else {
        $connected = empty($row->acctstoptime);
    }
    $row->connected = $connected;
    $row->status = $connected
        ? '<span class="badge btn-success">Connected</span>'
        : '<span class="badge btn-danger">Disconnected</span>';

    $df = $schema['date'];
    $row->sdate = !empty($row->$df) ? $row->$df : '-';
    return $row;
}

function UserDataUsage_human($bytes)
{
    $bytes = floatval($bytes);
    if ($bytes >= 1099511627776) return number_format($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824)    return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)       return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)          return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes > 0)              return number_format($bytes, 0) . ' bytes';
    return '0 bytes';
}

/** Read the requested admin filters from the request. */
function UserDataUsage_filters()
{
    return [
        'username' => _req('q', ''),
        'from'     => preg_replace('/[^0-9\-]/', '', _req('from', '')),
        'to'       => preg_replace('/[^0-9\-]/', '', _req('to', '')),
        'status'   => in_array(_req('status', ''), ['online', 'offline']) ? _req('status', '') : '',
    ];
}

/**
 * Aggregate total usage per username for the current filter set.
 * Optionally paginated (when $pagerArgs is provided, uses Paginator so the
 * summary view scales to thousands of users instead of one huge table).
 */
function UserDataUsage_perUser($schema, $filters, $pagerArgs = null, $perPage = 25)
{
    $df = $schema['date'];
    $base = UserDataUsage_query($schema, $filters)
        ->select_expr('username', 'username')
        ->select_expr('SUM(COALESCE(acctinputoctets,0))', 'in_sum')
        ->select_expr('SUM(COALESCE(acctoutputoctets,0))', 'out_sum')
        ->select_expr('COUNT(*)', 'sessions')
        ->select_expr("MAX($df)", 'last_seen')
        ->group_by('username')
        // order by the raw aggregate expression — MySQL can't ORDER BY an
        // aggregate alias used inside another expression
        ->order_by_expr('SUM(COALESCE(acctoutputoctets,0)) + SUM(COALESCE(acctinputoctets,0)) DESC');

    if ($pagerArgs !== null) {
        $rows = Paginator::findMany($base, $pagerArgs, $perPage, '', true); // toArray=true
        $rows = $rows ?: [];
    } else {
        $rows = $base->find_array();
    }

    // Batch-load quota for all usernames on this page in ONE pass (no N+1).
    $quota = UserDataUsage_quotaMap(array_column($rows, 'username'));

    foreach ($rows as &$r) {
        $in = floatval($r['in_sum']);
        $out = floatval($r['out_sum']);
        $r['upload'] = UserDataUsage_human($in);
        $r['download'] = UserDataUsage_human($out);
        $r['total'] = UserDataUsage_human($in + $out);
        $r['total_bytes'] = $in + $out;
        $r['quota'] = '';
        $r['quota_pct'] = null;
        $u = $r['username'];
        if (isset($quota[$u]) && $quota[$u]['limit'] > 0) {
            $r['quota'] = UserDataUsage_human($quota[$u]['limit']);
            $r['quota_pct'] = min(100, round(($in + $out) / $quota[$u]['limit'] * 100, 1));
        }
    }
    unset($r);
    return $rows;
}

/**
 * Build username => ['limit'=>bytes] for the given usernames, batching the
 * recharge + plan lookups into two queries total instead of two per user.
 * Best-effort: returns [] if the billing tables aren't usable.
 */
function UserDataUsage_quotaMap($usernames)
{
    $usernames = array_values(array_unique(array_filter($usernames)));
    if (!$usernames) {
        return [];
    }
    $map = [];
    try {
        $recharges = ORM::for_table('tbl_user_recharges')
            ->where_in('username', $usernames)
            ->find_array();
        if (!$recharges) {
            return [];
        }
        $planIds = array_values(array_unique(array_column($recharges, 'plan_id')));
        $plans = ORM::for_table('tbl_plans')->where_in('id', $planIds)->find_array();
        $planById = [];
        foreach ($plans as $p) {
            $planById[$p['id']] = $p;
        }
        foreach ($recharges as $tur) {
            $p = $planById[$tur['plan_id']] ?? null;
            if (!$p) continue;
            if (in_array($p['limit_type'], ['Data_Limit', 'Both_Limit']) && $p['data_limit'] > 0) {
                $limitBytes = Text::convertDataUnit($p['data_limit'], $p['data_unit']);
                if ($limitBytes > 0) {
                    // keep the largest limit if a user has multiple recharges
                    $cur = $map[$tur['username']]['limit'] ?? 0;
                    if ($limitBytes > $cur) {
                        $map[$tur['username']] = ['limit' => $limitBytes];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // billing tables differ/absent — degrade to no quota
        return [];
    }
    return $map;
}

/** Top N data consumers for the current filter set (for the summary card). */
function UserDataUsage_topConsumers($schema, $filters, $limit = 5)
{
    $rows = UserDataUsage_query($schema, $filters)
        ->select_expr('username', 'username')
        ->select_expr('SUM(COALESCE(acctinputoctets,0)) + SUM(COALESCE(acctoutputoctets,0))', 'total_bytes')
        ->group_by('username')
        ->order_by_expr('SUM(COALESCE(acctoutputoctets,0)) + SUM(COALESCE(acctinputoctets,0)) DESC')
        ->limit($limit)
        ->find_array();

    $out = [];
    $max = 0;
    foreach ($rows as $r) {
        $b = floatval($r['total_bytes']);
        if ($b > $max) $max = $b;
        $out[] = ['username' => $r['username'], 'total' => UserDataUsage_human($b), 'bytes' => $b];
    }
    // relative bar width for the card
    foreach ($out as &$o) {
        $o['pct'] = $max > 0 ? round($o['bytes'] / $max * 100) : 0;
    }
    unset($o);
    return $out;
}

/** Grand totals across the current filter set, for the stat cards. */
function UserDataUsage_summary($schema, $filters)
{
    $agg = UserDataUsage_query($schema, $filters)
        ->select_expr('SUM(COALESCE(acctinputoctets,0))', 'in_sum')
        ->select_expr('SUM(COALESCE(acctoutputoctets,0))', 'out_sum')
        ->select_expr('COUNT(*)', 'sessions')
        ->select_expr('COUNT(DISTINCT username)', 'users')
        ->find_one();

    $in = floatval($agg ? $agg->in_sum : 0);
    $out = floatval($agg ? $agg->out_sum : 0);

    // online count needs the status filter cleared
    $onlineFilters = $filters;
    $onlineFilters['status'] = 'online';
    $online = UserDataUsage_query($schema, $onlineFilters)
        ->select_expr('COUNT(DISTINCT username)', 'c')->find_one();

    return [
        'download' => UserDataUsage_human($out),
        'upload'   => UserDataUsage_human($in),
        'total'    => UserDataUsage_human($in + $out),
        'sessions' => intval($agg ? $agg->sessions : 0),
        'users'    => intval($agg ? $agg->users : 0),
        'online'   => intval($online ? $online->c : 0),
    ];
}

/**
 * Map a requested period to a SQL grouping expression + how many points to keep.
 * @return array ['expr'=>string, 'points'=>int]
 */
function UserDataUsage_periodGroup($period, $dateCol)
{
    switch ($period) {
        case 'weekly':
            // ISO-style year-week label, e.g. 2026-W24
            return ['expr' => "DATE_FORMAT($dateCol, '%x-W%v')", 'points' => 26];
        case 'monthly':
            return ['expr' => "DATE_FORMAT($dateCol, '%Y-%m')", 'points' => 24];
        case 'daily':
        default:
            return ['expr' => "DATE($dateCol)", 'points' => 30];
    }
}

/** Validate/normalise a requested period. */
function UserDataUsage_period($default = 'daily')
{
    $p = _req('period', $default);
    return in_array($p, ['daily', 'weekly', 'monthly']) ? $p : $default;
}

/**
 * Usage trend (download/upload MB per period) for the current filter set.
 * $period: daily | weekly | monthly. Optionally scoped to a single username.
 * @return array ['labels'=>[], 'download'=>[], 'upload'=>[]]
 */
function UserDataUsage_trend($schema, $filters, $usernameExact = null, $period = 'daily')
{
    $df = $schema['date'];
    $grp = UserDataUsage_periodGroup($period, $df);

    $f = $filters;
    if ($usernameExact !== null) {
        $f['username_exact'] = $usernameExact;
    }
    $rows = UserDataUsage_query($schema, $f)
        ->select_expr($grp['expr'], 'd')
        ->select_expr('SUM(COALESCE(acctinputoctets,0))', 'in_sum')
        ->select_expr('SUM(COALESCE(acctoutputoctets,0))', 'out_sum')
        ->group_by_expr($grp['expr'])
        ->order_by_expr($grp['expr'] . ' ASC')
        ->find_array();

    // keep the most recent N points for the chosen period
    if (count($rows) > $grp['points']) {
        $rows = array_slice($rows, -$grp['points']);
    }

    $labels = $download = $upload = [];
    foreach ($rows as $r) {
        $labels[]   = $r['d'];
        $download[] = round(floatval($r['out_sum']) / 1048576, 2);
        $upload[]   = round(floatval($r['in_sum']) / 1048576, 2);
    }
    return ['labels' => $labels, 'download' => $download, 'upload' => $upload];
}

/* ------------------------------------------------------------------ *
 *  Admin controller
 * ------------------------------------------------------------------ */

function UserDataUsageAdmin()
{
    global $ui;
    _admin();

    $schema = UserDataUsage_schema();
    $filters = UserDataUsage_filters();
    $view = in_array(_req('view', ''), ['sessions', 'summary']) ? _req('view') : 'summary';

    // CSV export short-circuits the normal render.
    if (_req('export') === 'csv' && $schema !== null) {
        UserDataUsageAdmin_csv($schema, $filters, $view);
        return;
    }

    $ui->assign('_title', 'User Data Usage');
    $ui->assign('_system_menu', '');
    $ui->assign('_admin', Admin::_info());
    $ui->assign('q', $filters['username']);
    $ui->assign('from', $filters['from']);
    $ui->assign('to', $filters['to']);
    $ui->assign('status', $filters['status']);
    $ui->assign('view', $view);

    if ($schema === null) {
        $ui->assign('data', []);
        $ui->assign('summaryRows', []);
        $ui->assign('error', 'No FreeRADIUS accounting table found (rad_acct for REST, or radacct for SQL). Enable Radius and make sure the router sends accounting.');
        $ui->display('data_usage_admin.tpl');
        return;
    }

    $ui->assign('stats', UserDataUsage_summary($schema, $filters));
    $ui->assign('mode', $schema['status_mode'] === 'statustype' ? 'REST (rad_acct)' : 'SQL (radacct)');

    // Usage trend chart series (daily / weekly / monthly)
    $period = UserDataUsage_period('daily');
    $ui->assign('period', $period);
    $trend = UserDataUsage_trend($schema, $filters, null, $period);
    $ui->assign('trend_labels', json_encode($trend['labels']));
    $ui->assign('trend_download', json_encode($trend['download']));
    $ui->assign('trend_upload', json_encode($trend['upload']));

    // Top consumers card
    $ui->assign('topConsumers', UserDataUsage_topConsumers($schema, $filters, 5));

    if ($view === 'summary') {
        $ui->assign('summaryRows', UserDataUsage_perUser($schema, $filters, UserDataUsage_pagerArgs($filters, 'summary')));
        $ui->assign('data', []);
    } else {
        $rows = Paginator::findMany(
            UserDataUsage_query($schema, $filters),
            UserDataUsage_pagerArgs($filters, 'sessions'),
            20
        );
        foreach (($rows ?: []) as $row) {
            UserDataUsage_decorate($row, $schema);
        }
        $ui->assign('data', $rows ?: []);
        $ui->assign('summaryRows', []);
    }

    $ui->display('data_usage_admin.tpl');
}

/** Filter args to keep across pagination links. */
function UserDataUsage_pagerArgs($filters, $view = 'sessions')
{
    $args = [];
    if ($filters['username'] !== '') $args['q'] = $filters['username'];
    if ($filters['from'] !== '')     $args['from'] = $filters['from'];
    if ($filters['to'] !== '')       $args['to'] = $filters['to'];
    if ($filters['status'] !== '')   $args['status'] = $filters['status'];
    $args['view'] = $view;
    return $args;
}

/** Stream the current view as CSV (semicolon-quoted, matching core logs export). */
function UserDataUsageAdmin_csv($schema, $filters, $view)
{
    set_time_limit(-1);
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="data-usage_' . $view . '_' . date('Y-m-d_H_i') . '.csv"');
    header('Content-Transfer-Encoding: binary');

    $out = function ($cols) {
        echo '"' . implode('";"', array_map(fn($v) => str_replace('"', '""', (string)$v), $cols)) . "\"\n";
    };

    if ($view === 'summary') {
        $out(['username', 'download', 'upload', 'total', 'sessions', 'quota', 'quota_percent', 'last_seen']);
        foreach (UserDataUsage_perUser($schema, $filters) as $r) {
            $out([$r['username'], $r['download'], $r['upload'], $r['total'], $r['sessions'],
                  $r['quota'], ($r['quota_pct'] === null ? '' : $r['quota_pct'] . '%'), $r['last_seen']]);
        }
    } else {
        $out(['username', 'download', 'upload', 'total', 'status', 'date']);
        $rows = UserDataUsage_query($schema, $filters)->find_many();
        foreach ($rows as $row) {
            UserDataUsage_decorate($row, $schema);
            $out([$row->username, $row->download, $row->upload, $row->totalBytes,
                  $row->connected ? 'Connected' : 'Disconnected', $row->sdate]);
        }
    }
    die();
}
