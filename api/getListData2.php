<?php
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

// Your original DataTables parameters - UNCHANGED
$draw             = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start            = isset($_POST['start'])  ? intval($_POST['start'])  : 0;
$length           = isset($_POST['length']) ? intval($_POST['length']) : 10;
$orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
$orderDir         = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

// Your original column mapping - UNCHANGED
$columnMapping = [
    0 => "ct.ID",
    1 => "ct.CTCR_REFERENCE_NO",
    2 => "ct.TIMER_TYPE_MODEL",
    3 => "ct.STD_REF_TYPE_MODEL_SERIAL",
    4 => "ct.STD_REF_CALIBRATION_CERT_NO",
    5 => "ct.CALIBRATION_DATE",
    6 => "ct.CREATED_BY",
    7 => "ct.CREATED_AT",
    8 => "ct.VERIFIED_BY",
    9 => "ct.APPROVED_BY",
    10 => "ct.EQUIPMENT_STATUS"
];
$orderColumn = $columnMapping[$orderColumnIndex] ?? "ct.ID";

// DUMMY DATA - REPLACES DATABASE QUERIES
$dummyRecords = [
    ['ID'=>1001, 'CTCR_REFERENCE_NO'=>'CTCR-2024-001', 'TIMER_TYPE_MODEL'=>'Omron H3CR-A8', 'STD_REF_TYPE_MODEL_SERIAL'=>'SN:OMR-456789', 'STD_REF_CALIBRATION_CERT_NO'=>'CC-2024-001', 'CALIBRATION_DATE'=>'15/11/2024', 'CREATED_BY'=>'John Doe', 'CREATED_AT'=>'14/11/2024 09:30', 'VERIFIED_BY'=>'Jane Smith', 'APPROVED_BY'=>'Manager Lee', 'EQUIPMENT_STATUS'=>'Pending Approval'],
    ['ID'=>1002, 'CTCR_REFERENCE_NO'=>'CTCR-2024-002', 'TIMER_TYPE_MODEL'=>'Siemens 3RP1525', 'STD_REF_TYPE_MODEL_SERIAL'=>'SN:SI-123456', 'STD_REF_CALIBRATION_CERT_NO'=>'CC-2024-002', 'CALIBRATION_DATE'=>'12/11/2024', 'CREATED_BY'=>'Alice Tan', 'CREATED_AT'=>'11/11/2024 14:15', 'VERIFIED_BY'=>null, 'APPROVED_BY'=>null, 'EQUIPMENT_STATUS'=>'Draft'],
    ['ID'=>1003, 'CTCR_REFERENCE_NO'=>'CTCR-2024-003', 'TIMER_TYPE_MODEL'=>'ABB CT-ARS.21', 'STD_REF_TYPE_MODEL_SERIAL'=>'SN:ABB-789012', 'STD_REF_CALIBRATION_CERT_NO'=>'CC-2024-003', 'CALIBRATION_DATE'=>'10/11/2024', 'CREATED_BY'=>'Bob Wilson', 'CREATED_AT'=>'09/11/2024 16:45', 'VERIFIED_BY'=>'Sarah Kong', 'APPROVED_BY'=>'Manager Lee', 'EQUIPMENT_STATUS'=>'Approved'],
    ['ID'=>1004, 'CTCR_REFERENCE_NO'=>'CTCR-2024-004', 'TIMER_TYPE_MODEL'=>'Schneider RE17RAMU', 'STD_REF_TYPE_MODEL_SERIAL'=>'SN:SCH-345678', 'STD_REF_CALIBRATION_CERT_NO'=>'CC-2024-004', 'CALIBRATION_DATE'=>'08/11/2024', 'CREATED_BY'=>'Mike Chen', 'CREATED_AT'=>'07/11/2024 11:20', 'VERIFIED_BY'=>'Lisa Tan', 'APPROVED_BY'=>null, 'EQUIPMENT_STATUS'=>'Rejected'],
    ['ID'=>1005, 'CTCR_REFERENCE_NO'=>'CTCR-2024-005', 'TIMER_TYPE_MODEL'=>'Eaton RM17TA32MW', 'STD_REF_TYPE_MODEL_SERIAL'=>'SN:EAT-567890', 'STD_REF_CALIBRATION_CERT_NO'=>'CC-2024-005', 'CALIBRATION_DATE'=>'05/11/2024', 'CREATED_BY'=>'David Lim', 'CREATED_AT'=>'04/11/2024 13:10', 'VERIFIED_BY'=>'Emma Wong', 'APPROVED_BY'=>'Manager Lee', 'EQUIPMENT_STATUS'=>'Approved']
];

// SIMULATE FILTERS (Your original filter logic)
$status_filter = $_POST['status_filter'] ?? '';
$use_status_filter = !empty($status_filter);
$filteredData = $dummyRecords;
if ($use_status_filter) {
    $filteredData = array_filter($filteredData, fn($row) => $row['EQUIPMENT_STATUS'] === $status_filter);
}

// SIMULATE PAGINATION & TOTAL COUNT (Your original logic)
$totalRecords = count($dummyRecords);
$recordsFiltered = count($filteredData);
$paginatedData = array_slice($filteredData, $start, $length);

// Your original results building - UNCHANGED
$results = [];
foreach ($paginatedData as $row) {
    $encodedId = base64_encode($row['ID']);
    $status = $row['EQUIPMENT_STATUS'] ?? 'Draft';
    $locked = in_array($status, ['Approved', 'Rejected']);

    $links = "<a href='add.php?mode=view&id={$encodedId}' class='btn btn-sm btn-info me-4' title='View details'><i class='fa-solid fa-eye'></i>VIEW</a>";

    if (!$locked) {
        $links .= "<a href='add.php?mode=edit&id={$encodedId}' class='btn btn-sm btn-secondary me-4' title='Edit details'><i class='fa-solid fa-pen-to-square'></i>EDIT</a>";
        $links .= "<a href='add.php?mode=approve&id={$encodedId}' class='btn btn-sm btn-success me-4' title='Approve'><i class='fa-solid fa-circle-check'></i>APPROVE</a>";
    }

    $links .= "<a href='pdfGenerate.php?id={$encodedId}' target='_blank' class='btn btn-sm btn-danger' title='PDF'><i class='fa-solid fa-file-pdf'></i>PDF</a>";

    $results[] = [
        "ID" => $row['ID'],
        "CTCR_REFERENCE_NO" => htmlspecialchars($row['CTCR_REFERENCE_NO'] ?? ''),
        "TIMER_TYPE_MODEL" => htmlspecialchars($row['TIMER_TYPE_MODEL'] ?? ''),
        "STD_REF_TYPE_MODEL_SERIAL" => htmlspecialchars($row['STD_REF_TYPE_MODEL_SERIAL'] ?? ''),
        "STD_REF_CALIBRATION_CERT_NO" => htmlspecialchars($row['STD_REF_CALIBRATION_CERT_NO'] ?? ''),
        "CALIBRATION_DATE" => htmlspecialchars($row['CALIBRATION_DATE'] ?? ''),
        "CREATED_BY" => htmlspecialchars($row['CREATED_BY'] ?? ''),
        "CREATED_AT" => htmlspecialchars($row['CREATED_AT'] ?? ''),
        "VERIFIED_BY" => htmlspecialchars($row['VERIFIED_BY'] ?? ''),
        "APPROVED_BY" => htmlspecialchars($row['APPROVED_BY'] ?? ''),
        "ACTION" => $links,
    ];
}

// Your original JSON response - UNCHANGED
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $recordsFiltered,
    "data" => $results,
]);
exit;
?>