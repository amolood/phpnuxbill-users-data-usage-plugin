<?php
register_menu("User Data Usage", true, "UserDataUsageAdmin",
    'SERVICES', '');

function UserDataUsageAdmin()
{
    global $ui;
    _admin();
    $ui->assign('_title', 'User Data Usage');
    $ui->assign('_system_menu', '');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $search = $_POST['q'] ?? '';
    $page = !isset($_GET['page']) ? 1 : (int)$_GET['page'];
    $perPage = 10;

    $total = UserDataUsageAdmin_count_user_in_out_data_admin($search);

    if (is_string($total)) {
        r2(U . "dashboard", 'e', $total);
        return;
    }

    $data = UserDataUsageAdmin_fetch_user_in_out_data_admin($search, $page, $perPage);
    $pagination = UserDataUsageAdmin_create_pagination_admin($page, $perPage, $total);

    $ui->assign('q', $search);
    $ui->assign('data', $data);
    $ui->assign('pagination', $pagination);
    $ui->display('data_usage_admin.tpl');
}


function UserDataUsageAdmin_fetch_user_in_out_data_admin($search = '', $page = 1, $perPage = 10)
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
        $row->acctOutputOctets = UserDataUsageAdmin_convert_bytes_admin(floatval($row->acctoutputoctets));
        $row->acctInputOctets = UserDataUsageAdmin_convert_bytes_admin(floatval($row->acctinputoctets));
        $row->totalBytes = UserDataUsageAdmin_convert_bytes_admin(floatval($row->acctoutputoctets) + floatval($row->acctinputoctets));

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

        // Set the start date
        $row->sdate = isset($lastRecord['dateAdded']) ? $lastRecord['dateAdded'] : $lastRecord['acctstarttime'];
    }

    return $data;
}




function UserDataUsageAdmin_count_user_in_out_data_admin($search = '')
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


function UserDataUsageAdmin_create_pagination_admin($page, $perPage, $total)
{
    $pages = ceil($total / $perPage);
    return [
        'current' => $page,
        'total' => $pages,
        'previous' => ($page > 1) ? $page - 1 : null,
        'next' => ($page < $pages) ? $page + 1 : null,
    ];
}

function UserDataUsageAdmin_convert_bytes_admin($bytes)
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