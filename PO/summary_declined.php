<?php
session_start();
require_once 'db_connect.php';

// Fetch purchase order entries, ordered by po_number to group easily
$poQuery = "SELECT * FROM summary_declined ORDER BY po_number, id";
$poResult = $conn->query($poQuery);

$poGroups = [];  // Array to hold grouped data

if ($poResult->num_rows > 0) {
    while ($row = $poResult->fetch_assoc()) {
        $po_number = $row['po_number'];
        // Group items under their PO number
        if (!isset($poGroups[$po_number])) {
            $poGroups[$po_number] = [
                'items' => [],
                'total' => 0,
                'particulars' => $row['particulars'],  // Add this line
            ];
        }
        
        $poGroups[$po_number]['items'][] = $row;
        $poGroups[$po_number]['total'] += $row['total_price'];  // assuming you have total_price column per item
    }
}
$projects = [];

foreach ($poGroups as $po_number => $poData) {
    $projectName = $poData['items'][0]['ship_project_name'];
    if (!isset($projects[$projectName])) {
        $projects[$projectName] = [];
    }
    $projects[$projectName][$po_number] = $poData;
}

$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'"; // Change table/column if needed
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

$sql = "SELECT COUNT(*) AS pending_count FROM mrf WHERE status = 'Pending'";
$result = $conn->query($sql);
$pending_count = 0;

