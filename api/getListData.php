<?php
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/constant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db/connection.php';
require_once 'common.php';

// DataTables parameters
$draw             = isset($_POST['draw'])               ? intval($_POST['draw'])   : 1;
$start            = isset($_POST['start'])              ? intval($_POST['start'])  : 0;
$length           = isset($_POST['length'])             ? intval($_POST['length']) : 10;
$orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
$orderDir         = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$columnMapping = [
    0  => "ct.ID",
    1  => "ct.CTCR_REFERENCE_NO",
    2  => "ct.TIMER_TYPE_MODEL",
    3  => "ct.STD_REF_TYPE_MODEL_SERIAL",
    4  => "ct.STD_REF_CALIBRATION_CERT_NO",
    5  => "ct.CALIBRATION_DATE",
    6  => "ct.CREATED_BY",
    7  => "ct.CREATED_AT",
    8  => "ct.EQUIPMENT_STATUS",
];
$orderColumn = $columnMapping[$orderColumnIndex] ?? "ct.ID";

$employee_id   = isset($_SESSION['EMP_ID']) ? trim($_SESSION['EMP_ID']) : '';
$status_filter = isset($_POST['status_filter']) ? trim($_POST['status_filter']) : '';
$date_from     = isset($_POST['date_from'])     ? trim($_POST['date_from'])     : '';
$date_to       = isset($_POST['date_to'])       ? trim($_POST['date_to'])       : '';

$date_pattern  = '/^\d{4}-\d{2}-\d{2}$/';
$use_date_from = !empty($date_from) && preg_match($date_pattern, $date_from);
$use_date_to   = !empty($date_to)   && preg_match($date_pattern, $date_to);

// ── Determine role flags (used for action button rendering only) ──────────────
// All authenticated users can see ALL records in the list.
// Action buttons (EDIT / VERIFY / APPROVE) are still role-gated per row.
$is_verifier = Common::is_verifier($employee_id);
$is_approver = Common::is_approver($employee_id);

// Visibility: every logged-in user sees every record.
// If no valid session exists, show nothing.
$visibility_clause = !empty($employee_id) ? '1=1' : '1=0';

// Additional filters on top of visibility
$filter_parts = ["1=1", $visibility_clause];
if (!empty($status_filter)) {
    $filter_parts[] = "ct.EQUIPMENT_STATUS = :status_filter";
}
if ($use_date_from) {
    $filter_parts[] = "ct.CALIBRATION_DATE >= TO_DATE(:date_from, 'YYYY-MM-DD')";
}
if ($use_date_to) {
    $filter_parts[] = "ct.CALIBRATION_DATE <= TO_DATE(:date_to, 'YYYY-MM-DD')";
}
$where_clause = implode(" AND ", $filter_parts);

// Bind all parameters
// Note: :emp_id_creator removed — all authenticated users see all records.
function bindAllParams($stmt, $employee_id, $is_verifier, $is_approver,
                        $status_filter, $use_date_from, $date_from,
                        $use_date_to, $date_to) {
    if (!empty($status_filter)) {
        oci_bind_by_name($stmt, ":status_filter", $status_filter);
    }
    if ($use_date_from) {
        oci_bind_by_name($stmt, ":date_from", $date_from);
    }
    if ($use_date_to) {
        oci_bind_by_name($stmt, ":date_to", $date_to);
    }
}

// ── TOTAL COUNT ───────────────────────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS TOTAL_COUNT FROM CYCLE_TIMER_CALIBRATION ct WHERE $where_clause";
$count_stmt = oci_parse($dbcon, $count_sql);
bindAllParams($count_stmt, $employee_id, $is_verifier, $is_approver,
              $status_filter, $use_date_from, $date_from, $use_date_to, $date_to);
oci_execute($count_stmt);
oci_fetch($count_stmt);
$totalRecords = (int) oci_result($count_stmt, "TOTAL_COUNT");
oci_free_statement($count_stmt);

// ── PAGINATED DATA ────────────────────────────────────────────────────────────
$paginated_query = "
    SELECT * FROM (
        SELECT
            ct.ID,
            ct.CTCR_REFERENCE_NO,
            ct.TIMER_TYPE_MODEL,
            ct.STD_REF_TYPE_MODEL_SERIAL,
            ct.STD_REF_CALIBRATION_CERT_NO,
            TO_CHAR(ct.CALIBRATION_DATE,      'DD/MM/YYYY')   AS CALIBRATION_DATE,
            ct.CREATED_BY,
            TO_CHAR(ct.CREATED_AT,            'DD/MM/YYYY HH24:MI') AS CREATED_AT,
            ct.EQUIPMENT_STATUS,
            (SELECT EMPLOYEE_NAME FROM EMPLOYEE_MASTER WHERE EMP_ID = ct.CREATED_BY AND ROWNUM = 1) AS CREATED_BY_NAME,
            ROW_NUMBER() OVER (ORDER BY $orderColumn $orderDir) AS RN
        FROM CYCLE_TIMER_CALIBRATION ct
        WHERE $where_clause
    )
    WHERE RN BETWEEN :startrow + 1 AND :endrow
    ORDER BY RN
