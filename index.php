<?php
ob_start();
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/constant.php';

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header("Location:" . BASE_URL . "/Dashboard/Authenticate/login.php?gotourl=" . base64_encode(BASE_URL . "/cycle_timer_calibration_form/"));
    die;
}

$alert_message = null;
if (isset($_GET['msg'])) {
    $alert_message = htmlspecialchars(urldecode($_GET['msg']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>GRAND TEN - CYCLE TIMER CALIBRATION RECORD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css?v=1">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="assets/css/toastr.min.css">

    <style>
        .dataTables_scrollHead thead th,
        .dataTables_scrollHead thead th.sorting,
        .dataTables_scrollHead thead th.sorting_asc,
        .dataTables_scrollHead thead th.sorting_desc {
            background-color: #1a56a0 !important;
            color: #fff !important;
            font-weight: 600;
            white-space: nowrap;
        }
        .dataTables_scrollHead thead th.sorting::before,
        .dataTables_scrollHead thead th.sorting::after,
        .dataTables_scrollHead thead th.sorting_asc::after,
        .dataTables_scrollHead thead th.sorting_desc::after {
            color: #fff !important;
            opacity: 0.7;
        }
        .filter-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .filter-row label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #495057;
            display: block;
        }
        .filter-row .form-control { font-size: 13px; }
    </style>
</head>

<body>
<div class="wrapper">
    <header class="main-header-top hidden-print">
        <a href="<?= BASE_URL; ?>/cycle_timer_calibration_form/" class="logo">
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
                                <a href="pages/logout.php">
                                    <img src="assets/images/menu_logout.svg" class="side-icon" /> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <div id="content-container" class="container-fluid mydesignform">
        <div class="card m-a-2">
            <div class="card-block">

                <!-- Title + Add Button -->
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-9 col-xs-12">
                        <h5>CYCLE TIMER CALIBRATION CHECKLIST</h5>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-9 col-xs-12" style="text-align:right;">
                        <a href="<?= BASE_URL; ?>/cycle_timer_calibration/add.php" class="btn btn-success">ADD</a>
                    </div>
                </div>

                <br>

                <!-- Filter Row -->
                <div class="filter-row">
                    <div class="row align-items-end">

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom:8px;">
                            <label>Status</label>
                            <select id="statusFilter" class="form-control">
                                <option value="">-- All Status --</option>
                                <option value="Pending Approval">Pending Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom:8px;">
                            <label>Date From</label>
                            <input type="date" id="dateFrom" class="form-control">
                        </div>

                        <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom:8px;">
                            <label>Date To</label>
                            <input type="date" id="dateTo" class="form-control">
                        </div>

                        <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom:8px;">
                            <label>&nbsp;</label>
                            <button id="resetBtn" class="btn btn-default btn-block">Reset</button>
                        </div>

                    </div>
                </div>

                <hr />

                <div class="table-responsive" id="content-table" style="overflow-x:auto; width:100%;">
                    <table id="cycle_timer_calibration_form" class="display nowrap" style="width:100%"></table>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="assets/js/tether.min.js"></script>
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/jquery.dataTables.min.js"></script>
<script src="assets/js/toastr.min.js"></script>

<script>
var table;

$(document).ready(function () {

    // ── Init DataTable ──────────────────────────────────────────
    table = $('#cycle_timer_calibration_form').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        lengthChange: true,
        paging: true,
        pageLength: 10,
        order: [[0, 'desc']],
        scrollX: true,
        
        ajax: {
            url: "api/getListData.php",
            type: "POST",
            dataType: "json",
            // Inject filter values into every ajax request
            data: function (d) {
                d.status_filter = $('#statusFilter').val();
                d.date_from     = $('#dateFrom').val();
                d.date_to       = $('#dateTo').val();
            }
        },
        columns: [
            { title: "ID",                   data: "ID" },
            { title: "CTCR REFERENCE NO",    data: "CTCR_REFERENCE_NO" },
            { title: "TIMER TYPE MODEL",     data: "TIMER_TYPE_MODEL" },
            { title: "SERIAL NO",            data: "STD_REF_TYPE_MODEL_SERIAL" },
            { title: "CALIBRATION CERT NO",  data: "STD_REF_CALIBRATION_CERT_NO" },
            { title: "CALIBRATION DATE",     data: "CALIBRATION_DATE" },
            { title: "CREATED BY",           data: "CREATED_BY" },
            { title: "CREATED DATE",         data: "CREATED_AT" },
            { title: "APPROVED BY",          data: "APPROVED_BY" },
            { title: "STATUS",               data: "EQUIPMENT_STATUS", orderable: false },
            { title: "ACTION",               data: "ACTION",           orderable: false }
        ],
        language: {
            lengthMenu: "Show _MENU_ entries",
            emptyTable: "No Records Found."
        }
    });

    // ── Auto-reload on any filter change ───────────────────────
    $('#statusFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#dateFrom, #dateTo').on('change', function () {
        table.ajax.reload();
    });

    // ── Reset filters ───────────────────────────────────────────
    $('#resetBtn').click(function () {
        $('#statusFilter').val('');
        $('#dateFrom').val('');
        $('#dateTo').val('');
        table.ajax.reload();
    });

    // ── Alert from redirect ─────────────────────────────────────
    var alertMessage = "<?= addslashes($alert_message ?? ''); ?>";
    if (alertMessage) {
        toastr.error(alertMessage);
        setTimeout(function () {
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 2000);
    }
});
</script>

</body>
</html>