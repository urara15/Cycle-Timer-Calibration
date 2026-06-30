<?php
ob_start();
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/constant.php';

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header("Location:" . BASE_URL . "/Dashboard/Authenticate/login.php?gotourl=" . base64_encode(BASE_URL . "/vendor_qualification_form/"));
    die;
}

require_once 'api/common.php';
$employee_list = Common::employee_list();

// Determine mode: add, edit, view
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$record = [];

if (($mode == 'edit' || $mode == 'view') && isset($_GET['id'])) {
    $id = intval(base64_decode($_GET['id']));
    $record = Common::view_vqf_by_id($id);
    $vqf_rows = $record['criteria_details'] ?? [];
} else {
    $vqf_rows = [];
}
// Default criteria rows
$vqf_rows = [
    ['criteria' => 'Suitability', 'rating' => 40, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
    ['criteria' => 'Reliability', 'rating' => 25, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
    ['criteria' => 'Service', 'rating' => 20, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
    ['criteria' => 'Cost', 'rating' => 10, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
    ['criteria' => 'Others', 'rating' => 5, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
    ['criteria' => 'Total', 'rating' => 100, 'supplier1' => '', 'supplier2' => '', 'supplier3' => ''],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>GRAND TEN - <?= strtoupper($mode); ?> VENDOR QUALIFICATION FORM</title>
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
        #vqfTable th, #vqfTable td {
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        #vqfTable input.form-control {
            width: 100%;
            min-width: 150px;
        }

        .form-control.error, .error-border { border-color: red !important; }

        label.error {
            color: red;
            font-size: 13px;
            margin-top: 5px;
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .loader {
            border: 16px solid #f3f3f3;
            border-top: 16px solid #3498db;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-border { border: 1px solid red !important; }
        .error { color: red; font-size: 0.9em; }

        /* Highlight Total row */
        .total-row {
            background-color: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>

<body>
<div class="wrapper">
    <header class="main-header-top hidden-print">
        <a href="<?= BASE_URL; ?>/vendor_qualification_form/" class="logo">
            <img class="img-fluid able-logo" src="assets/images/yty_banner.svg" alt="Theme-logo">
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

    <div id="content-container" class="container content-wrapper">
        <div class="card m-a-2">
            <div class="card-block">
                <div class="col-12">
                    <h5>VENDOR QUALIFICATION FORM - <?= strtoupper($mode); ?></h5>
                </div>
                <hr />

                <div class="row text-center">
                    <div class="col-md-8">
                        <h5><strong>VENDOR QUALIFICATION FORM</strong></h5><br>
                        <h7>Document No: WIPUR-02-VQF <br> Revision No.: 1</h7>
                    </div>
                    <div class="col-md-4 text-end">
                        <img alt="logo" src="assets/images/grandten_logo.png">
                    </div>
                </div>

                <hr />

                <form method="post" id="vqfForm">
                    <input type="hidden" name="mode" value="<?= $mode; ?>">
                    <input type="hidden" name="record_id" value="<?= $record['ID'] ?? ''; ?>">

                    <div class="mb-3">
                        <label>VQF Reference No.<span style="color:red;">*</span></label>
                        <input type="text" name="vqf_ref_no" class="form-control" value="<?= $record['VEQ_REF_NO'] ?? ''; ?>" required <?= ($mode == 'view') ? 'readonly' : ''; ?>>
                    </div>
                    <br>
                    <div class="mb-3">
                        <label>Material/Item<span style="color:red;">*</span></label>
                        <input type="text" name="material_item" class="form-control" value="<?= $record['MATERIAL_ITEM'] ?? ''; ?>" required <?= ($mode == 'view') ? 'readonly' : ''; ?>>
                    </div>
                    <br>
                    <div style="overflow-x:auto;">
                        <table class="table table-bordered" id="vqfTable">
                            <thead class="thead-dark">
                            <tr>
                                <th>Criteria</th>
                                <th>Rating (%)</th>
                                <th>Supplier 1</th>
                                <th>Supplier 2</th>
                                <th>Supplier 3</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vqf_rows as $row): ?>
                                <tr <?= ($row['criteria'] == 'Total') ? 'class="total-row"' : ''; ?>>
                                    <td><input type="text" name="criteria[]" class="form-control" value="<?= $row['criteria']; ?>" readonly></td>
                                    <td><input type="number" name="rating[]" class="form-control" value="<?= $row['rating']; ?>" readonly></td>
                                    <td><input type="number" name="supplier1[]" class="form-control supplier-field" value="<?= $row['supplier1']; ?>" <?= ($row['criteria'] == 'Total' || $mode == 'view') ? 'readonly' : ''; ?>></td>
                                    <td><input type="number" name="supplier2[]" class="form-control supplier-field" value="<?= $row['supplier2']; ?>" <?= ($row['criteria'] == 'Total' || $mode == 'view') ? 'readonly' : ''; ?>></td>
                                    <td><input type="number" name="supplier3[]" class="form-control supplier-field" value="<?= $row['supplier3']; ?>" <?= ($row['criteria'] == 'Total' || $mode == 'view') ? 'readonly' : ''; ?>></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label>Disposition<span style="color:red;">*</span></label>
                        <textarea name="disposition" class="form-control" rows="3" <?= ($mode == 'view') ? 'readonly' : ''; ?>><?= $record['DISPOSITION'] ?? ''; ?></textarea>
                    </div>

                    <table class="table table-bordered">
                        <thead class="thead-dark">
                        <tr>
                            <th colspan="3" style="text-align: center;">SUPPLIER'S NAME AND ADDRESS</th>
                        </tr>
                        <tr>
                            <th>Supplier 1</th>
                            <th>Supplier 2</th>
                            <th>Supplier 3</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td><textarea name="supplier1_info" class="form-control" rows="6" <?= ($mode == 'view') ? 'readonly' : ''; ?>><?= $record['SUPPLIER1_INFO'] ?? ''; ?></textarea></td>
                            <td><textarea name="supplier2_info" class="form-control" rows="6" <?= ($mode == 'view') ? 'readonly' : ''; ?>><?= $record['SUPPLIER2_INFO'] ?? ''; ?></textarea></td>
                            <td><textarea name="supplier3_info" class="form-control" rows="6" <?= ($mode == 'view') ? 'readonly' : ''; ?>><?= $record['SUPPLIER3_INFO'] ?? ''; ?></textarea></td>
                        </tr>
                        </tbody>
                    </table>

                    <div class="mb-3">
                        <label>Remark<span style="color:red;">*</span></label>
                        <textarea name="remark" class="form-control" rows="2" <?= ($mode == 'view') ? 'readonly' : ''; ?>><?= $record['REMARK'] ?? ''; ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Checked by<span style="color:red;">*</span></label>
                            <?php if ($mode == 'view'): ?>
                                <input type="text" class="form-control" readonly value="<?= $record['CHECKED_BY_NAME'] ?? ''; ?>">
                            <?php else: ?>
                                <select name="checked_by" class="form-control select2-employee" style="width:100%;">
                                    <option value="">-- Select Employee --</option>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label>Date<span style="color:red;">*</span></label>
                            <input type="text" name="checked_date" class="form-control datepicker" value="<?= $record['CHECKED_DATE'] ?? ''; ?>" <?= ($mode == 'view') ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <div class="mb-3"><strong>Percentage for Approved Supplier = no less than 80%</strong></div>
                    <hr/>

                    <?php if ($mode != 'view'): ?>
                        <input type="submit" id="submitBtn" class="btn btn-primary" value="Submit">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JS Scripts -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/jquery-ui.min.js"></script>
<script src="assets/js/jquery.validate.min.js"></script>
<script src="assets/js/toastr.min.js"></script>
<script src="assets/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $(".select2").select2({ width: "100%" });
    initEmployeeSelect2(".select2-employee");

    $(".datepicker").datepicker({ dateFormat: "dd/mm/yy", maxDate: 0 });

    <?php if ($mode != 'view'): ?>
    // Calculate totals for suppliers
    function calculateTotals() {
        let total1 = 0, total2 = 0, total3 = 0;
        $('#vqfTable tbody tr').not('.total-row').each(function() {
            total1 += parseFloat($(this).find('input[name="supplier1[]"]').val() || 0);
            total2 += parseFloat($(this).find('input[name="supplier2[]"]').val() || 0);
            total3 += parseFloat($(this).find('input[name="supplier3[]"]').val() || 0);
        });

        let totalRow = $('#vqfTable tbody tr.total-row');
        totalRow.find('input[name="supplier1[]"]').val(total1);
        totalRow.find('input[name="supplier2[]"]').val(total2);
        totalRow.find('input[name="supplier3[]"]').val(total3);

        if (total1 > 100 || total2 > 100 || total3 > 100) {
            toastr.error("Total rating for any supplier cannot exceed 100!");
            return false;
        }
        return true;
    }

    $('#vqfTable').on('input', 'input.supplier-field', function() {
        calculateTotals();
    });

    function validateVQFTableRows() {
        let valid = true;
        $('#vqfTable tbody tr').not('.total-row').each(function() {
            $(this).find('input.supplier-field').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass("error-border");
                    valid = false;
                } else {
                    $(this).removeClass("error-border");
                }
            });
        });

        if (!calculateTotals()) valid = false;
        if (!valid) toastr.error("Please fill all supplier fields and ensure totals do not exceed 100.");
        return valid;
    }

    $("#vqfForm").validate({
        ignore: [],
        rules: {
            disposition: { required: true, maxlength: 2000 },
            remark: { required: true, maxlength: 2000 },
            checked_by: { required: true },
            checked_date: { required: true },
            supplier1_info: { required: true },
            supplier2_info: { required: true },
            supplier3_info: { required: true }
        },
        messages: {
            disposition: { required: "Disposition is required", maxlength: "Max 2000 characters" },
            remark: { required: "Remark is required", maxlength: "Max 2000 characters" },
            checked_by: { required: "Checked By is required" },
            checked_date: { required: "Checked Date is required" },
            supplier1_info: { required: "Supplier 1 info is required" },
            supplier2_info: { required: "Supplier 2 info is required" },
            supplier3_info: { required: "Supplier 3 info is required" }
        },
        errorClass: "error",
        highlight: el => $(el).addClass("error-border"),
        unhighlight: el => $(el).removeClass("error-border"),
        errorPlacement: (error, element) => error.insertAfter(element),
        submitHandler: () => false
    });

    $("#submitBtn").click(function(e) {
        e.preventDefault();
        let formValid = $("#vqfForm").valid();
        let tableValid = validateVQFTableRows();
        if (formValid && tableValid) ajaxSubmit("submit");
        else $(".error-border").first().focus();
    });

    function ajaxSubmit(action) {
        var formData = new FormData($("#vqfForm")[0]);
        formData.append("action", action);

        $.ajax({
            url: "api/save.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || "Saved successfully");
                    setTimeout(() => window.location.reload(), 2000);
                } else toastr.error(res.message || "Error occurred");
            },
            error: () => toastr.error("Server error. Try again.")
        });
    }

    <?php else: ?>
    $('#vqfForm input, #vqfForm select, #vqfForm textarea, #vqfForm button').prop('disabled', true);
    <?php endif; ?>
});

// Employee Select2
function initEmployeeSelect2(selector) {
    $(selector).each(function() {
        const $this = $(this);
        $this.select2({
            width: "100%",
            placeholder: "-- Select Employee --",
            minimumInputLength: 5,
            ajax: {
                url: "api/employee_search.php",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.results }),
                cache: true
            }
        });
    });
}
</script>

</body>
</html>