if ($result && $row = $result->fetch_assoc()) {
    $pending_count = $row['pending_count'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Approved | Office Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap & Fonts -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Copy your CSS from original */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        .navbar {
    background: #1167b1;
    backdrop-filter: blur(8px);
}

.navbar-brand img {
    height: 30px;
}

.nav-link {
    font-weight: 500;
    color: #fff !important;
    position: relative;
    margin-left: 15px;
    margin-right: 15px;
    transition: color 0.3s ease;
}

.nav-link::after {
    content: "";
    display: block;
    height: 2px;
    background-color: #fff;
    width: 0;
    transition: width 0.3s ease;
    position: absolute;
    bottom: -1px;
    left: 0;
}

.nav-link:hover::after {
    width: 100%;
}

        .sidebar {
            position: fixed;
            top: 6%;
            left: 0;
            width: 220px;
            height: 100%;
            background-color: #333;
            color: #fff;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            border-right: 3px solid #444;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 8px 20px;
            margin: 10px 0;
            border-radius: 6px;
            transition: background-color 0.3s ease;
            font-weight: 500;
            text-transform: uppercase;
        }

        .sidebar a:hover {
            background-color: #555;
        }
        .btn-logout {
            background-color: #dc3545; color: #fff;
            border: none; padding: 5px 20px;
            border-radius: 6px; cursor: pointer;
            font-size: 16px; margin-top: 20px;
            width: 100%; font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .container.content {
            margin-left: 240px; padding: 20px;
        }
        .form-container {
            background-color: #fff; border-radius: 10px;
            padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 80px;
        }
        .section-header {
            font-weight: 600; font-size: 18px;
            margin-bottom: 10px; color: #333;
            border-bottom: 1px solid #ccc; padding-bottom: 5px;
        }
        table {
            width: 100%; border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ccc; padding: 10px; text-align: left;
        }
        table th { background-color: #1167b1; color: white; }
.container.content {
    margin-left: 240px; padding: 20px;
}
.form-container {
    background-color: #fff; border-radius: 10px;
    padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    margin-top: 80px;
}
.section-header {
    font-weight: 600; font-size: 18px;
    margin-bottom: 10px; color: #333;
    border-bottom: 1px solid #ccc; padding-bottom: 5px;
}
.form-section {
    display: flex; justify-content: space-between; gap: 30px;
    margin-bottom: 30px;
    width: 150%;
}
.form-section .card {
    flex: 1; padding: 20px;
    border-radius: 10px; border: 1px solid #ddd;
    background: #fdfdfd;
}
.po-summary-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.po-summary-cards > .card {
    flex: 1 1 300px; /* Cards will grow/shrink but try to stay at 300px width */
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
}

.po-summary-cards .section-header {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 10px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
    color: #007bff;
}

.table-responsive {
    flex: 1 1 100%;
    margin-top: 20px;
    width: 150%;
}

.table-responsive table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.table thead.thead-light th {
    background-color: #e9ecef;
}

.table thead.thead-dark th {
    background-color: #840000;
    color: white;
}

@media print {
    #poContent {
        position: static !important;
        margin: 0 auto !important;
        transform: none !important;
        scale: 1 !important;
        box-shadow: none !important;
    }
}
.pdf-mode {
    position: static !important;
    transform: none !important;
    margin: 0 auto !important;
    scale: 1 !important;
    box-shadow: none !important;
}
.pdf-scale-fit {
    transform: scale(0.8);
    transform-origin: top left;
    width: 100%;
    overflow: hidden;
}
@media print {
    .hide-in-pdf {
        display: none !important;
    }
}
body.generate-pdf .hide-in-pdf {
    display: none !important;
}
.po-info-container {
    position: absolute;
    top: -160px;
    right: 0px;
    font-size: 14px;
    font-family: Arial, sans-serif;
    text-align: left;
}
.po-info-container div {
    margin-bottom: 10px;
}
.po-info-container input {
    width: 180px;
    padding: 5px;
    margin-top: 5px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
}
@media print {
    .po-info-container {
        display: none !important;
    }
}
.toggle-po-btn {
  margin-bottom: 15px; /* Adds space below each button */
  display: inline-block; /* ensures margin works properly */
}
.container.content > h3 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 24px;
    font-weight: 600;
    color: #840000;
    margin-bottom: 0;
    border-bottom: 2px solid #840000;
    padding-bottom: 6px;
    max-width: fit-content;
    margin-top: 100px;
  }
  .project-group > h2 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 26px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 6px;
    border-bottom: 2px solid #2980b9;
    max-width: fit-content;
}
.nav-tabs .nav-link {
    color: black !important;
    background-color: transparent;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.nav-tabs .nav-link.active {
    background-color: #840000 !important;
    color: white !important;
    font-weight: 600;
}

.nav-tabs .nav-link:hover {
    background-color: #008000 !important;
    color: white !important;
    cursor: pointer;
}
.po-number{
    margin-bottom: 20px;
    font-size: 30px;
}
.minimal-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
    overflow: hidden;
}

.minimal-table thead {
    background-color: #f4f4f4;
}

.minimal-table th, .minimal-table td {
    padding: 12px 16px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #eee;
    color: #333;
}

.minimal-table tbody tr:hover {
    background-color: #840000;
    transition: background-color 0.2s ease;
}

.minimal-table th {
    font-weight: 600;
    color: #444;
}

.minimal-table td button {
    font-size: 13px;
    padding: 6px 12px;
    border-radius: 6px;
}
.selected-row {
  background-color: #ADD8E6!important; /* Light yellow highlight */
}

.status-pending {
    background-color: #fff3cd; /* light yellow */
    color: #856404; /* dark yellow text */
    font-weight: bold;
}

.status-approved {
    background-color: #d4edda; /* light green */
    color: #155724; /* dark green text */
    font-weight: bold;
}

.status-declined {
    background-color: #f8d7da; /* light red */
    color: #721c24; /* dark red text */
    font-weight: bold;
}
.action-btn-form {
    display: inline-block;
    margin-right: 5px;
}
/* Smooth fade in/out for the modal backdrop */
.modal.fade .modal-dialog {
  transition: opacity 0.1s ease, transform 0.1s ease;
  opacity: 0;
  transform: translateY(-25px);
}

.modal.fade.show .modal-dialog {
  opacity: 1;
  transform: translateY(0);
}

/* Optional: also smooth fade for backdrop */
.modal-backdrop.fade {
  opacity: 0;
  transition: opacity 0.1s ease;
}

.modal-backdrop.fade.show {
  opacity: 0.5; /* default backdrop opacity */
}
.badge {
    background-color: red;
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 15px;
    margin-left: 3px;
    position: relative;
    top: -30px; /* Adjust this value as needed */
}
    </style>
</head>
<body>


<!-- Sidebar -->
<div class="sidebar">
    <button id="sidebarToggle" class="btn btn-outline-light btn-sm mb-4">
        <i class="fas fa-bars"></i>
    </button>  
    <a href="finance_index.php" style="position: relative; display: inline-block;">
  MRF's
  <span id="mrf-badge" style="
    position: absolute;
    top: -2px;
    right: 80px;
    background: red;
    color: white;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 12px;
    <?= $pending_count > 0 ? 'display:inline-block;' : 'display:none;' ?>
  ">
    <?= $pending_count ?>
  </span>
</a>

    <a href="office_expenses_form.php">Office expenses</a>
    <a href="misc_expenses_form.php">Miscellaneous</a>  
    <a href="reimbursement_form.php">Reimbursement</a>
    <a href="utilities_form.php">Utilities</a>
    <a href="payroll_form.php">Payroll</a>
    <a href="sub_contract_form.php">Sub Contract</a>
    <button onclick="window.location.href='finance_logout.php'" class="btn-logout">Logout</button>
</div>

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ml-auto">
             <li class="nav-item"><a class="nav-link" href="summary_approved.php">Approved Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_declined.php">Declined Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_request.php">Requested PO</a></li>
        </ul>
    </div>
</nav>

<div class="container content">
    <h3>Summary Declined</h3>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="projectTabs" role="tablist">
        <?php $i = 0; foreach ($projects as $projectName => $projectPOs): ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" 
               id="tab-<?= $i ?>" 
               data-toggle="tab" 
               href="#content-<?= $i ?>" 
               role="tab">
               <?= htmlspecialchars($projectName) ?>
            </a>
        </li>
        <?php $i++; endforeach; ?>
    </ul>

   <!-- Tabs Content -->
<div class="tab-content" id="projectTabsContent" style="padding: 20px; background: #f9f9f9; border-radius: 0 0 10px 10px;">
<?php 
$i = 0;
foreach ($projects as $projectName => $projectPOs): 
?>
    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="content-<?= $i ?>" role="tabpanel">
        <div class="summary-request mb-4">
            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>PO Number</th>
                        <th>Status</th>
                        <th>Purchase Order</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($projectPOs as $po_num => $poData): 
                    $date = date('F j, Y', strtotime($poData['items'][0]['created_at']));
                    $vendor = $poData['items'][0];
                    $grand_total = 0;
                    foreach ($poData['items'] as $item) {
                        $grand_total += $item['qty'] * $item['unit_price'];
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($poData['particulars'] ?? '-') ?></td>
                        <td>₱<?= number_format($grand_total, 2) ?></td>
                        <td><?= htmlspecialchars($po_num) ?></td>
                        <?php 
                            $status = strtolower($poData['items'][0]['status']); // Convert status to lowercase
                            $statusClass = "status-" . $status; // e.g., status-pending, status-approved
                        ?>
                        <td class="<?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($poData['items'][0]['status']) ?>
                        </td>
                        <td>
                        <button 
                        class="btn btn-primary toggle-po-btn" 
                        data-target="po-<?= htmlspecialchars($po_num); ?>" 
                        data-po-num="<?= htmlspecialchars($po_num); ?>"
                        >
                        Show PO for <?= htmlspecialchars($date); ?>
                        </button>
                </td>
                    </tr>

                    <!-- Hidden summary section -->
                    <tr id="po-<?= htmlspecialchars($po_num); ?>" class="po-container" style="display: none;">
                     <td colspan="4" style="padding: 0; border: none;">
                        <div style="padding: 15px; border-top: 0px solid #ccc; margin-right: 13px;">
                        <div class="form-section">
                                <!-- Vendor Info -->
                                <div class="card mb-3">
                                    <div class="section-header">Vendor</div>
                                    <?php $vendor = $poData['items'][0]; ?>
                                    <p><strong>Supplier Name:</strong> <?= htmlspecialchars($vendor['supplier_name'] ?? 'N/A') ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor['address'] ?? '-') ?></p>
                                    <p><strong>Contact Number:</strong> <?= htmlspecialchars($vendor['contact_number'] ?? '-') ?></p>
                                    <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendor['contact_person'] ?? '-') ?></p>
                                </div>

                                <!-- Ship To -->
                                <div class="card mb-3">
                                    <p class="section-header">Ship To</p>
                                    <p><strong>Project Name:</strong> <?= htmlspecialchars($vendor['ship_project_name'] ?? ''); ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor['ship_address'] ?? ''); ?></p>
                                    <p><strong>Contact Number:</strong> <?= htmlspecialchars($vendor['ship_contact_number'] ?? ''); ?></p>
                                    <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendor['ship_contact_person'] ?? ''); ?></p>
                                </div>
                            </div>


                                <!-- Items Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Item ID</th>
                                                <th>Description</th>
                                                <th>Quantity</th>
                                                <th>Unit</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($poData['items'] as $item): 
                                            $total = $item['qty'] * $item['unit_price'];
                                        ?>
                                            <tr data-item-id="<?= $item['id'] ?>"> <!-- Optional: useful for JS -->
                                                <td><?= $counter++ ?></td>
                                                <td><?= htmlspecialchars($item['item_description']) ?></td>
                                                <td><?= $item['qty'] ?></td>
                                                <td><?= htmlspecialchars($item['unit']) ?></td>
                                                <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                                <td>₱<?= number_format($total, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-right font-weight-bold">Grand Total</td>
                                                <td>₱<?= number_format($grand_total, 2) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php $i++; endforeach; ?>
</div>
<!-- Bootstrap & jQuery scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script>
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');

    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });
