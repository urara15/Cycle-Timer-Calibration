<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require $_SERVER['DOCUMENT_ROOT'] . '/common/EmailHelper.php';

use Common\Email\EmailHelper;

class Common
{
    private static $dbcon = null;

    private static function getDb()
    {
        if (self::$dbcon === null) {
            include $_SERVER['DOCUMENT_ROOT'] . '/db/connection.php';
            self::$dbcon = $dbcon;
        }
        return self::$dbcon;
    }


    // ─── Employee list (for select2 search) ──────────────────────────────────
    public static function employee_list($emp_no = '')
    {
        $dbcon = self::getDb();

        $tmpQuery = "SELECT DISTINCT emp_id,
                            NVL2(emp_short_name, emp_short_name, employee_name) AS employee_display_name,
                            employee_name,
                            email_id,
                            department,
                            emp_short_name
                    FROM employee_master
                    WHERE NVL(emp_status,'N') = 'N'
                    AND NVL(virtual_flag,'N') = 'N'";

        if ($emp_no) {
            $tmpQuery .= " AND (emp_id LIKE :searchTerm OR LOWER(employee_name) LIKE LOWER(:searchTerm))";
        }

        $tmpQuery .= " ORDER BY employee_name FETCH FIRST 20 ROWS ONLY";

        $query = oci_parse($dbcon, $tmpQuery);

        if ($emp_no) {
            $likeTerm = '%' . $emp_no . '%';
            oci_bind_by_name($query, ':searchTerm', $likeTerm);
        }

        oci_execute($query);

        $allRecords = [];
        while (($row = oci_fetch_array($query, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {
            $allRecords[] = $row;
        }

        return $allRecords;
    }


    // ─── Check if an employee is a configured VERIFIER ───────────────────────
    public static function is_verifier($emp_id)
    {
        if (empty($emp_id)) return false;

        $dbcon = self::getDb();

        $sql  = "SELECT COUNT(*) AS CNT
                 FROM CYCLE_TIMER_CONFIG
                 WHERE POSITION_TYPE = 'cycle_timer'
                 AND VERIFIED_BY     = :emp_id";

        $stmt = oci_parse($dbcon, $sql);
        oci_bind_by_name($stmt, ":emp_id", $emp_id);
        oci_execute($stmt);

        $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stmt);

        return (int)($row['CNT'] ?? 0) > 0;
    }


    // ─── Check if an employee is a configured APPROVER ───────────────────────
    public static function is_approver($emp_id)
    {
        if (empty($emp_id)) return false;

        $dbcon = self::getDb();

        $sql  = "SELECT COUNT(*) AS CNT
                 FROM CYCLE_TIMER_CONFIG
                 WHERE POSITION_TYPE = 'cycle_timer'
                 AND APPROVED_BY     = :emp_id";

        $stmt = oci_parse($dbcon, $sql);
        oci_bind_by_name($stmt, ":emp_id", $emp_id);
        oci_execute($stmt);

        $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stmt);

        return (int)($row['CNT'] ?? 0) > 0;
    }


    // ─── Get full record by ID ────────────────────────────────────────────────
    public static function view_ctcr_by_id($record_id = '')
    {
        if (!$record_id) return [];

        $dbcon = self::getDb();

        // ── Master header ────────────────────────────────────────────────────
        $sql_master = "
            SELECT
                ct.ID,
                ct.CTCR_REFERENCE_NO            AS CTFR_REF,
                ct.TIMER_TYPE_MODEL             AS TIMER_TYPE,
                ct.PAGE_NO                      AS PAGE,
                ct.CALIBRATED_BY,
                calib.EMPLOYEE_NAME             AS CALIBRATED_BY_NAME,
                ct.FREQUENCY,
                TO_CHAR(ct.CALIBRATION_DATE,      'YYYY-MM-DD') AS INSPECTION_DATE_RAW,
                TO_CHAR(ct.NEXT_CALIBRATION_DATE, 'YYYY-MM-DD') AS NEXT_CALIBRATION_DATE,
                ct.STD_REF_TYPE_MODEL_SERIAL    AS REF_TYPE_MODEL_SERIAL,
                TO_CHAR(ct.STD_REF_CALIBRATION_DATE, 'YYYY-MM-DD') AS REF_CALIBRATION_DATE,
                ct.STD_REF_CALIBRATION_CERT_NO  AS REF_CERT_NO,
                TO_CHAR(ct.STD_REF_EXPIRY_DATE, 'YYYY-MM-DD')  AS REF_EXPIRY_DATE,
                ct.COMMENTS                     AS VERIFICATION_COMMENTS,
                ct.EQUIPMENT_STATUS,
                ct.CREATED_BY,
                creator.EMPLOYEE_NAME           AS CREATED_BY_NAME,
                TO_CHAR(ct.CREATED_AT, 'DD/MM/YYYY HH24:MI')   AS CREATED_AT,
                ct.UPDATED_BY,
                updater.EMPLOYEE_NAME           AS UPDATED_BY_NAME,
                TO_CHAR(ct.UPDATED_AT, 'DD/MM/YYYY HH24:MI')   AS UPDATED_AT
            FROM CYCLE_TIMER_CALIBRATION ct
            LEFT JOIN EMPLOYEE_MASTER calib   ON calib.EMP_ID   = ct.CALIBRATED_BY
            LEFT JOIN EMPLOYEE_MASTER creator ON creator.EMP_ID = ct.CREATED_BY
            LEFT JOIN EMPLOYEE_MASTER updater ON updater.EMP_ID = ct.UPDATED_BY
            WHERE ct.ID = :ID
        ";

        $master_id_bind = (int) $record_id;
        $stmt = oci_parse($dbcon, $sql_master);
        oci_bind_by_name($stmt, ":ID", $master_id_bind);
        oci_execute($stmt);

        $master = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stmt);

        if (!$master) return [];

        // ── Fetch VERIFICATION action columns ────────────────────────────────
        $verification_extra = [
            'VERIFICATION_ACTION_BY'      => null,
            'VERIFICATION_ACTION_BY_NAME' => null,
            'VERIFICATION_ACTION_AT'      => null,
            'VERIFICATION_REMARKS'        => null,
        ];
        $sql_verif = "
            SELECT ct.VERIFICATION_ACTION_BY,
                   TO_CHAR(ct.VERIFICATION_ACTION_AT, 'DD/MM/YYYY HH24:MI') AS VERIFICATION_ACTION_AT,
                   ct.VERIFICATION_REMARKS,
                   emp.EMPLOYEE_NAME AS VERIFICATION_ACTION_BY_NAME
            FROM   CYCLE_TIMER_CALIBRATION ct
            LEFT JOIN EMPLOYEE_MASTER emp ON emp.EMP_ID = ct.VERIFICATION_ACTION_BY
            WHERE  ct.ID = :ID
        ";
        $stmt_v = @oci_parse($dbcon, $sql_verif);
        if ($stmt_v) {
            $id_v = (int) $record_id;
            oci_bind_by_name($stmt_v, ":ID", $id_v);
            $ok = @oci_execute($stmt_v);
            if ($ok) {
                $row_v = oci_fetch_array($stmt_v, OCI_ASSOC + OCI_RETURN_NULLS);
                if ($row_v) {
                    $verification_extra['VERIFICATION_ACTION_BY']      = $row_v['VERIFICATION_ACTION_BY']      ?? null;
                    $verification_extra['VERIFICATION_ACTION_BY_NAME'] = $row_v['VERIFICATION_ACTION_BY_NAME'] ?? null;
                    $verification_extra['VERIFICATION_ACTION_AT']      = $row_v['VERIFICATION_ACTION_AT']      ?? null;
                    $verification_extra['VERIFICATION_REMARKS']        = $row_v['VERIFICATION_REMARKS']        ?? null;
                }
            }
            oci_free_statement($stmt_v);
        }
        $master = array_merge($master, $verification_extra);

        // ── Fetch APPROVAL action columns ────────────────────────────────────
        $approval_extra = [
            'APPROVAL_ACTION_BY'      => null,
            'APPROVAL_ACTION_BY_NAME' => null,
            'APPROVAL_ACTION_AT'      => null,
            'APPROVAL_REMARKS'        => null,
        ];
        $sql_appr = "
            SELECT ct.APPROVAL_ACTION_BY,
                   TO_CHAR(ct.APPROVAL_ACTION_AT, 'DD/MM/YYYY HH24:MI') AS APPROVAL_ACTION_AT,
                   ct.APPROVAL_REMARKS,
                   emp.EMPLOYEE_NAME AS APPROVAL_ACTION_BY_NAME
            FROM   CYCLE_TIMER_CALIBRATION ct
            LEFT JOIN EMPLOYEE_MASTER emp ON emp.EMP_ID = ct.APPROVAL_ACTION_BY
            WHERE  ct.ID = :ID
        ";
        $stmt_a = @oci_parse($dbcon, $sql_appr);
        if ($stmt_a) {
            $id_a = (int) $record_id;
            oci_bind_by_name($stmt_a, ":ID", $id_a);
            $ok = @oci_execute($stmt_a);
            if ($ok) {
                $row_a = oci_fetch_array($stmt_a, OCI_ASSOC + OCI_RETURN_NULLS);
                if ($row_a) {
                    $approval_extra['APPROVAL_ACTION_BY']      = $row_a['APPROVAL_ACTION_BY']      ?? null;
                    $approval_extra['APPROVAL_ACTION_BY_NAME'] = $row_a['APPROVAL_ACTION_BY_NAME'] ?? null;
                    $approval_extra['APPROVAL_ACTION_AT']      = $row_a['APPROVAL_ACTION_AT']      ?? null;
                    $approval_extra['APPROVAL_REMARKS']        = $row_a['APPROVAL_REMARKS']        ?? null;
                }
            }
            oci_free_statement($stmt_a);
        }
        $master = array_merge($master, $approval_extra);

        // ── Master timer rows ────────────────────────────────────────────────
        $sql_rows = "
            SELECT IS_LABEL_ROW, TIMER_SETTING,
                   READING_1 AS R1, DEV_1 AS D1,
                   READING_2 AS R2, DEV_2 AS D2,
                   READING_3 AS R3, DEV_3 AS D3,
                   REMARKS
            FROM   CYCLE_MASTER_TIMER
            WHERE  CTCR_ID = :CTCR_ID
            ORDER BY ROW_ORDER
        ";

        $ctcr_id_bind = (int) $record_id;
        $stmt2 = oci_parse($dbcon, $sql_rows);
        oci_bind_by_name($stmt2, ":CTCR_ID", $ctcr_id_bind);
        $exec2 = oci_execute($stmt2);

        $rows = [];
        if ($exec2) {
            while (($row = oci_fetch_array($stmt2, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {
                $rows[] = [
                    'is_label_row'  => $row['IS_LABEL_ROW']  ?? 'N',
                    'timer_setting' => $row['TIMER_SETTING']  ?? '',
                    'r1'            => $row['R1']             ?? '',
                    'd1'            => $row['D1']             ?? '',
                    'r2'            => $row['R2']             ?? '',
                    'd2'            => $row['D2']             ?? '',
                    'r3'            => $row['R3']             ?? '',
                    'd3'            => $row['D3']             ?? '',
                    'remarks'       => $row['REMARKS']        ?? '',
                ];
            }
        }
        oci_free_statement($stmt2);

        if (empty($rows)) {
            $rows = [
                ['is_label_row' => 'Y', 'timer_setting' => 'STD REF', 'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
                ['is_label_row' => 'N', 'timer_setting' => '',         'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
                ['is_label_row' => 'N', 'timer_setting' => '',         'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
            ];
        }

        $master['master_timer_rows'] = $rows;
        return $master;
    }

    // Alias — keeps any existing callers working
    public static function view_vqf_by_id($record_id = '')
    {
        return self::view_ctcr_by_id($record_id);
    }


    // ─── PDF Generate ────────────────────────────────────────────────────────
    public static function pdf_generate($record_id = '', $type = 'I')
    {
        ini_set('pcre.backtrack_limit', 1000000000);
        require_once $_SERVER['DOCUMENT_ROOT'] . '/common/vendor/autoload.php';

        $record = Common::view_ctcr_by_id($record_id);
        if (empty($record)) { echo "No data found."; exit; }

        $rows = $record['master_timer_rows'] ?? [];
        $http = $_SERVER['REQUEST_SCHEME'];
        $host = $_SERVER['SERVER_NAME'];
        $logo = $http . '://' . $host . '/cycle_timer_calibration/assets/images/grandten_logo.png';

        // ── Resolve verified by / approved by display values ─────────────────
        $verified_by_display = !empty($record['VERIFICATION_ACTION_BY_NAME'])
            ? htmlspecialchars($record['VERIFICATION_ACTION_BY_NAME'])
            : '<span style="color:#999;font-style:italic;">Not verified yet</span>';

        $verified_date_display = !empty($record['VERIFICATION_ACTION_AT'])
            ? htmlspecialchars($record['VERIFICATION_ACTION_AT'])
            : '-';

        $approved_by_display = !empty($record['APPROVAL_ACTION_BY_NAME'])
            ? htmlspecialchars($record['APPROVAL_ACTION_BY_NAME'])
            : '<span style="color:#999;font-style:italic;">Not approved yet</span>';

        $approved_date_display = !empty($record['APPROVAL_ACTION_AT'])
            ? htmlspecialchars($record['APPROVAL_ACTION_AT'])
            : '-';

        // ── Audit info ───────────────────────────────────────────────────────────
        $created_by_display = htmlspecialchars($record['CREATED_BY_NAME'] ?? $record['CREATED_BY'] ?? '-');
        $created_at_display = !empty($record['CREATED_AT']) ? htmlspecialchars($record['CREATED_AT']) : '-';

        $updated_by_display = !empty($record['UPDATED_BY_NAME'])
            ? htmlspecialchars($record['UPDATED_BY_NAME'])
            : (!empty($record['UPDATED_BY']) ? htmlspecialchars($record['UPDATED_BY']) : null);
        $updated_at_display = !empty($record['UPDATED_AT']) ? htmlspecialchars($record['UPDATED_AT']) : null;

        $audit_line = 'Created by: <b>' . $created_by_display . '</b> on <b>' . $created_at_display . '</b>';
        if ($updated_by_display && $updated_at_display) {
            $audit_line .= '&nbsp;&nbsp;|&nbsp;&nbsp;Last updated by: <b>' . $updated_by_display . '</b> on <b>' . $updated_at_display . '</b>';
        } else {
            $audit_line .= '&nbsp;&nbsp;|&nbsp;&nbsp;<span style="color:#999;font-style:italic;">No update</span>';
        }

        $html = '
        <style>
            body  { font-family: sans-serif; font-size: 11px; }
            table { border-collapse: collapse; width: 100%; }
            td, th { padding: 5px; }
            .bold { font-weight: bold; }
            .label-cell { background-color: #f2f2f2; font-weight: bold; width: 30%; }
            .not-yet { color: #999; font-style: italic; }
            .audit-bar {
                font-size: 9px;
                color: #555;
                background: #f8f8f8;
                border: 1px solid #e0e0e0;
                padding: 4px 8px;
                margin-bottom: 6px;
            }
        </style>

        <!-- Audit bar at very top -->
        <div class="audit-bar">' . $audit_line . '</div>

        <table>
            <tr>
                <td width="70%">
                    <div class="bold">CYCLE TIMER CALIBRATION RECORD</div>
                    <div>Document No: QP-08-CTC</div>
                    <div>Revision No.: 3</div>
                </td>
                <td width="30%" style="text-align:right;">
                    <img src="' . $logo . '" height="50">
                </td>
            </tr>
        </table>
        <br>
        <table>
            <tr>
                <td width="50%">CTCR REFERENCE NO : <b>' . htmlspecialchars($record['CTFR_REF'] ?? '') . '</b></td>
                <td width="50%">FREQUENCY : ' . htmlspecialchars($record['FREQUENCY'] ?? '') . '</td>
            </tr>
            <tr>
                <td>TIMER TYPE/MODEL : ' . htmlspecialchars($record['TIMER_TYPE'] ?? '') . '</td>
                <td>DATE : ' . htmlspecialchars($record['INSPECTION_DATE_RAW'] ?? '') . '</td>
            </tr>
            <tr>
                <td>PAGE : ' . htmlspecialchars($record['PAGE'] ?? '') . '</td>
                <td>NEXT CALIBRATION DUE DATE : ' . htmlspecialchars($record['NEXT_CALIBRATION_DATE'] ?? '') . '</td>
            </tr>
            <tr>
                <td>CALIBRATED BY : ' . htmlspecialchars($record['CALIBRATED_BY_NAME'] ?? '') . '</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2"><i>Note: The acceptable tolerance limit is ±1%</i></td>
            </tr>
        </table>
        <br>

        <div class="bold">STANDARD REFERENCE STOPWATCH</div>
        <table>
            <tr><td class="label-cell">1. Type/Model/Serial No.</td><td>: ' . htmlspecialchars($record['REF_TYPE_MODEL_SERIAL'] ?? '') . '</td></tr>
            <tr><td class="label-cell">2. Calibration Date</td>       <td>: ' . htmlspecialchars($record['REF_CALIBRATION_DATE'] ?? '') . '</td></tr>
            <tr><td class="label-cell">3. Calibration Certificate No.</td><td>: ' . htmlspecialchars($record['REF_CERT_NO'] ?? '') . '</td></tr>
            <tr><td class="label-cell">4. Expiry Date</td>            <td>: ' . htmlspecialchars($record['REF_EXPIRY_DATE'] ?? '') . '</td></tr>
        </table>
        <br>

        <div class="bold">1. MASTER TIMER</div>
        <table border="1">
            <tr>
                <th rowspan="3" style="vertical-align:middle;">TIMER SETTING</th>
                <th colspan="6" style="text-align:center;">TIMER READING</th>
                <th rowspan="3" style="vertical-align:middle; text-align:center;">REMARKS</th>
            </tr>
            <tr>
                <th colspan="2" style="text-align:center;">secs</th>
                <th colspan="2" style="text-align:center;">secs</th>
                <th colspan="2" style="text-align:center;">secs</th>
            </tr>
            <tr>
                <th>Reading</th><th>% Dev.</th>
                <th>Reading</th><th>% Dev.</th>
                <th>Reading</th><th>% Dev.</th>
            </tr>';

        foreach ($rows as $r) {
            $html .= '<tr>
                <td>' . htmlspecialchars($r['timer_setting'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['r1'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['d1'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['r2'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['d2'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['r3'] ?? '') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($r['d3'] ?? '') . '</td>
                <td>' . htmlspecialchars($r['remarks'] ?? '') . '</td>
            </tr>';
        }

        if (empty($rows)) {
            $html .= '<tr><td colspan="8" style="text-align:center;">No data available</td></tr>';
        }

        $html .= '</table><br>';

        // ── VERIFICATION section: Verified By | Approved By | Comments ────────
        $comments_html = !empty($record['VERIFICATION_COMMENTS'])
            ? htmlspecialchars($record['VERIFICATION_COMMENTS'])
            : '';

        $html .= '<div class="bold">VERIFICATION</div>';
        $html .= '
        <table border="1" style="width:100%;">
            <tr>
                <th style="width:33%; background:#f2f2f2; text-align:left; padding:5px;">Verified By</th>
                <th style="width:33%; background:#f2f2f2; text-align:left; padding:5px;">Approved By</th>
                <th style="width:34%; background:#f2f2f2; text-align:left; padding:5px;">Comments</th>
            </tr>
            <tr>
                <!-- Verified By cell -->
                <td style="vertical-align:top; padding:8px; height:190px;">';

        if (!empty($record['VERIFICATION_ACTION_BY_NAME'])) {
            $html .= '<b>' . htmlspecialchars($record['VERIFICATION_ACTION_BY_NAME']) . '</b><br>'
                   . '<span style="font-size:9px;color:#555;">Date: ' . $verified_date_display . '</span>';
            if (!empty($record['VERIFICATION_REMARKS'])) {
                $html .= '<br><span style="font-size:9px;color:#555;">Remarks: ' . htmlspecialchars($record['VERIFICATION_REMARKS']) . '</span>';
            }
        } else {
            $html .= '<span style="color:#999;font-style:italic;">Not verified yet</span>';
        }

        // Signing space + footer label
        $html .= '
                    <br><br><br>
                    <div style="font-size:9px;color:#777;font-style:italic;">Official Stamp, Sign and Date</div>
                </td>

                <!-- Approved By cell -->
                <td style="vertical-align:top; padding:8px; height:190px;">';

        if (!empty($record['APPROVAL_ACTION_BY_NAME'])) {
            $html .= '<b>' . htmlspecialchars($record['APPROVAL_ACTION_BY_NAME']) . '</b><br>'
                   . '<span style="font-size:9px;color:#555;">Date: ' . $approved_date_display . '</span>';
            if (!empty($record['APPROVAL_REMARKS'])) {
                $html .= '<br><span style="font-size:9px;color:#555;">Remarks: ' . htmlspecialchars($record['APPROVAL_REMARKS']) . '</span>';
            }
        } else {
            $html .= '<span style="color:#999;font-style:italic;">Not approved yet</span>';
        }

        $html .= '
                    <br><br><br>
                    <div style="font-size:9px;color:#777;font-style:italic;">Official Stamp, Sign and Date</div>
                </td>

                <!-- Comments cell -->
                <td style="vertical-align:top; padding:8px; height:190px;">' . $comments_html . '</td>
            </tr>
        </table>';

        $tmp_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/mpdf/';
        if (!file_exists($tmp_dir)) { mkdir($tmp_dir, 0777, true); }

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'UTF-8',
                'format'        => 'A4',
                'margin_left'   => 10,
                'margin_right'  => 10,
                'margin_top'    => 10,
                'margin_bottom' => 10,
                'tempDir'       => $tmp_dir
            ]);
            $mpdf->SetTitle('Cycle Timer Calibration Record');
            $mpdf->WriteHTML($html);
            $mpdf->Output('CTCR_' . $record_id . '.pdf', $type);
        } catch (\Mpdf\MpdfException $e) {
            echo "PDF Error: " . $e->getMessage();
        }
    }
}