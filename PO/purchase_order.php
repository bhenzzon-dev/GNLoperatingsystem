<?php
session_start();
require_once 'db_connect.php';

// Initialize the success flag variable
$showSuccess = false;

// Fetch purchase order entries
$poQuery = "SELECT * FROM temp_purchase_orders";
$poResult = $conn->query($poQuery);

// Initialize grand total
$grand_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_admin'])) {
    $project_name = $_POST['project_name'];
    $ship_address = $_POST['ship_address'];
    $ship_contact = $_POST['ship_contact'];
    $ship_person = $_POST['ship_person'];
    $particulars = $_POST['particulars'] ?? '';

    // Generate PO number
    $po_number = 'PO' . date('Ymd') . '-' . rand(100, 999);

    $tempPOs = $conn->query("SELECT * FROM temp_purchase_orders");

    // Array to store unique mrf_ids
    $mrf_ids = [];
    $all_mrf_ids = [];

    while ($row = $tempPOs->fetch_assoc()) {
        $total_price = $row['qty'] * $row['unit_price'];
        $po_num = $_POST['po_number'] ?? $po_number;
        $po_date = $_POST['date'] ?? date('Y-m-d');

        $stmt = $conn->prepare("INSERT INTO purchase_orders 
            (po_number, po_num, date, item_description, qty, unit, unit_price, total_price,
             supplier_name, address, contact_number, contact_person, 
             ship_project_name, ship_address, ship_contact_number, ship_contact_person, particulars, mrf_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssissdssssssssss",
            $po_number,
            $po_num,
            $po_date,
            $row['item_description'],
            $row['qty'],
            $row['unit'],
            $row['unit_price'],
            $total_price,
            $row['supplier_name'],
            $row['address'],
            $row['contact_number'],
            $row['contact_person'],
            $project_name,
            $ship_address,
            $ship_contact,
            $ship_person,
            $particulars,
            $row['mrf_id']
        );

        $stmt->execute();
        $stmt->close();

        // Track all mrf_ids for debugging
        $all_mrf_ids[] = $row['mrf_id'];

        // Collect unique mrf_ids
        $mrf_id = (int)$row['mrf_id'];
        if (!in_array($mrf_id, $mrf_ids) && $mrf_id > 0) {
            $mrf_ids[] = $mrf_id;
        }
    }

    error_log("All MRF IDs processed: " . json_encode($all_mrf_ids));
    error_log("Unique MRF IDs for update: " . json_encode($mrf_ids));

    // Update requested_mrf status to 'requested'
    foreach ($mrf_ids as $mrf_id) {
        error_log("Updating status for MRF ID: $mrf_id");
        $updateStmt = $conn->prepare("UPDATE requested_mrf SET status = 'requested' WHERE id = ?");
        $updateStmt->bind_param("i", $mrf_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Clear the temp table
    $conn->query("DELETE FROM temp_purchase_orders");
    $showSuccess = true;
}


// Fetch project list
$projects = [];
$result = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
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
    <title>Purchase Order | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap & Fonts -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
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
            position: fixed; top: 6%; left: 0;
            width: 220px; height: 100%;
            background-color: #333; color: #fff;
            padding: 20px; display: flex;
            flex-direction: column; justify-content: flex-start;
            border-right: 3px solid #444;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
        }
        .sidebar a {
            color: #fff; text-decoration: none;
            padding: 8px 20px; margin: 10px 0;
            border-radius: 6px; transition: background-color 0.3s ease;
            font-weight: 500; text-transform: uppercase;
        }
        .sidebar a:hover { background-color: #555; }
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
        .form-section {
            display: flex; justify-content: space-between; gap: 30px;
            margin-bottom: 30px;
        }
        .form-section .card {
            flex: 1; padding: 20px;
            border-radius: 10px; border: 1px solid #ddd;
            background: #fdfdfd;
        }
        table {
            width: 100%; border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ccc; padding: 10px; text-align: left;
        }
        table th { background-color: #1167b1; color: white; }
/* html2canvas uses screen media, so override with a class instead */
body.generate-pdf .hide-in-pdf {
  display: none !important;
}
/* Container for Date and PO Number */
.po-info-container {
    position: absolute;
    top: -160px; /* Adjust the vertical distance from the top */
    right: 0px; /* Positioning on the right side */
    font-size: 14px;
    font-family: Arial, sans-serif;
    text-align: left;
}

/* Style for individual Date and PO Number fields */
.po-info-container div {
    margin-bottom: 10px; /* Space between the fields */
}

/* Styling for the input fields in Date and PO Number */
.po-info-container input {
    width: 180px; /* Fixed width for consistency */
    padding: 5px;
    margin-top: 5px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
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
    <a href="office_expenses_form.php">Office Expenses</a>
    <a href="misc_expenses_form.php">Miscellaneous</a>  
    <a href="reimbursement_form.php">Reimbursement</a>
    <a href="utilities_form.php">Utilities</a>
    <a href="payroll_form.php">Payroll</a>
    <a href="immediate_material.php">Materials</a>
    <a href="sub_contract_form.php">Sub Contract</a>
    <button onclick="window.location.href='finance_logout.php'" id="logoutBtn" class="btn-logout">Logout</button>

</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a class="nav-link" href="released_pdf.php">Released Summary</a></li>
        <li class="nav-item"><a class="nav-link" href="summary_approved.php">Approved Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_declined.php">Declined Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_request.php">Requested PO</a></li>
        </ul>
    </div>
</nav>


<?php
// Default values
$default_project_name = '';
$default_project_address = '';

// Step 1: Get the first available mrf_id from temp_purchase_orders
$mrfIdResult = $conn->query("SELECT mrf_id FROM temp_purchase_orders WHERE mrf_id IS NOT NULL LIMIT 1");
if ($mrfIdResult && $mrfRow = $mrfIdResult->fetch_assoc()) {
    $mrf_id = (int)$mrfRow['mrf_id'];

    // Step 2: Get project_id from requested_mrf
    $projectIdResult = $conn->query("SELECT project_id FROM requested_mrf WHERE id = $mrf_id LIMIT 1");
    if ($projectIdResult && $projRow = $projectIdResult->fetch_assoc()) {
        $project_id = (int)$projRow['project_id'];

        // Step 3: Get project_name and location from projects
        $projectInfoResult = $conn->query("SELECT project_name, address FROM projects WHERE id = $project_id LIMIT 1");
        if ($projectInfoResult && $projectInfo = $projectInfoResult->fetch_assoc()) {
            $default_project_name = $projectInfo['project_name'];
            $default_project_address = $projectInfo['address'];
        }
    }
}
?>
<!-- Main Content -->
<div id="poContent" style="width: 100%; max-width: 1200px; margin: 0 auto; padding: 20px; margin-top: 170px;">
    <h3>Purchase Order</h3>
    <div class="form-section">
        <!-- Vendor Info -->
        <div class="card">
            <div class="section-header">Vendor</div>
            <?php
            $vendorData = $poResult->fetch_assoc(); // Just fetch the first one as an example
            ?>
            <p><strong>Supplier Name:</strong> <?= htmlspecialchars($vendorData['supplier_name'] ?? 'N/A') ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($vendorData['address'] ?? '-') ?></p>
            <p><strong>Contact Number:</strong> <?= htmlspecialchars($vendorData['contact_number'] ?? '-') ?></p>
            <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendorData['contact_person'] ?? '-') ?></p>
        </div>

        <!-- Ship To Info -->
        <div class="card" id="shipForm">
    <div class="section-header">Ship To</div>
    <form method="post" action="">
    <input type="hidden" name="mrf_id" value="<?php echo $some_mrf_id; ?>">
    <div class="po-info-container">
    <div>
        <label><strong>Date:</strong></label>
        <input type="date" id="date" name="date" class="form-control" required>
    </div>
    <div>
        <label><strong>PO Number:</strong></label>
        <input type="text" id="po_number" name="po_number" class="form-control" required>
    </div>
</div>
<div class="form-group">
  <label for="project_name"><strong>Project Name</strong></label>
  <input class="form-control" list="project_names" name="project_name" id="project_name" required
         value="<?= htmlspecialchars($default_project_name) ?>">
  <datalist id="project_names">
    <?php foreach ($projects as $proj): ?>
      <option value="<?= htmlspecialchars($proj['project_name']) ?>"></option>
    <?php endforeach; ?>
  </datalist>
</div>

<div class="form-group">
  <label><strong>Address</strong></label>
  <input type="text" class="form-control" name="ship_address" id="ship_address" required
         value="<?= htmlspecialchars($default_project_address) ?>">
</div>

        <div class="form-group">
            <label><strong>Contact Number</strong></label>
            <input type="text" class="form-control" name="ship_contact" id="ship_contact" required>
        </div>
        <div class="form-group">
            <label><strong>Contact Person</strong></label>
            <input type="text" class="form-control" name="ship_person" id="ship_person" required>
        </div>

        <div class="form-group" style="margin-top: 20px;" data-html2canvas-ignore="true">
    <label for="particulars"><strong>Particulars</strong></label>
    <textarea name="particulars" id="particulars" class="form-control" rows="4" placeholder="Enter additional particulars or notes here..." required></textarea>
</div>

        <!-- Submit to Admin button -->
        <button type="submit" name="submit_to_admin" class="btn btn-primary hide-in-pdf">Submit to Admin</button>
    </form>
</div>


    </div>

    <!-- PO Item Table -->
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Item ID</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit of Measure</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $poResult->data_seek(0); // Reset pointer
        $counter = 1; // Initialize counter
        $grand_total = 0; // Make sure grand_total is initialized

        while ($row = $poResult->fetch_assoc()):
            $total = $row['qty'] * $row['unit_price'];
            $grand_total += $total; // Add the total to the grand total
        ?>
            <tr>
                <td><?= $counter ?></td>  <!-- Use counter here -->
                <td><?= htmlspecialchars($row['item_description']) ?></td>
                <td><?= $row['qty'] ?></td>
                <td><?= $row['unit'] ?></td>
                <td>₱<?= number_format($row['unit_price'], 2) ?></td>
                <td>₱<?= number_format($total, 2) ?></td>
            </tr>
        <?php 
            $counter++; // Increment counter
        endwhile; 
        ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right; font-weight:bold;">Grand Total</td>
                <td>₱<?= number_format($grand_total, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>


<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.querySelector('.sidebar').classList.toggle('collapsed');
    });
</script>

<?php if ($showSuccess): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1️⃣ Create loading overlay
    const loader = document.createElement('div');
    loader.id = 'loadingOverlay';
    loader.style.position = 'fixed';
    loader.style.top = 0;
    loader.style.left = 0;
    loader.style.width = '100%';
    loader.style.height = '100%';
    loader.style.background = 'rgba(0,0,0,0.5)';
    loader.style.display = 'flex';
    loader.style.alignItems = 'center';
    loader.style.justifyContent = 'center';
    loader.style.zIndex = 9999;

    // 2️⃣ Add spinner
    loader.innerHTML = `<div class="spinner"></div>`;
    document.body.appendChild(loader);

    // 3️⃣ Spinner CSS
    const style = document.createElement('style');
    style.innerHTML = `
        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    // 4️⃣ Show iziToast success
    iziToast.success({
        title: 'Success',
        message: 'Data successfully sent to admin.',
        position: 'topRight',
        timeout: 1500,
        onClosed: function() {
            // Remove loader
            loader.remove();
            // Redirect
            window.location.href = 'finance_index.php';
        }
    });
});
</script>
<?php endif; ?>


<script>
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


document.addEventListener("DOMContentLoaded", function () {
    // Set initial flag
    window.shouldWarnBeforeUnload = false;

    // Check if there's data
    const poTableHasData = document.querySelectorAll("table tbody tr").length > 0;
    if (poTableHasData) {
        window.shouldWarnBeforeUnload = true;
    }

    // ✅ Define the handler globally so we can remove it later
    window.beforeUnloadHandler = function (e) {
        if (window.shouldWarnBeforeUnload) {
            e.preventDefault();
            e.returnValue = ''; // This triggers Chrome's prompt
        }
    };

    // Attach handler
    window.addEventListener("beforeunload", window.beforeUnloadHandler);

    // Prevent navigation on click (except forms/buttons you allow)
    document.querySelectorAll('a[href], button').forEach(el => {
    el.addEventListener('click', function (e) {
        if (e.target.closest('form') || e.target.closest('.hide-in-pdf')) return;

        if (window.shouldWarnBeforeUnload) {
            e.preventDefault();
            iziToast.warning({
                title: 'Warning',
                message: 'You need to finish what you started!',
                position: 'topRight',
                timeout: 2000
            });
        }
    });
})

    // ✅ On form submit, clear the flag and remove the event
    const form = document.querySelector("#shipForm form");
    if (form) {
        form.addEventListener("submit", function () {
            console.log("✅ Form submitted. Removing beforeunload handler.");
            window.shouldWarnBeforeUnload = false;
            window.removeEventListener("beforeunload", window.beforeUnloadHandler);
        });
    }
});

// Get the button element
const logoutBtn = document.getElementById("logoutBtn");

// Disable the button
logoutBtn.disabled = true;

// Optional: change its style to show it’s disabled
logoutBtn.style.opacity = "0.5";
logoutBtn.style.cursor = "not-allowed";

</script>



</body>
</html>

<?php $conn->close(); ?>