// Toggle button show/hide and update text
document.querySelectorAll('.toggle-po-btn').forEach(button => {
  button.addEventListener('click', () => {
    const targetId = button.getAttribute('data-target');
    const targetDiv = document.getElementById(targetId);
    const showing = targetDiv.style.display === 'block';

    // Close all other open target divs and reset buttons/highlights
    document.querySelectorAll('.toggle-po-btn').forEach(btn => {
      const btnTargetId = btn.getAttribute('data-target');
      const btnTargetDiv = document.getElementById(btnTargetId);

      if (btn !== button) {
        if (btnTargetDiv) btnTargetDiv.style.display = 'none';

        let text = btn.textContent;
        if (text.includes('Hide')) {
          btn.textContent = text.replace('Hide', 'Show');
        }

        const row = btn.closest('tr');
        if (row) row.classList.remove('selected-row');
      }
    });

    // Toggle clicked button/row
    if (showing) {
      targetDiv.style.display = 'none';
      button.textContent = button.textContent.replace('Hide', 'Show');
      const row = button.closest('tr');
      if (row) row.classList.remove('selected-row');
    } else {
      targetDiv.style.display = 'block';
      button.textContent = button.textContent.replace('Show', 'Hide');
      const row = button.closest('tr');
      if (row) row.classList.add('selected-row');

      // ✅ Only fetch po_num here when showing
      const poNum = button.getAttribute('data-po-num');
      console.log("Fetched po_num:", poNum);

      const poInput = document.querySelector('#po_num_input');
      if (poInput) {
        poInput.value = poNum;
      }

      const summaryLink = document.querySelector('#summaryLink');
      if (summaryLink) {
        summaryLink.href = `summary_request.php?po_num=${encodeURIComponent(poNum)}`;
      }
    }
  });
});



