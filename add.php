<?php
ob_start();
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/constant.php';

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header("Location:" . BASE_URL . "/Dashboard/Authenticate/login.php?gotourl=" . base64_encode(BASE_URL . "/cycle_timer_calibration/"));
    die;
}

require_once 'api/common.php';

$current_user_id = $_SESSION['EMP_ID'] ?? '';

$mode   = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$record = [];

$default_master_rows = [
    ['is_label_row' => 'Y', 'timer_setting' => 'STD REF', 'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
    ['is_label_row' => 'N', 'timer_setting' => '',         'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
    ['is_label_row' => 'N', 'timer_setting' => '',         'r1' => '', 'd1' => '', 'r2' => '', 'd2' => '', 'r3' => '', 'd3' => '', 'remarks' => ''],
];

if (in_array($mode, ['edit', 'view', 'verify', 'approve']) && isset($_GET['id'])) {
    $id          = intval(base64_decode($_GET['id']));
    $record      = Common::view_vqf_by_id($id);
    $master_rows = !empty($record['master_timer_rows']) ? $record['master_timer_rows'] : $default_master_rows;
} else {
    $master_rows = $default_master_rows;
}

$user_is_verifier = Common::is_verifier($current_user_id);
$user_is_approver = Common::is_approver($current_user_id);

if ($mode === 'verify') {
    if (!$user_is_verifier) {
        header("Location:" . BASE_URL . "/cycle_timer_calibration/?msg=" . urlencode("You are not authorised to verify records."));
        die;
    }
    $record_status = $record['EQUIPMENT_STATUS'] ?? '';
    if ($record_status !== 'Pending Verification') {
        header("Location:" . BASE_URL . "/cycle_timer_calibration/?msg=" . urlencode("This record is not pending verification. Status: {$record_status}"));
        die;
    }
}

if ($mode === 'approve') {
    if (!$user_is_approver) {
        header("Location:" . BASE_URL . "/cycle_timer_calibration/?msg=" . urlencode("You are not authorised to approve records."));
        die;
    }
    $record_status = $record['EQUIPMENT_STATUS'] ?? '';
    if ($record_status !== 'Pending Approval') {
        header("Location:" . BASE_URL . "/cycle_timer_calibration/?msg=" . urlencode("This record is not pending approval. Status: {$record_status}"));
        die;
    }
}

$is_view_only = in_array($mode, ['view', 'verify', 'approve']);

$frequency_options = ['Monthly', 'Quarterly', 'Semi', 'Annual'];

if ($mode === 'edit')         { $mode_label = 'EDIT'; }
elseif ($mode === 'view')     { $mode_label = 'VIEW'; }
elseif ($mode === 'verify')   { $mode_label = 'VERIFY'; }
elseif ($mode === 'approve')  { $mode_label = 'APPROVE'; }
else                          { $mode_label = 'ADD'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>GRAND TEN - <?= $mode_label; ?> CYCLE TIMER CALIBRATION RECORD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=1">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/jquery-ui.min.css">
    <link rel="stylesheet" href="assets/css/toastr.min.css">
    <link rel="stylesheet" href="assets/css/select2.min.css">

    <style>
        .master-timer-table th,
        .master-timer-table td {
            vertical-align: middle;
            text-align: center;
            white-space: nowrap;
            padding: 6px 8px;
        }
        .master-timer-table input.form-control-sm {
            min-width: 80px;
            text-align: center;
        }
        .inline-field {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .inline-field label { white-space: nowrap; margin-bottom: 0; flex-shrink: 0; }
        .inline-field input { flex: 0 0 200px; width: 200px; }
        .required-star { color: red; }
        .form-control.error, .error-border { border-color: red !important; }
        label.error { color: red; font-size: 13px; margin-top: 5px; }
        .error-border { border: 1px solid red !important; }
        .error { color: red; font-size: 0.9em; }
        .btn-add-row, .btn-remove-row { padding: 2px 8px; font-size: 12px; }

        /* Action panels */
        .action-panel { border-radius: 8px; padding: 20px 24px; margin-bottom: 20px; }
        .action-panel h6 { font-weight: 700; margin-bottom: 16px; }

        .action-panel.verify-panel  { background: #e8f4fd; border: 2px solid #17a2b8; }
        .action-panel.verify-panel h6 { color: #0c5460; }
        .action-panel.verify-panel .btn-action-confirm {
            background-color: #17a2b8; color: #fff; border: none;
            padding: 8px 28px; border-radius: 4px; font-weight: 600; margin-right: 10px;
        }
        .action-panel.verify-panel .btn-action-confirm:hover { background-color: #138496; }

        .action-panel.approve-panel { background: #fff8e1; border: 2px solid #ffc107; }
        .action-panel.approve-panel h6 { color: #856404; }
        .action-panel.approve-panel .btn-action-confirm {
            background-color: #28a745; color: #fff; border: none;
            padding: 8px 28px; border-radius: 4px; font-weight: 600; margin-right: 10px;
        }
        .action-panel.approve-panel .btn-action-confirm:hover { background-color: #218838; }

        .btn-action-reject {
            background-color: #dc3545; color: #fff; border: none;
            padding: 8px 28px; border-radius: 4px; font-weight: 600;
        }
        .btn-action-reject:hover { background-color: #c82333; }

        /* History panels */
        .history-panel {
            background: #f8f9fa; border: 1px solid #dee2e6;
            border-radius: 8px; padding: 16px 20px; margin-bottom: 16px;
        }
        .history-panel h6 { font-weight: 700; margin-bottom: 12px; color: #495057; }
        .status-badge {
            display: inline-block; padding: 4px 14px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .status-approved  { background: #28a745; color: #fff; }
        .status-rejected  { background: #dc3545; color: #fff; }
        .status-verified  { background: #17a2b8; color: #fff; }
    </style>
</head>
<body>
<div class="wrapper">
    <header class="main-header-top hidden-print">
        <a href="<?= BASE_URL; ?>/cycle_timer_calibration/" class="logo">
            <img class="img-fluid able-logo" src="assets/images/yty_banner2.svg" alt="Theme-logo">
        </a>
        <nav class="navbar navbar-static-top">
            <div class="navbar-custom-menu f-right">
                <ul class="top-nav">
                    <li class="dropdown">
                        <span id="time"></span>
                        <span><b><?= ucwords(strtolower($_SESSION['EMP_NAME'])); ?></b></span>
                        <a href="#!" data-toggle="dropdown" class="dropdown-toggle drop icon-circle drop-image">
                            <span><img id="main_profile" class="img-circle" src="assets/images/profile.svg" alt="User Image"></span>
                        </a>
                        <ul class="dropdown-menu settings-menu">
                            <li class="border-top-menu">
                                <a href="pages/logout.php"><img src="assets/images/menu_logout.svg" class="side-icon" /> Logout</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <br>
    <br>

    <div id="content-container" class="container content-wrapper">
        <div class="card m-a-2">
            <div class="card-block">
                <div class="col-12">
                    <h5>CYCLE TIMER CALIBRATION - <?= $mode_label; ?></h5>
                </div>
                <hr />

                <!-- ══ VERIFICATION HISTORY ══ -->
                <?php if (!empty($record['VERIFICATION_ACTION_BY']) && in_array($mode, ['view', 'approve', 'edit'])): ?>
                <div class="history-panel">
                    <h6>&#10003; Verification Record</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Outcome</small>
                            <span class="status-badge status-verified">Verified</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Verified By</small>
                            <strong><?= htmlspecialchars($record['VERIFICATION_ACTION_BY_NAME'] ?? $record['VERIFICATION_ACTION_BY'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Date</small>
                            <strong><?= htmlspecialchars($record['VERIFICATION_ACTION_AT'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Remarks</small>
                            <strong><?= htmlspecialchars($record['VERIFICATION_REMARKS'] ?? '-'); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ══ APPROVAL HISTORY ══ -->
                <?php if (!empty($record['APPROVAL_ACTION_BY']) && in_array($mode, ['view', 'edit'])): ?>
                <div class="history-panel">
                    <h6>&#10003; Approval Record</h6>
                    <?php
                    $hist_status = $record['EQUIPMENT_STATUS'] ?? '';
                    $badge_class = $hist_status === 'Approved' ? 'status-approved' : 'status-rejected';
                    ?>
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Status</small>
                            <span class="status-badge <?= $badge_class; ?>"><?= htmlspecialchars($hist_status); ?></span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Action By</small>
                            <strong><?= htmlspecialchars($record['APPROVAL_ACTION_BY_NAME'] ?? $record['APPROVAL_ACTION_BY'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Date</small>
                            <strong><?= htmlspecialchars($record['APPROVAL_ACTION_AT'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Remarks</small>
                            <strong><?= htmlspecialchars($record['APPROVAL_REMARKS'] ?? '-'); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ══ VERIFY ACTION PANEL ══ -->
                <?php if ($mode === 'verify'): ?>
                <div class="action-panel verify-panel">
                    <h6>&#128270; Verification Action Required</h6>
                    <div class="mb-3">
                        <label for="action_remarks"><strong>Remarks</strong> <small class="text-muted">(required when rejecting)</small></label>
                        <textarea id="action_remarks" class="form-control" rows="3"
                            placeholder="Enter any remarks before verifying or rejecting…"></textarea>
                    </div>
                    <button type="button" id="btnVerify" class="btn-action-confirm">&#10003; Mark as Verified</button>
                    <button type="button" id="btnVerifyReject" class="btn-action-reject">&#10007; Reject</button>
                </div>
                <hr />
                <?php endif; ?>

                <!-- ══ APPROVE ACTION PANEL ══ -->
                <?php if ($mode === 'approve'): ?>
                <div class="action-panel approve-panel">
                    <h6>&#9888; Approval Action Required</h6>
                    <div class="mb-3">
                        <label for="action_remarks"><strong>Remarks</strong> <small class="text-muted">(required when rejecting)</small></label>
                        <textarea id="action_remarks" class="form-control" rows="3"
                            placeholder="Enter any remarks before approving or rejecting…"></textarea>
                    </div>
                    <button type="button" id="btnApprove" class="btn-action-confirm">&#10003; Approve</button>
                    <button type="button" id="btnReject" class="btn-action-reject">&#10007; Reject</button>
                </div>
                <hr />
                <?php endif; ?>

                <!-- Document header -->
                <div class="row text-center">
                    <div class="col-md-8">
                        <h5><strong>CYCLE TIMER CALIBRATION</strong></h5><br>
                        <span>Document No: QP-08-CTC &nbsp;|&nbsp; Revision No.: 3</span>
                    </div>
                    <div class="col-md-4 text-end">
                        <img alt="logo" src="assets/images/grandten_logo.png">
                    </div>
                </div>
                <hr />

                <form method="post" id="ctcForm">
                    <input type="hidden" name="mode"      value="<?= $mode; ?>">
                    <input type="hidden" name="record_id" value="<?= $record['ID'] ?? ''; ?>">

                    <!-- Row 1 -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>CTFR REFERENCE NO <span class="required-star">*</span></label>
                            <input type="text" name="ctfr_ref" class="form-control"
                                value="<?= htmlspecialchars($record['CTFR_REF'] ?? ''); ?>"
                                <?= $is_view_only ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label>TIMER TYPE/MODEL <span class="required-star">*</span></label>
                            <input type="text" name="timer_type" class="form-control"
                                value="<?= htmlspecialchars($record['TIMER_TYPE'] ?? ''); ?>"
                                <?= $is_view_only ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label>PAGE <span class="required-star">*</span></label>
                            <input type="number" name="page" class="form-control"
                                value="<?= htmlspecialchars($record['PAGE'] ?? ''); ?>"
                                <?= $is_view_only ? 'readonly' : ''; ?> step="1">
                        </div>
                    </div>
                    <br>

                    <!-- Row 2 -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>CALIBRATED BY <span class="required-star">*</span></label>
                            <?php if ($is_view_only): ?>
                                <input type="text" class="form-control" readonly
                                    value="<?= htmlspecialchars($record['CALIBRATED_BY_NAME'] ?? ''); ?>">
                            <?php else: ?>
                                <select name="calibrated_by" id="calibrated_by"
                                    class="form-control select2-employee" style="width:100%;"
                                    data-value="<?= htmlspecialchars($record['CALIBRATED_BY'] ?? ''); ?>"
                                    data-text="<?= htmlspecialchars($record['CALIBRATED_BY_NAME'] ?? ''); ?>">
                                    <option value="">-- Select Employee --</option>
                                    <?php if (!empty($record['CALIBRATED_BY'])): ?>
                                        <option value="<?= htmlspecialchars($record['CALIBRATED_BY']); ?>" selected>
                                            <?= htmlspecialchars($record['CALIBRATED_BY_NAME'] ?? ''); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label>FREQUENCY <span class="required-star">*</span></label>
                            <?php if ($is_view_only): ?>
                                <input type="text" class="form-control" readonly
                                    value="<?= htmlspecialchars($record['FREQUENCY'] ?? ''); ?>">
                            <?php else: ?>
                                <select name="frequency" id="frequency" class="form-control">
                                    <option value="">-- Select Frequency --</option>
                                    <?php foreach ($frequency_options as $opt): ?>
                                        <option value="<?= $opt; ?>"
                                            <?= ($record['FREQUENCY'] ?? '') === $opt ? 'selected' : ''; ?>>
                                            <?= $opt; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <label class="me-2 mb-0">DATE <span class="required-star">*</span></label>
                            <input type="date" name="date" class="form-control"
                                value="<?= htmlspecialchars($record['INSPECTION_DATE_RAW'] ?? ''); ?>"
                                <?= $is_view_only ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <br>

                    <!-- Row 3 -->
                    <div class="mb-3">
                        <div class="inline-field">
                            <label>NEXT CALIBRATION DUE DATE <span class="required-star">*</span></label>
                            <input type="date" name="next_calibration_date" class="form-control"
                                value="<?= htmlspecialchars($record['NEXT_CALIBRATION_DATE'] ?? ''); ?>"
                                <?= $is_view_only ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <p class="mb-4"><small>Note: The acceptable tolerance limit is ± 1%</small></p>
                    <hr />

                    <!-- STANDARD REFERENCE STOPWATCH -->
                    <div class="mb-4">
                        <h6><strong>STANDARD REFERENCE STOPWATCH</strong></h6>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-1 text-end">1.</div>
                            <div class="col-md-3"><label class="mb-0">Type/Model/Serial No.</label></div>
                            <div class="col-md-1 text-center">:</div>
                            <div class="col-md-5">
                                <input type="text" name="ref_type_model_serial" class="form-control"
                                    value="<?= htmlspecialchars($record['REF_TYPE_MODEL_SERIAL'] ?? ''); ?>"
                                    <?= $is_view_only ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-1 text-end">2.</div>
                            <div class="col-md-3"><label class="mb-0">Calibration Date</label></div>
                            <div class="col-md-1 text-center">:</div>
                            <div class="col-md-5">
                                <input type="date" name="ref_calibration_date" class="form-control"
                                    value="<?= htmlspecialchars($record['REF_CALIBRATION_DATE'] ?? ''); ?>"
                                    <?= $is_view_only ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-1 text-end">3.</div>
                            <div class="col-md-3"><label class="mb-0">Calibration Certificate No.</label></div>
                            <div class="col-md-1 text-center">:</div>
                            <div class="col-md-5">
                                <input type="text" name="ref_cert_no" class="form-control"
                                    value="<?= htmlspecialchars($record['REF_CERT_NO'] ?? ''); ?>"
                                    <?= $is_view_only ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-1 text-end">4.</div>
                            <div class="col-md-3"><label class="mb-0">Expiry Date</label></div>
                            <div class="col-md-1 text-center">:</div>
                            <div class="col-md-5">
                                <input type="date" name="ref_expiry_date" class="form-control"
                                    value="<?= htmlspecialchars($record['REF_EXPIRY_DATE'] ?? ''); ?>"
                                    <?= $is_view_only ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <hr />

                    <!-- MASTER TIMER TABLE -->
                    <div class="mb-4">
                        <h6><strong>1. MASTER TIMER</strong></h6>
                        <div style="overflow-x:auto;">
                            <table class="table table-bordered master-timer-table" id="masterTimerTable">
                                <thead>
                                    <tr>
                                        <th rowspan="3" style="vertical-align:middle;">TIMER<br>SETTING</th>
                                        <th colspan="6">TIMER READING</th>
                                        <th rowspan="3" style="vertical-align:middle;">REMARKS</th>
                                        <?php if (!$is_view_only): ?>
                                        <th rowspan="3" style="vertical-align:middle;">ACTION</th>
                                        <?php endif; ?>
                                    </tr>
                                    <tr>
                                        <th colspan="2">secs</th>
                                        <th colspan="2">secs</th>
                                        <th colspan="2">secs</th>
                                    </tr>
                                    <tr>
                                        <th>Reading</th><th>% Dev.</th>
                                        <th>Reading</th><th>% Dev.</th>
                                        <th>Reading</th><th>% Dev.</th>
                                    </tr>
                                </thead>
                                <tbody id="masterTimerBody">
                                    <?php foreach ($master_rows as $mr): ?>
                                    <tr>
                                        <input type="hidden" name="master_is_label[]" value="<?= htmlspecialchars($mr['is_label_row'] ?? 'N'); ?>">
                                        <td>
                                            <?php if (($mr['is_label_row'] ?? 'N') === 'Y'): ?>
                                                <strong><?= htmlspecialchars($mr['timer_setting']); ?></strong>
                                                <input type="hidden" name="master_timer_setting[]" value="<?= htmlspecialchars($mr['timer_setting']); ?>">
                                            <?php else: ?>
                                                <input type="text" name="master_timer_setting[]" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($mr['timer_setting'] ?? ''); ?>"
                                                    <?= $is_view_only ? 'readonly' : ''; ?>>
                                            <?php endif; ?>
                                        </td>
                                        <td><input type="number" step="any" name="master_r1[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['r1'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" step="any" name="master_d1[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['d1'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" step="any" name="master_r2[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['r2'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" step="any" name="master_d2[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['d2'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" step="any" name="master_r3[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['r3'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" step="any" name="master_d3[]" class="form-control form-control-sm" value="<?= htmlspecialchars($mr['d3'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>></td>
                                        <td style="white-space:normal; min-width:180px; text-align:left;">
                                            <input type="text" name="master_remarks[]" class="form-control form-control-sm" placeholder="Remarks" value="<?= htmlspecialchars($mr['remarks'] ?? ''); ?>" <?= $is_view_only ? 'readonly' : ''; ?>>
                                        </td>
                                        <?php if (!$is_view_only): ?>
                                        <td>
                                            <?php if (($mr['is_label_row'] ?? 'N') !== 'Y'): ?>
                                                <button type="button" class="btn btn-danger btn-sm btn-remove-row">&#x2212;</button>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!$is_view_only): ?>
                        <button type="button" id="addRowBtn" class="btn btn-secondary btn-sm mt-2">+ Add Row</button>
                        <?php endif; ?>
                    </div>
                    <hr />

                    <!-- COMMENTS -->
                    <div class="mb-4">
                        <h6><strong>COMMENTS</strong></h6>
                        <textarea name="verification_comments" class="form-control" rows="3"
                            <?= $is_view_only ? 'readonly' : ''; ?>><?= htmlspecialchars($record['VERIFICATION_COMMENTS'] ?? ''); ?></textarea>
                    </div>
                    <hr/>

                    <?php if (!$is_view_only): ?>
                        <button type="button" id="submitBtn" class="btn btn-primary">Submit</button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL; ?>/cycle_timer_calibration/" class="btn btn-default">&#8592; Back to List</a>
                </form>

            </div>
        </div>
    </div>
</div>

<input type="hidden" id="js_mode"      value="<?= htmlspecialchars($mode); ?>">
<input type="hidden" id="js_record_id" value="<?= htmlspecialchars($record['ID'] ?? ''); ?>">

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/jquery-ui.min.js"></script>
<script src="assets/js/jquery.validate.min.js"></script>
<script src="assets/js/toastr.min.js"></script>
<script src="assets/js/select2.min.js"></script>

<script>
$(document).ready(function () {

    $(".select2").select2({ width: "100%" });
    initEmployeeSelect2(".select2-employee");

    // ===== ADD ROW =====
    $("#addRowBtn").click(function () {
        var newRow = `
        <tr>
            <input type="hidden" name="master_is_label[]" value="N">
            <td><input type="text"   name="master_timer_setting[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_r1[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_d1[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_r2[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_d2[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_r3[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="any" name="master_d3[]" class="form-control form-control-sm"></td>
            <td style="white-space:normal; min-width:180px; text-align:left;">
                <input type="text" name="master_remarks[]" class="form-control form-control-sm" placeholder="Remarks">
            </td>
            <td><button type="button" class="btn btn-danger btn-sm btn-remove-row">&#x2212;</button></td>
        </tr>`;
        $("#masterTimerBody").append(newRow);
    });

    // ===== REMOVE ROW =====
    $(document).on("click", ".btn-remove-row", function () {
        $(this).closest("tr").remove();
    });

    // ===== VERIFY / APPROVE ACTION HANDLER =====
    var pageMode = $("#js_mode").val();
    var recordId = $("#js_record_id").val();

    function doAction(action, confirmLabel) {
        var remarks = $("#action_remarks").val().trim();
        if ((action === 'reject' || action === 'verify_reject') && remarks === '') {
            toastr.warning("Please enter remarks before rejecting.");
            $("#action_remarks").focus();
            return;
        }
        if (!confirm("Are you sure you want to " + confirmLabel + " this record?")) return;

        $.ajax({
            url:  "api/approve.php",
            type: "POST",
            data: { record_id: recordId, action: action, remarks: remarks },
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || "Action completed.");
                    setTimeout(function () {
                        window.location.href = "<?= BASE_URL; ?>/cycle_timer_calibration/";
                    }, 1800);
                } else {
                    toastr.error(res.message || "An error occurred.");
                }
            },
            error: function () { toastr.error("Server error. Please try again."); }
        });
    }

    if (pageMode === 'verify') {
        $("#btnVerify").click(function ()       { doAction('verify',        'Verify'); });
        $("#btnVerifyReject").click(function () { doAction('verify_reject', 'Reject'); });
    }
    if (pageMode === 'approve') {
        $("#btnApprove").click(function () { doAction('approve', 'Approve'); });
        $("#btnReject").click(function ()  { doAction('reject',  'Reject');  });
    }

    <?php if (!$is_view_only): ?>
    // ===== FORM VALIDATION + SUBMIT =====
    $("#ctcForm").validate({
        ignore: [],
        rules: {
            ctfr_ref:              { required: true },
            timer_type:            { required: true },
            page:                  { required: true },
            calibrated_by:         { required: true },
            frequency:             { required: true },
            date:                  { required: true },
            next_calibration_date: { required: true }
        },
        messages: {
            ctfr_ref:              { required: "CTFR Reference No is required" },
            timer_type:            { required: "Timer Type/Model is required" },
            page:                  { required: "Page is required" },
            calibrated_by:         { required: "Calibrated By is required" },
            frequency:             { required: "Frequency is required" },
            date:                  { required: "Date is required" },
            next_calibration_date: { required: "Next Calibration Due Date is required" }
        },
        errorClass: "error",
        highlight:   function(el) { $(el).addClass("error-border"); },
        unhighlight: function(el) { $(el).removeClass("error-border"); },
        errorPlacement: function(error, element) {
            if (element.hasClass("select2-employee")) {
                error.insertAfter(element.next(".select2-container"));
            } else {
                error.insertAfter(element);
            }
        },
        submitHandler: function() { return false; }
    });

    $("#submitBtn").click(function (e) {
        e.preventDefault();
        if ($("#ctcForm").valid()) { ajaxSubmit(); }
        else { $(".error-border").first().focus(); }
    });

    function ajaxSubmit() {
        var formData = new FormData($("#ctcForm")[0]);
        formData.append("action", "submit");
        $.ajax({
            url: "api/save.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || "Saved successfully");
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    if (res.errors && res.errors.length) {
                        $.each(res.errors, function(i, msg) { toastr.error(msg); });
                    } else {
                        toastr.error(res.message || "Error occurred");
                    }
                }
            },
            error: function() { toastr.error("Server error. Try again."); }
        });
    }
    <?php else: ?>
    $('#ctcForm input, #ctcForm select, #ctcForm textarea').prop('disabled', true);
    <?php endif; ?>

    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg) toastr.info(decodeURIComponent(msg));
});

function initEmployeeSelect2(selector) {
    $(selector).each(function () {
        var $this = $(this);
        var existingValue = $this.data('value');
        var existingText  = $this.data('text');
        $this.select2({
            width: "100%",
            placeholder: "-- Select Employee --",
            minimumInputLength: 2,
            ajax: {
                url: "api/employee_search.php",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; },
                cache: true
            }
        });
        if (existingValue && existingText) {
            var option = new Option(existingText, existingValue, true, true);
            $this.append(option).trigger('change');
        }
    });
}
</script>
</body>
</html>