<?php

register_menu("User Data Usage", false, "UserDataUsage", 'AFTER_DASHBOARD', 'fa fa-bar-chart');

function UserDataUsage()
{
    global $ui;
    $ui->assign('_title', 'User Data Usage');
    $ui->assign('_system_menu', '');
    $user = User::_info();
    $ui->assign('_user', $user);
    $search = $user['username'];
    $page = !isset($_GET['page']) ? 1 : (int)$_GET['page'];
    $perPage = 10;
	
	$total = UserDataUsage_count_user_in_out_data($search);

    if (is_string($total)) {
        r2(U . "home", 'e', $total);
        return;
    }

    $data = UserDataUsage_fetch_user_in_out_data($search, $page, $perPage);
    $pagination = UserDataUsage_create_pagination($page, $perPage, $total);

    $ui->assign('q', $search);
    $ui->assign('data', $data);
    $ui->assign('pagination', $pagination);
    $ui->display('data_usage_user.tpl');
}

function UserDataUsage_fetch_user_in_out_data($search = '', $page = 1, $perPage = 10)
{
    // Check for the existence of the table and initialize query accordingly
    $table = isTableExist('rad_acct') ? 'rad_acct' : 'radacct';
    $query = ORM::for_table($table)->where_not_equal('acctoutputoctets', 0);

    // Handle search functionality
    if ($search) {
        $query->where_like('username', '%' . $search . '%');
    }

    // Apply pagination limits
    $query->limit($perPage)->offset(($page - 1) * $perPage);
    $data = Paginator::findMany($query, [], $perPage);

    // Processing each record
    foreach ($data as &$row) {
        // Converting octet values into readable formats
        $row->acctOutputOctets = UserDataUsage_convert_bytes(floatval($row->acctoutputoctets));
        $row->acctInputOctets = UserDataUsage_convert_bytes(floatval($row->acctinputoctets));
        $row->totalBytes = UserDataUsage_convert_bytes(floatval($row->acctoutputoctets) + floatval($row->acctinputoctets));

        // Fetch the last record for status determination
        $lastRecord = ORM::for_table($table)
            ->where('username', $row->username)
            ->where_not_equal('acctoutputoctets', 0)
            ->order_by_desc($table === 'rad_acct' ? 'acctstatustype' : 'acctstoptime')
            ->find_one();

        // Set connection status based on the last record's type
        if ($lastRecord && ($lastRecord->acctstatustype === 'Start' || $lastRecord->acctstoptime === null)) {
            $row->status = '<span class="badge btn-success">Connected</span>';
        } else {
            $row->status = '<span class="badge btn-danger">Disconnected</span>';
        }
		$row->sdate = isset($lastRecord['dateAdded']) ? $lastRecord['dateAdded'] : $lastRecord['acctstarttime'];
    }

    return $data;
}

function UserDataUsage_count_user_in_out_data($search = '')
{
    // Check for the existence of the tables and initialize query accordingly
    $tables = ['rad_acct', 'radacct']; // Add more table names here if needed
    $query = null;
    foreach ($tables as $table) {
        if (isTableExist($table)) {
            $query = ORM::for_table($table)->where_not_equal('acctoutputoctets', 0.00);
            break;
        }
    }

    // If no table exists, return an error message
    if ($query === null) {
        return "Error: No valid table found in the database.";
    }

    // Apply search filter if applicable
    if ($search) {
        $query->where_like('username', '%' . $search . '%');
    }

    // Return the total count of records
    $count = $query->count();
    if ($count === false) {
        return "Error: Unable to retrieve the count of records.";
    }
    return $count;
}

function UserDataUsage_create_pagination($page, $perPage, $total)
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

function UserDataUsage_convert_bytes($bytes, $format = false)
{
    if ($bytes >= 1073741824) {
        $value = $bytes / 1073741824;
        $unit = 'GB';
    } elseif ($bytes >= 1048576) {
        $value = $bytes / 1048576;
        $unit = 'MB';
    } elseif ($bytes >= 1024) {
        $value = $bytes / 1024;
        $unit = 'KB';
    } else {
        $value = $bytes;
        $unit = 'bytes';
    }

    if ($format) {
        return number_format($value, 2) . ' ' . $unit;
    } else {
        return number_format($value, 2); // Return numeric value only
    }
}