$(document).ready(function() {
  // When switching tabs, close all open PO summaries and update buttons
  $('#projectTabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
    $('.toggle-po-btn').each(function() {
      const targetId = $(this).data('target');
      $('#' + targetId).hide();

      let text = $(this).text();
      if (text.includes('Hide')) {
        $(this).text(text.replace('Hide', 'Show'));
      }

      $(this).removeClass('active');

      // Remove highlight from rows when switching tabs
      const row = $(this).closest('tr');
      if (row.length) {
        row.removeClass('selected-row');
      }
    });
  });

  // Optional: On page unload, reset buttons and hide content
  $(window).on('beforeunload', function() {
    $('.toggle-po-btn').each(function() {
      const targetId = $(this).data('target');
      $('#' + targetId).hide();

      let text = $(this).text();
      if (text.includes('Hide')) {
        $(this).text(text.replace('Hide', 'Show'));
      }
      $(this).removeClass('active');

      const row = $(this).closest('tr');
      if (row.length) {
        row.removeClass('selected-row');
      }
    });
  });
});


document.querySelectorAll('.update-status-btn').forEach(button => {
    button.addEventListener('click', function () {
        const po_num = this.dataset.po;
        const status = this.dataset.status;

        // Confirmation popup before sending request
        Swal.fire({
            icon: 'warning',
            title: `Are you sure you want to ${status} this PO?`,
            text: "There's no going back after doing it.",
            showCancelButton: true,
            confirmButtonText: `Yes, ${status} it`,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // User confirmed, proceed with update
                fetch('update_summary_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `po_num=${encodeURIComponent(po_num)}&status=${encodeURIComponent(status)}`
                })
                .then(response => {
                    if (!response.ok) throw new Error("Network response was not ok");
                    return response.text(); // adjust if your PHP returns JSON
                })
                .then(data => {
                    // Show SweetAlert success popup
                    Swal.fire({
                        icon: 'success',
                        title: `PO ${status.charAt(0).toUpperCase() + status.slice(1)}`,
                        text: `The PO has been successfully ${status}.`,
                        confirmButtonText: 'OK'
                    });

                    // Find the <tr> (table row) of the clicked button
                    const row = button.closest('tr');

                    if (row) {
                        // Update status cell
                        const statusCell = row.querySelector('td:nth-child(4)');
                        statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                        statusCell.className = `status-${status}`; // Optional: change class for styling

                        // Replace action buttons with a note
                        const actionCell = row.querySelector('td:nth-child(6)');
                        actionCell.innerHTML = `<em>No actions available</em>`;
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong while updating the status.',
                    });
                    console.error('Error:', error);
                });
            }
            // else: user canceled, do nothing
        });
    });
});


