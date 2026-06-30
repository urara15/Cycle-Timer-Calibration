<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>GRAND TEN - CYCLE TIMER CALIBRATION</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <style>
        .header-blue { background: linear-gradient(135deg, #1a56a0 0%, #0d47a1 100%); color: white; }
        .filter-card { background: #f8f9fa; border-radius: 10px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn-sm { font-size: 12px; }
        .table th { background: #1a56a0 !important; color: white !important; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-blue text-white p-3 rounded mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3><i class="fas fa-stopwatch me-2"></i>CYCLE TIMER CALIBRATION SYSTEM</h3>
                    <small>GRAND TEN ENGINEERING</small>
                </div>
                <div class="col-md-6 text-end">
                    <span class="me-3"><i class="fas fa-user"></i> <?php echo $_SESSION['EMP_NAME'] ?? 'User'; ?></span>
                    <a href="logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-list me-2"></i>Calibration Records</h5>
                    <a href="add.php" class="btn btn-success btn-lg">
                        <i class="fas fa-plus"></i> ADD NEW
                    </a>
                </div>

                <!-- Filters -->
                <div class="filter-card p-3 mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">All Status</option>
                                <option value="Draft">Draft</option>
                                <option value="Pending Approval">Pending Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Date From</label>
                            <input type="date" id="dateFrom" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Date To</label>
                            <input type="date" id="dateTo" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <button id="resetFilters" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-sync-alt"></i> Reset
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button id="applyFilters" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- DataTable -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="calibrationTable" class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="70px">ID</th>
                                        <th>CTCR REF NO</th>
                                        <th>TIMER MODEL</th>
                                        <th>SERIAL NO</th>
                                        <th>CERT NO</th>
                                        <th width="120px">CALIB DATE</th>
                                        <th>CREATED BY</th>
                                        <th width="130px">CREATED</th>
                                        <th>VERIFIED BY</th>
                                        <th>APPROVED BY</th>
                                        <th width="220px">ACTIONS</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
    $(document).ready(function() {
        let table = $('#calibrationTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'desc']],
            ajax: {
                url: 'api/getListData.php',
                type: 'POST',
                data: function(d) {
                    d.status_filter = $('#statusFilter').val();
                    d.date_from = $('#dateFrom').val();
                    d.date_to = $('#dateTo').val();
                }
            },
            columnDefs: [{ 
                targets: -1, 
                orderable: false 
            }],
            language: {
                processing: '<i class="fas fa-spinner fa-spin"></i> Loading...',
                emptyTable: 'No calibration records found',
                zeroRecords: 'No matching records found'
            },
            drawCallback: function() {
                $('.dataTables_length select').addClass('form-select form-select-sm');
            }
        });

        // Filter events
        $('#statusFilter, #dateFrom, #dateTo').on('change', function() {
            table.ajax.reload();
        });

        $('#resetFilters').click(function() {
            $('#statusFilter, #dateFrom, #dateTo').val('');
            table.ajax.reload();
        });

        $('#applyFilters').click(function() {
            table.ajax.reload();
        });
    });
    </script>
</body>
</html>