";

$endrow   = $start + $length;
$startrow = $start;

$stmt = oci_parse($dbcon, $paginated_query);
oci_bind_by_name($stmt, ":startrow", $startrow);
oci_bind_by_name($stmt, ":endrow",   $endrow);
bindAllParams($stmt, $employee_id, $is_verifier, $is_approver,
              $status_filter, $use_date_from, $date_from, $use_date_to, $date_to);

$exec = oci_execute($stmt);
if (!$exec) {
    $err = oci_error($stmt);
    echo json_encode(['error' => $err['message'] ?? 'Query failed']);
    exit;
}

// ── STATUS BADGE ──────────────────────────────────────────────────────────────
function statusBadge($status) {
    $map = [
        'Draft'                => ['bg' => '#6c757d', 'color' => '#fff'],
        'Pending Verification' => ['bg' => '#17a2b8', 'color' => '#fff'],
        'Pending Approval'     => ['bg' => '#ffc107', 'color' => '#212529'],
        'Approved'             => ['bg' => '#28a745', 'color' => '#fff'],
        'Rejected'             => ['bg' => '#dc3545', 'color' => '#fff'],
    ];
    $cfg   = $map[$status] ?? ['bg' => '#6c757d', 'color' => '#fff'];
    $style = "display:inline-block;padding:5px 14px;border-radius:20px;font-size:12px;"
           . "font-weight:600;color:{$cfg['color']};background:{$cfg['bg']};white-space:nowrap;";
    return "<span style='{$style}'>" . htmlspecialchars($status ?? 'Unknown') . "</span>";
}

// ── BUILD ROWS ────────────────────────────────────────────────────────────────
$results  = [];
$base_url = BASE_URL;

while (($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {

    $encodedId = base64_encode($row['ID']);
    $status    = $row['EQUIPMENT_STATUS'] ?? 'Draft';
    $isLocked  = in_array($status, ['Approved', 'Rejected']);

    // VIEW — always
    $links = "<a href='{$base_url}/cycle_timer_calibration/add.php?mode=view&id={$encodedId}' "
           . "class='btn btn-sm btn-info me-1'>VIEW</a> ";

    // EDIT — creator only, and only while still Pending Verification (not yet actioned)
    if (!empty($employee_id) && $row['CREATED_BY'] == $employee_id && $status === 'Pending Verification') {
        $links .= "<a href='{$base_url}/cycle_timer_calibration/add.php?mode=edit&id={$encodedId}' "
                . "class='btn btn-sm btn-secondary me-1'>EDIT</a> ";
    }

    // VERIFY — any configured verifier, status must be Pending Verification
    if ($is_verifier && $status === 'Pending Verification') {
        $links .= "<a href='{$base_url}/cycle_timer_calibration/add.php?mode=verify&id={$encodedId}' "
                . "class='btn btn-sm btn-primary me-1'>VERIFY</a> ";
    }

    // APPROVE — any configured approver, status must be Pending Approval
    if ($is_approver && $status === 'Pending Approval') {
        $links .= "<a href='{$base_url}/cycle_timer_calibration/add.php?mode=approve&id={$encodedId}' "
                . "class='btn btn-sm btn-warning me-1'>APPROVE</a> ";
    }

    // PDF — always
    $links .= "<a href='{$base_url}/cycle_timer_calibration/pdfGenerate.php?id={$encodedId}' "
            . "target='_blank' class='btn btn-sm btn-danger'>PDF</a>";

    $results[] = [
        "ID"                          => $row['ID'],
        "CTCR_REFERENCE_NO"           => htmlspecialchars($row['CTCR_REFERENCE_NO']           ?? ''),
        "TIMER_TYPE_MODEL"            => htmlspecialchars($row['TIMER_TYPE_MODEL']            ?? ''),
        "STD_REF_TYPE_MODEL_SERIAL"   => htmlspecialchars($row['STD_REF_TYPE_MODEL_SERIAL']   ?? ''),
        "STD_REF_CALIBRATION_CERT_NO" => htmlspecialchars($row['STD_REF_CALIBRATION_CERT_NO'] ?? ''),
        "CALIBRATION_DATE"            => htmlspecialchars($row['CALIBRATION_DATE']            ?? ''),
        "CREATED_BY"                  => htmlspecialchars($row['CREATED_BY_NAME'] ?? $row['CREATED_BY'] ?? ''),
        "CREATED_AT"                  => htmlspecialchars($row['CREATED_AT']                  ?? ''),
        "EQUIPMENT_STATUS"            => statusBadge($status),
        "ACTION"                      => $links,
    ];
}

oci_free_statement($stmt);

echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data"            => $results,
], JSON_UNESCAPED_UNICODE);
exit;