document.addEventListener('DOMContentLoaded', function () {
    function updateGrandTotal(poNum) {
    // Find the PO container table footer for this PO number
    const poContainer = document.getElementById(`po-${poNum}`);
    if (!poContainer) return;

    // Sum all totals from the item rows inside tbody
    let sum = 0;
    poContainer.querySelectorAll('tbody tr').forEach(row => {
        const totalCell = row.querySelector('td:nth-child(6)');
        if (totalCell) {
            // Extract numeric value from currency formatted text e.g., ₱123.45
            const totalText = totalCell.textContent.trim().replace(/[₱,]/g, '');
            const totalValue = parseFloat(totalText);
            if (!isNaN(totalValue)) sum += totalValue;
        }
    });

    // Update the footer cell that contains the grand total
    const footerTotalCell = poContainer.querySelector('tfoot tr td:last-child');
    if (footerTotalCell) {
        footerTotalCell.textContent = `₱${sum.toFixed(2)}`;
    }
}

    // DELETE
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the item.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_po_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete&id=${encodeURIComponent(id)}`
                    })
                    .then(res => res.text())
                    .then(() => {
    Swal.fire('Deleted!', 'The item has been removed.', 'success').then(() => {
        // Remove the deleted row from the table immediately
        const row = button.closest('tr');
        if (row) {
            const poNum = row.closest('.po-container').id.replace('po-', '');
            row.remove();
            updateGrandTotal(poNum); // update grand total for this PO
        }
    });
});

                }
            });
        });
    });


    // EDIT
    document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', () => {
        const id = button.dataset.id;
        const description = button.dataset.description;
        const qty = button.dataset.qty;
        const unit = button.dataset.unit;
        const price = button.dataset.price;

        Swal.fire({
            title: 'Edit Item',
            html:
                `<input id="desc" class="swal2-input" placeholder="Description" value="${description}">` +
                `<input id="qty" type="number" class="swal2-input" placeholder="Qty" value="${qty}">` +
                `<input id="unit" class="swal2-input" placeholder="Unit" value="${unit}">` +
                `<input id="price" type="number" step="0.01" class="swal2-input" placeholder="Unit Price" value="${price}">`,
            focusConfirm: false,
            preConfirm: () => {
                return {
                    id,
                    description: document.getElementById('desc').value,
                    qty: document.getElementById('qty').value,
                    unit: document.getElementById('unit').value,
                    price: document.getElementById('price').value
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                const data = result.value;
                const body = `action=edit&id=${data.id}&item_description=${encodeURIComponent(data.description)}&qty=${data.qty}&unit=${encodeURIComponent(data.unit)}&unit_price=${data.price}`;

                fetch('update_po_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                })
                .then(res => res.text())
                .then(() => {
                    // Find the row by data-item-id
                    const row = document.querySelector(`tr[data-item-id="${data.id}"]`);
                    if (row) {
                        // Update the row cells
                        row.children[1].textContent = data.description;
                        row.children[2].textContent = data.qty;
                        row.children[3].textContent = data.unit;
                        row.children[4].textContent = `₱${parseFloat(data.price).toFixed(2)}`;
                        const total = parseFloat(data.qty) * parseFloat(data.price);
                        row.children[5].textContent = `₱${total.toFixed(2)}`;

                        // Update the button's data attributes inside the row
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) {
                            editBtn.setAttribute('data-description', data.description);
                            editBtn.setAttribute('data-qty', data.qty);
                            editBtn.setAttribute('data-unit', data.unit);
                            editBtn.setAttribute('data-price', data.price);
                        }

                        // Update the grand total for the PO container
                        const poNum = row.closest('.po-container').id.replace('po-', '');
                        updateGrandTotal(poNum);
                    }

                    Swal.fire('Updated!', 'The item has been updated.', 'success');
                });
            }
        });
    });
});

});

document.addEventListener('DOMContentLoaded', function () {
  const addItemBtns = document.querySelectorAll('.addItemBtn');

  addItemBtns.forEach(button => {
    button.addEventListener('click', function () {
      const poNum = this.getAttribute('data-po_num');
      console.log('Fetched PO Number:', poNum);
      document.getElementById('modalPoNum').value = poNum;

      // Find the hidden PO summary row by ID (#po-PO_NUM)
      const poRow = document.getElementById(`po-${poNum}`);

if (poRow) {
  // Vendor info
  let supplierName = '', address = '', contactNumber = '', contactPerson = '';
  const vendorCard = poRow.querySelector('.card:nth-of-type(1)');
  if (vendorCard) {
    vendorCard.querySelectorAll('p').forEach(p => {
      const text = p.textContent;
      if (text.includes('Supplier Name:')) supplierName = text.replace('Supplier Name:', '').trim();
      else if (text.includes('Address:')) address = text.replace('Address:', '').trim();
      else if (text.includes('Contact Number:')) contactNumber = text.replace('Contact Number:', '').trim();
      else if (text.includes('Contact Person:')) contactPerson = text.replace('Contact Person:', '').trim();
    });
  }

  // Ship To info
  let shipProjectName = '', shipAddress = '', shipContactNumber = '', shipContactPerson = '';
  const shipCard = poRow.querySelector('.card:nth-of-type(2)');
  if (shipCard) {
    shipCard.querySelectorAll('p').forEach(p => {
      const text = p.textContent;
      if (text.includes('Project Name:')) shipProjectName = text.replace('Project Name:', '').trim();
      else if (text.includes('Address:')) shipAddress = text.replace('Address:', '').trim();
      else if (text.includes('Contact Number:')) shipContactNumber = text.replace('Contact Number:', '').trim();
      else if (text.includes('Contact Person:')) shipContactPerson = text.replace('Contact Person:', '').trim();
    });
  }

  // Particulars and Date from main row (previous sibling)
  const mainRow = poRow.previousElementSibling;
  let particulars = '';
  let date = '';
  if (mainRow) {
    particulars = mainRow.querySelector('td:first-child')?.textContent.trim() || '';
    date = mainRow.querySelector('td:nth-child(3)')?.textContent.trim() || '';
  }

  // Set hidden inputs
  document.getElementById('supplierName').value = supplierName;
  document.getElementById('address').value = address;
  document.getElementById('contactNumber').value = contactNumber;
  document.getElementById('contactPerson').value = contactPerson;
  document.getElementById('shipProjectName').value = shipProjectName;
  document.getElementById('shipAddress').value = shipAddress;
  document.getElementById('shipContactNumber').value = shipContactNumber;
  document.getElementById('shipContactPerson').value = shipContactPerson;
  document.getElementById('particulars').value = particulars;
  document.getElementById('poDate').value = date;
}


      // Reset modal form fields except hidden inputs we just set
      const addItemForm = document.getElementById('addItemForm');
      // Save hidden fields
      const hiddenValues = {
        supplierName: document.getElementById('supplierName').value,
        address: document.getElementById('address').value,
        contactNumber: document.getElementById('contactNumber').value,
        contactPerson: document.getElementById('contactPerson').value,
        shipProjectName: document.getElementById('shipProjectName').value,
        shipAddress: document.getElementById('shipAddress').value,
        shipContactNumber: document.getElementById('shipContactNumber').value,
        shipContactPerson: document.getElementById('shipContactPerson').value,
        particulars: document.getElementById('particulars').value,
        poDate: document.getElementById('poDate').value,
        modalPoNum: document.getElementById('modalPoNum').value
      };

      addItemForm.reset();

      // Restore hidden inputs after reset
      for (const [key, val] of Object.entries(hiddenValues)) {
        document.getElementById(key).value = val;
      }

      document.getElementById('total_price').value = '';

      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('addItemModal'));
      modal.show();
    });
  });

  // Auto-calculate total price
  const qtyInput = document.getElementById('qty');
  const unitPriceInput = document.getElementById('unit_price');
  const totalPriceInput = document.getElementById('total_price');

  function calculateTotal() {
    const qty = parseFloat(qtyInput.value) || 0;
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    totalPriceInput.value = (qty * unitPrice).toFixed(2);
  }

  qtyInput.addEventListener('input', calculateTotal);
  unitPriceInput.addEventListener('input', calculateTotal);

  // Handle form submission
  // Handle form submission
  document.getElementById('addItemForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const form = this;  // store reference for later removal
  const formData = new FormData(form);

  fetch('insert_po_item.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    console.log('Response:', data);

    if (data === 'success') {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'PO Item added successfully!',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        // Hide the modal
        const modalEl = document.getElementById('addItemModal');
        if (bootstrap.Modal.getInstance) {
          bootstrap.Modal.getInstance(modalEl).hide();
        } else if (bootstrap.Modal) {
          const modal = new bootstrap.Modal(modalEl);
          modal.hide();
        } else if (window.jQuery) {
          $(modalEl).modal('hide');
        } else {
          console.warn('Cannot hide modal: Bootstrap modal instance or jQuery not found.');
        }

        // Remove the form from the DOM
        form.remove();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error: ' + data,
      });
    }
  })
  .catch(err => {
    console.error('AJAX Error:', err);
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: 'An error occurred. Check console for details.',
    });
  });
});
});

let originalTitle = "Material Requisition Forms | Admin Panel";
let altTitle = "🔔 New MRF Pending!";
let titleToggle = false;
let titleInterval = null;
let isAnimating = false;

function animateTitleStart() {
  if (titleInterval) return; // Already running
  isAnimating = true;

  titleInterval = setInterval(() => {
    document.title = titleToggle ? originalTitle : altTitle;
    titleToggle = !titleToggle;
  }, 1000);
}

function animateTitleStop() {
  clearInterval(titleInterval);
  titleInterval = null;
  isAnimating = false;
  document.title = originalTitle;
}

function updateMRFCount() {
  fetch('get_pending_count.php')
    .then(response => response.text())
    .then(count => {
      const badge = document.getElementById('mrf-badge');
      const num = parseInt(count);

      if (num > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
        if (document.hidden) {
          animateTitleStart(); // Start animation only if tab is hidden
        } else {
          animateTitleStop(); // Stop animation if tab is visible
        }
      } else {
        badge.style.display = 'none';
        animateTitleStop();
      }
    })
    .catch(err => console.error('Error fetching MRF count:', err));
}

// Listen for visibility change
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && isAnimating) {
    // User switched back to tab, stop animation
    animateTitleStop();
  }
});

setInterval(updateMRFCount, 5000);
updateMRFCount(); // Initial call to get started immediately

</script>


</body>
</html>
