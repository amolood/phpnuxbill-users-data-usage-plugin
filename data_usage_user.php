<?php

register_menu("User Data Usage", false, "UserDataUsage", 'AFTER_DASHBOARD', 'fa fa-bar-chart');

function UserDataUsage()
{
    global $ui;
    $ui->assign('_title', 'DataUsage');
    $ui->assign('_system_menu', '');
    $user = User::_info();
    $ui->assign('_user', $user);
    $search = $user['username'];
    $page = !isset($_GET['page']) ? 1 : (int)$_GET['page'];
    $perPage = 10;

    $data = fetch_user_in_out_data($search, $page, $perPage);
    $total = count_user_in_out_data($search);
    $pagination = create_pagination($page, $perPage, $total);

    $ui->assign('q', $search);
    $ui->assign('data', $data);
    $ui->assign('pagination', $pagination);
    $ui->display('data_usage_user.tpl');
}

function fetch_user_in_out_data($search = '', $page = 1, $perPage = 10000)
{
    // Calculate the start and end date for the current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');

    // Fetch data usage from radacct table
    $query = ORM::for_table('radacct')
        ->select('radacctid', 'id')
        ->select_expr('AcctOutputOctets', 'Download')  // Corrected: AcctOutputOctets corresponds to Download
        ->select_expr('AcctInputOctets', 'Upload') // Corrected: AcctInputOctets corresponds to Upload
        ->select_expr('AcctStartTime', 'StartTime')
        ->select_expr('AcctStopTime', 'StopTime')
        ->select('CallingStationId', 'mac_address')  // MAC Address from callingstationid
        ->select('FramedIPAddress', 'ip_address')   // IP Address from framedipaddress
        ->where_raw("(DATE(AcctStartTime) BETWEEN ? AND ?)", [$start_date, $end_date])
        ->where('username', $search)
        ->order_by_desc('AcctStartTime')
        ->limit($perPage)
        ->offset(($page - 1) * $perPage)
        ->find_many();

    // Prepare the data array
    $data = [];
    foreach ($query as $row) {
        $download = $row->Download ? convert_bytes($row->Download) : '0 MB'; // Download data
        $upload = $row->Upload ? convert_bytes($row->Upload) : '0 MB';      // Upload data

        $row_data = [
            'mac_address' => $row->mac_address, // MAC Address
            'ip_address' => $row->ip_address,   // IP Address
            'acctoutputoctets' => $download,      // Download Data
            'acctinputoctets' => $upload,     // Upload Data
            'totalBytes' => convert_bytes($row->Download + $row->Upload),
            'dateAdded' => format_date_time($row->StartTime),
            'status' => determine_user_status($row->username),
        ];
        $data[] = $row_data;
    }

    return $data;
}

function count_user_in_out_data($search = '')
{
    // Calculate the start and end date for the current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');

    $query = ORM::for_table('radacct')
        ->where_raw("(DATE(AcctStartTime) BETWEEN ? AND ?)", [$start_date, $end_date])
        ->where('username', $search);

    return $query->count();
}

function create_pagination($page, $perPage, $total)
{
    $pages = ceil($total / $perPage);
    $pagination = [
        'current' => $page,
        'total' => $pages,
        'previous' => ($page > 1) ? $page - 1 : null,
        'next' => ($page < $pages) ? $page + 1 : null,
    ];
    return $pagination;
}

function convert_bytes($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

function format_date_time($datetime)
{
    return date('Y-m-d H:i:s', strtotime($datetime));
}

function determine_user_status($username)
{
    $lastRecord = ORM::for_table('radacct')
        ->where('username', $username)
        ->order_by_desc('AcctStopTime')
        ->find_one();

    if ($lastRecord && $lastRecord->AcctStopTime == 'Start') {
        return '<span class="badge btn-success">Connected</span>';
    } else {
        return '<span class="badge btn-danger">Disconnected</span>';
    }
}
