<?php
ob_start();
session_start();

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/db/connection.php';

$response = ['success' => false, 'message' => 'Error occurred'];

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    $response['message'] = 'User not authenticated';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request';
    echo json_encode($response);
    exit;
}

try {
    $mode      = $_POST['mode']      ?? 'add';
    $record_id = intval($_POST['record_id'] ?? 0);
    $user_id   = $_SESSION['EMP_ID'];

    $ctcr_ref      = trim($_POST['ctfr_ref']               ?? '');
    $timer_type    = trim($_POST['timer_type']             ?? '');
    $page          = trim($_POST['page']                   ?? '');
    $calibrated_by = $_POST['calibrated_by']               ?? '';
    $frequency     = trim($_POST['frequency']              ?? '');
    $date          = $_POST['date']                        ?? '';
    $next_date     = $_POST['next_calibration_date']       ?? '';
    $ref_model     = $_POST['ref_type_model_serial']       ?? '';
    $ref_cal_date  = $_POST['ref_calibration_date']        ?? '';
    $ref_cert      = $_POST['ref_cert_no']                 ?? '';
    $ref_expiry    = $_POST['ref_expiry_date']             ?? '';
    $comments      = $_POST['verification_comments']       ?? '';

    // Validation
    if ($ctcr_ref      == '') throw new Exception("CTCR Reference No required");
    if ($timer_type    == '') throw new Exception("Timer Type required");
    if ($page          == '') throw new Exception("Page required");
    if ($calibrated_by == '') throw new Exception("Calibrated By required");
    if ($frequency     == '') throw new Exception("Frequency required");
    if ($date          == '') throw new Exception("Date required");

    if ($mode === 'add') {

        $initial_status = 'Pending Verification';

        $sql = "
        INSERT INTO CYCLE_TIMER_CALIBRATION (
            CTCR_REFERENCE_NO,
            TIMER_TYPE_MODEL,
            PAGE_NO,
            CALIBRATED_BY,
            FREQUENCY,
            CALIBRATION_DATE,
            NEXT_CALIBRATION_DATE,
            STD_REF_TYPE_MODEL_SERIAL,
            STD_REF_CALIBRATION_DATE,
            STD_REF_CALIBRATION_CERT_NO,
            STD_REF_EXPIRY_DATE,
            COMMENTS,
            CREATED_BY,
            EQUIPMENT_STATUS
        ) VALUES (
            :ctcr_ref,
            :timer_type,
            :page,
            :calibrated_by,
            :frequency,
            TO_DATE(:date_val,      'YYYY-MM-DD'),
            TO_DATE(:next_date,     'YYYY-MM-DD'),
            :ref_model,
            TO_DATE(:ref_cal_date,  'YYYY-MM-DD'),
            :ref_cert,
            TO_DATE(:ref_expiry,    'YYYY-MM-DD'),
            :comments,
            :created_by,
            :equipment_status
        )
        RETURNING ID INTO :new_id
        ";

        $stmt = oci_parse($dbcon, $sql);
        if (!$stmt) throw new Exception("Parse error: " . oci_error($dbcon)['message']);

        oci_bind_by_name($stmt, ":ctcr_ref",        $ctcr_ref);
        oci_bind_by_name($stmt, ":timer_type",       $timer_type);
        oci_bind_by_name($stmt, ":page",             $page);
        oci_bind_by_name($stmt, ":calibrated_by",    $calibrated_by);
        oci_bind_by_name($stmt, ":frequency",        $frequency);
        oci_bind_by_name($stmt, ":date_val",         $date);
        oci_bind_by_name($stmt, ":next_date",        $next_date);
        oci_bind_by_name($stmt, ":ref_model",        $ref_model);
        oci_bind_by_name($stmt, ":ref_cal_date",     $ref_cal_date);
        oci_bind_by_name($stmt, ":ref_cert",         $ref_cert);
        oci_bind_by_name($stmt, ":ref_expiry",       $ref_expiry);
        oci_bind_by_name($stmt, ":comments",         $comments);
        oci_bind_by_name($stmt, ":created_by",       $user_id);
        oci_bind_by_name($stmt, ":equipment_status", $initial_status);
        oci_bind_by_name($stmt, ":new_id",           $master_id, 32);

        if (!oci_execute($stmt)) {
            throw new Exception("Insert error: " . oci_error($stmt)['message']);
        }

    } else {

        $master_id = $record_id;

        $sql = "
        UPDATE CYCLE_TIMER_CALIBRATION SET
            CTCR_REFERENCE_NO           = :ctcr_ref,
            TIMER_TYPE_MODEL            = :timer_type,
            PAGE_NO                     = :page,
            CALIBRATED_BY               = :calibrated_by,
            FREQUENCY                   = :frequency,
            CALIBRATION_DATE            = TO_DATE(:date_val,     'YYYY-MM-DD'),
            NEXT_CALIBRATION_DATE       = TO_DATE(:next_date,    'YYYY-MM-DD'),
            STD_REF_TYPE_MODEL_SERIAL   = :ref_model,
            STD_REF_CALIBRATION_DATE    = TO_DATE(:ref_cal_date, 'YYYY-MM-DD'),
            STD_REF_CALIBRATION_CERT_NO = :ref_cert,
            STD_REF_EXPIRY_DATE         = TO_DATE(:ref_expiry,   'YYYY-MM-DD'),
            COMMENTS                    = :comments,
            UPDATED_BY                  = :updated_by,
            UPDATED_AT                  = SYSDATE
        WHERE ID = :id
        ";

        $stmt = oci_parse($dbcon, $sql);
        if (!$stmt) throw new Exception("Parse error: " . oci_error($dbcon)['message']);

        oci_bind_by_name($stmt, ":ctcr_ref",     $ctcr_ref);
        oci_bind_by_name($stmt, ":timer_type",   $timer_type);
        oci_bind_by_name($stmt, ":page",         $page);
        oci_bind_by_name($stmt, ":calibrated_by",$calibrated_by);
        oci_bind_by_name($stmt, ":frequency",    $frequency);
        oci_bind_by_name($stmt, ":date_val",     $date);
        oci_bind_by_name($stmt, ":next_date",    $next_date);
        oci_bind_by_name($stmt, ":ref_model",    $ref_model);
        oci_bind_by_name($stmt, ":ref_cal_date", $ref_cal_date);
        oci_bind_by_name($stmt, ":ref_cert",     $ref_cert);
        oci_bind_by_name($stmt, ":ref_expiry",   $ref_expiry);
        oci_bind_by_name($stmt, ":comments",     $comments);
        oci_bind_by_name($stmt, ":updated_by",   $user_id);
        oci_bind_by_name($stmt, ":id",           $master_id);

        if (!oci_execute($stmt)) {
            throw new Exception("Update error: " . oci_error($stmt)['message']);
        }

        // Remove old child rows before re-inserting
        $del = oci_parse($dbcon, "DELETE FROM CYCLE_MASTER_TIMER WHERE CTCR_ID = :id");
        oci_bind_by_name($del, ":id", $master_id);
        if (!oci_execute($del)) {
            throw new Exception("Delete child rows error: " . oci_error($del)['message']);
        }
    }

    // ===== INSERT MASTER TIMER ROWS =====
    $settings = $_POST['master_timer_setting'] ?? [];
    for ($i = 0; $i < count($settings); $i++) {

        $sql2 = "
        INSERT INTO CYCLE_MASTER_TIMER (
            CTCR_ID, TIMER_SETTING, IS_LABEL_ROW, ROW_ORDER,
            READING_1, DEV_1, READING_2, DEV_2, READING_3, DEV_3,
            REMARKS
        ) VALUES (
            :ctcr_id, :setting, :is_label_row, :row_order,
            :r1, :d1, :r2, :d2, :r3, :d3,
            :remarks
        )";

        $stmt2 = oci_parse($dbcon, $sql2);
        if (!$stmt2) throw new Exception("Parse error (child row): " . oci_error($dbcon)['message']);

        $row_order = $i + 1;
        $is_label  = $_POST['master_is_label'][$i]   ?? 'N';
        $setting   = $settings[$i]                   ?? '';
        $r1        = $_POST['master_r1'][$i]         ?? '';
        $d1        = $_POST['master_d1'][$i]         ?? '';
        $r2        = $_POST['master_r2'][$i]         ?? '';
        $d2        = $_POST['master_d2'][$i]         ?? '';
        $r3        = $_POST['master_r3'][$i]         ?? '';
        $d3        = $_POST['master_d3'][$i]         ?? '';
        $remarks   = $_POST['master_remarks'][$i]    ?? '';

        oci_bind_by_name($stmt2, ":ctcr_id",      $master_id);
        oci_bind_by_name($stmt2, ":setting",      $setting);
        oci_bind_by_name($stmt2, ":is_label_row", $is_label);
        oci_bind_by_name($stmt2, ":row_order",    $row_order);
        oci_bind_by_name($stmt2, ":r1",           $r1);
        oci_bind_by_name($stmt2, ":d1",           $d1);
        oci_bind_by_name($stmt2, ":r2",           $r2);
        oci_bind_by_name($stmt2, ":d2",           $d2);
        oci_bind_by_name($stmt2, ":r3",           $r3);
        oci_bind_by_name($stmt2, ":d3",           $d3);
        oci_bind_by_name($stmt2, ":remarks",      $remarks);

        if (!oci_execute($stmt2)) {
            throw new Exception("Insert child row error: " . oci_error($stmt2)['message']);
        }
    }

    $response['success']   = true;
    $response['master_id'] = $master_id;
    $response['message']   = ($mode === 'add')
        ? 'Saved successfully. Record is now Pending Verification.'
        : 'Updated successfully.';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;