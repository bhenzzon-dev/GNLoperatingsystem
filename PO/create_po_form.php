<?php
session_start();
require_once 'db_connect.php';

// Get the MRF id from the query string
$mrf_id = isset($_GET['mrf_id']) ? $_GET['mrf_id'] : null;

if ($mrf_id) {
    // Fetch the grouped MRF data based on mrf_id
    $mrfQuery = "SELECT id, category, item_description, qty, unit FROM mrf WHERE mrf_id = ? AND status IN ('pending', 'acknowledged')";
    $stmt = $conn->prepare($mrfQuery);
    $stmt->bind_param("s", $mrf_id);
    $stmt->execute();
    $mrfResult = $stmt->get_result();
    $mrfDataList = $mrfResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Insert MRFs with 'Requested' status into requested_mrf table
    $insertQuery = "
    INSERT INTO requested_mrf (
        id, project_id, item_description, qty, unit, comment, mrf_id, category, status
    )
    SELECT 
        id, project_id, item_description, qty, unit, comment, mrf_id, category, status
    FROM 
        mrf
    WHERE 
        mrf_id = ? AND status = 'Processing'
";

    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("s", $mrf_id);

    if ($insertStmt->execute()) {
        // If insert was successful, delete the same entries from mrf
        $deleteQuery = "DELETE FROM mrf WHERE mrf_id = ? AND status = 'Processing'";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("s", $mrf_id);
        $deleteStmt->execute();
        $deleteStmt->close();
    } else {
        echo "Error inserting into requested_mrf: " . $insertStmt->error;
    }

    $insertStmt->close();
}

// Fetch projects for the dropdown
$projectsQuery = "SELECT id, project_name FROM projects ORDER BY project_name ASC";
$projectsResult = $conn->query($projectsQuery);

// Check for success message from redirect
$successMessage = isset($_GET['success']) ? true : false;

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
    <title>Create PO</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            width: 100%;
            font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.2s ease-in-out;
        }

        .btn-logout:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .btn-logout:active {
            transform: translateY(0);
        }

        .main-content {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: -40px;
    }

    .table-section {
        flex: 1;
        min-width: 300px;
    }

    .form-section {
        flex: 0 0 450px;
        max-width: 100%;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    input[type="text"],
    input[type="number"] {
        width: 100%;
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    input[type="submit"] {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
    }

    @media (max-width: 992px) {
        .main-content {
            flex-direction: column;
        }

        .form-section,
        .table-section {
            flex: 100%;
        }
    }
    input[type="submit"] {
    cursor: pointer;
    transition: background-color 0.3s ease; /* Smooth transition for background color */
    background-color: #28a745; /* Default background color */
    color: white; /* Text color */
    border: none; /* Optional: remove border */
    padding: 10px 20px; /* Optional: add padding */
    font-size: 16px; /* Optional: font size */
    border-radius: 5px; /* Optional: rounded corners */
}

input[type="submit"]:hover {
    background-color: darkgreen; /* Change background color on hover */
    color: white; /* Optional: change text color */
}
.add-sign {
            cursor: pointer;
            font-size: 20px;
            color: green;
            margin-left: 20px;
        }
        .main-content {
    display: flex;
    gap: 5px;
    flex-wrap: nowrap; /* Keep side by side */
}

.table-section {
    flex: 2; /* Table takes double space */
    min-width: 300px;
}

.form-section {
    flex: 1; /* Form takes less space */
    min-width: 450px;
}

/* For smaller screens, stack vertically */
@media (max-width: 992px) {
    .main-content {
        flex-direction: column;
    }

    .table-section,
    .form-section {
        flex: unset;
        width: 100%;
    }
}
.search-container {
  display: flex;
  align-items: center;
  max-width: 500px;
  margin: 20px auto 10px auto;
  border: 1.5px solid #ccc;
  border-radius: 8px;
  background-color: #fff;
  padding: 5px 10px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  margin-bottom: -200px;
  margin-top: 80px;
}

.search-btn {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #1167b1; /* your blue color */
  margin-right: 8px;
  padding: 0;
}

.search-btn:hover {
  color: #0b4a82; /* darker blue on hover */
}

.search-input {
  flex-grow: 1;
  border: none;
  font-size: 16px;
  outline: none;
  padding: 0px 0;
  color: #333;
  font-family: 'Poppins', sans-serif;

}

.search-input::placeholder {
  color: #aaa;
  
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
    <a href="immediate_material.php">Materials</a>
    <a href="sub_contract_form.php">Sub Contract</a>
    <button onclick="window.location.href='finance_logout.php'" class="btn-logout">Logout</button>
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
<div class="search-container d-flex align-items-center mb-4">
  <button type="button" class="btn btn-outline-secondary me-2" id="searchBtn">
    <i class="fas fa-search"></i>
  </button>
  <input type="text" class="form-control" id="searchInput" placeholder="Search item description...">
</div>

<!-- Results will appear here -->
<div id="searchResults" class="table-responsive mx-auto" style="display: none; max-width: 550px;">
  <table class="table table-bordered">
    <thead class="thead-dark">
      <tr>
        <th>Item Description</th>
        <th>Unit of Measure</th>
        <th>Unit Price</th>
        <th>Supplier Name</th>
        <th>Date Added</th>
      </tr>
    </thead>
    <tbody id="searchResultsBody"></tbody>
  </table>
</div>

<div class="container mt-5 pt-5">
<div class="main-content">
    <div class="main-content d-flex flex-wrap">
        <!-- TABLE SECTION - NOW FIRST -->
        <div class="table-section p-4 bg-white rounded shadow mb-4">
            <?php if (!empty($mrfDataList)): ?>
                <h5>MRF Details</h5>
                <table class="table table-bordered mt-3">
                    <thead class="thead-dark">
                        <tr>
                            <th>Category</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mrfDataList as $mrfData): ?>
                            <tr data-id="<?= htmlspecialchars($mrfData['id']) ?>">
                                <td><?= htmlspecialchars($mrfData['category']) ?></td>
                                <td><?= htmlspecialchars($mrfData['item_description']) ?></td>
                                <td><?= htmlspecialchars($mrfData['qty']) ?></td>
                                <td><?= htmlspecialchars($mrfData['unit']) ?></td>
                                <td>
                                    <!-- The "+" sign outside the table but aligned with each row -->
                                    <span class="add-sign">+</span>
                        </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-danger">No MRF data found for the selected group.</p>
            <?php endif; ?>
        </div>

        <div class="form-section mb-4 p-4 bg-white rounded shadow">
  <h3>Purchase Order Form</h3>
  <form id="purchaseOrderForm" method="POST" action="submit_po.php">
    <input type="hidden" name="mrf_id" value="<?= $mrf_id ?>">
    <input type="hidden" id="total_price" name="price"> <!-- Total price field -->

    <div id="formRowsContainer">
      <!-- FIRST ROW (BASE TEMPLATE) -->
      <!-- FIRST ROW (BASE TEMPLATE, NOW BLANK) -->
<div class="form-entry border p-3 mb-3 rounded">
  <div class="form-number font-weight-bold mb-2" style="color: #1167b1;"></div>

  <div class="form-row">
    <div class="form-group col">
      <label>Category</label>
      <input type="text" name="category[]" class="form-control category" value="" required>
    </div>
    <div class="form-group col">
      <label>Item Description</label>
      <input type="text" name="item_description[]" class="form-control item_description" value="" required>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col">
      <label>Quantity</label>
      <input type="number" name="qty[]" class="form-control qty" step="any" value="" required>
    </div>
    <div class="form-group col">
      <label>Unit</label>
      <input type="text" name="unit[]" class="form-control unit" value="" required>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col">
        <label>Unit Price</label>
        <input type="number" name="unit_price[]" class="form-control unit_price" placeholder="Enter unit price" step="0.01" required>
    </div>
    <div class="form-group col">
        <label>Total Price</label>
        <input type="text" name="total_price[]" class="form-control total_price_display" readonly value="₱0.00">
    </div>
</div>


  <input type="hidden" name="id[]" class="id" value="">
</div>

    </div>

    <!-- Shared Supplier Fields -->
    <div class="border-top pt-3 mt-3">
      <div class="form-row">
        <div class="form-group col">
          <label>Supplier Name</label>
          <input type="text" id="supplier_name" name="supplier_name" class="form-control" required>
        </div>
        <div class="form-group col">
          <label>Contact Person</label>
          <input type="text" id="contact_person" name="contact_person" class="form-control" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col">
          <label>Contact Number</label>
          <input type="text" id="contact_number" name="contact_number" class="form-control" required>
        </div>
        <div class="form-group col">
          <label>Address</label>
          <input type="text" id="address" name="address" class="form-control" required>
        </div>
      </div>

      <div class="form-row justify-content-between align-items-center mt-3">
        <input type="submit" value="Submit" class="btn btn-success">
        <strong>Total: <span id="grandTotal">₱0.00</span></strong>
      </div>
    </div>
  </form>
</div>


<div class="purchase-order-btn-container text-right mb-4">
    <button onclick="window.location.href='purchase_order.php'" class="btn btn-primary">Purchase Order</button>
</div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'true'): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    Swal.fire({
        icon: 'success',
        title: 'Submitted!',
        text: 'The purchase order was submitted successfully.',
        showConfirmButton: false,
        timer: 2000
    });

    setTimeout(function () {
        window.location.href = 'purchase_order.php';
    }, 2100);
});
</script>
<?php endif; ?>


<!-- SweetAlert Notification Script -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
    const firstHiddenInput = document.querySelector('.form-entry .id');
    if (firstHiddenInput) {
        console.log('Original first hidden input value:', firstHiddenInput.value);
    } else {
        console.warn('No original hidden input found in the first form entry!');
    }
});


    document.addEventListener("DOMContentLoaded", function () {
    const qtyInput = document.getElementById("qty");
    const unitPriceInput = document.getElementById("unit_price");
    const totalPriceDisplay = document.getElementById("totalPrice");
    const totalPriceField = document.getElementById("total_price");

    function updateTotal() {
        const qty = parseFloat(qtyInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const total = qty * unitPrice;

        totalPriceDisplay.textContent = "₱" + total.toFixed(2);
        totalPriceField.value = total.toFixed(2);
    }

    qtyInput.addEventListener("input", updateTotal);
    unitPriceInput.addEventListener("input", updateTotal);

    updateTotal(); // Initialize on load
});

document.addEventListener("DOMContentLoaded", function () {
    const formRowsContainer = document.getElementById('formRowsContainer');
    const addButtons = document.querySelectorAll('.add-sign');

    function createRemoveButton(target, hiddenIdInput) {
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-danger btn-sm remove-btn';
    removeBtn.textContent = '×';
    removeBtn.style.float = 'right';

    removeBtn.addEventListener('click', () => {
        const formEntries = document.querySelectorAll('#formRowsContainer .form-entry');
        if (formEntries.length === 1) {
            // Instead of removing, just clear fields
            console.log(`Clearing last remaining form-entry (MRF ID: ${hiddenIdInput.value})`);
            target.querySelectorAll('input').forEach(input => {
                if (input.type === 'hidden') {
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
            target.querySelector('.total_price_display').value = '₱0.00';
        } else {
            console.log(`Removing MRF ID: ${hiddenIdInput.value}`);
            target.remove();
        }
        updateFormNumbers();
        updateGrandTotal();
    });

    return removeBtn;
}

addButtons.forEach(button => {
    button.addEventListener('click', () => {
        const row = button.closest('tr');
        const category = row.cells[0].textContent.trim();
        const description = row.cells[1].textContent.trim();
        const qty = row.cells[2].textContent.trim();
        const unit = row.cells[3].textContent.trim();
        const id = row.getAttribute('data-id');

        console.log(`Adding MRF ID: ${id}`);

        const firstEntry = formRowsContainer.firstElementChild;
        const isFirstEmpty = firstEntry.querySelector('.category').value === '';

        // Clone the template but WITHOUT event listeners
        const target = isFirstEmpty ? firstEntry : firstEntry.cloneNode(true);

        // Fill in data
        target.querySelector('.category').value = category;
        target.querySelector('.item_description').value = description;
        target.querySelector('.qty').value = qty;
        target.querySelector('.unit').value = unit;
        target.querySelector('.unit_price').value = '';
        target.querySelector('.total_price_display').value = '₱0.00';

        // Set or create hidden ID
        let hiddenIdInput = target.querySelector('.id');
        if (!hiddenIdInput) {
            hiddenIdInput = document.createElement('input');
            hiddenIdInput.type = 'hidden';
            hiddenIdInput.name = 'id[]';
            hiddenIdInput.classList.add('id');
            target.appendChild(hiddenIdInput);
        }
        hiddenIdInput.value = id;

        // Remove any old remove button first
        const oldRemove = target.querySelector('.remove-btn');
        if (oldRemove) oldRemove.remove();

        // Add new remove button with fresh listener
        target.prepend(createRemoveButton(target, hiddenIdInput));

        // Append if not the first
        if (!isFirstEmpty) {
            formRowsContainer.appendChild(target);
        }

        // Reattach price calculation
        attachPriceCalculation(target);
        updateFormNumbers();
    });
});




    function attachPriceCalculation(container) {
        const qtyInput = container.querySelector(".qty");
        const unitPriceInput = container.querySelector(".unit_price");
        const totalDisplay = container.querySelector(".total_price_display");

        const updateTotal = () => {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(unitPriceInput.value) || 0;
            const total = qty * price;
            totalDisplay.value = "₱" + total.toFixed(2);
            updateGrandTotal();
        };

        qtyInput.addEventListener("input", updateTotal);
        unitPriceInput.addEventListener("input", updateTotal);
        updateTotal();
    }

    function updateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('.total_price_display').forEach(input => {
            const num = parseFloat(input.value.replace(/[^\d.-]/g, '')) || 0;
            grandTotal += num;
        });
        document.getElementById('grandTotal').textContent = "₱" + grandTotal.toFixed(2);
    }

    // Attach to first row on load
    attachPriceCalculation(formRowsContainer.firstElementChild);
});


document.getElementById('searchBtn').addEventListener('click', searchSummary);
document.getElementById('searchInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    searchSummary();
  }
});

function searchSummary() {
  const query = document.getElementById('searchInput').value.trim();
  const resultsDiv = document.getElementById('searchResults');
  const tbody = document.getElementById('searchResultsBody');

  if (!query) {
    resultsDiv.style.display = 'none';
    tbody.innerHTML = '';
    return;
  }

  // Format the date into a readable string
function formatDate(dateStr) {
  const date = new Date(dateStr);
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString(undefined, options); // Uses browser's locale
}

  fetch(`search_summary.php?q=${encodeURIComponent(query)}`)
    .then(response => response.json())
    .then(data => {
      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No results found. <br>  Please double-check your spelling <br>or try using different keywords.</td></tr>';
      } else {
        tbody.innerHTML = data.map(item => `
          <tr>
            <td>${item.item_description}</td>
            <td>${item.unit}</td>
            <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
            <td>${item.supplier_name}</td>
            <td>${formatDate(item.created_at)}</td>
          </tr>
        `).join('');
      }
      resultsDiv.style.display = 'block';
    })
    .catch(err => {
      console.error('Fetch error:', err);
      tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Error loading results.</td></tr>';
      resultsDiv.style.display = 'block';
    });
}

function updateFormNumbers() {
  const formEntries = document.querySelectorAll('#formRowsContainer .form-entry');
  formEntries.forEach((entry, index) => {
    const numberDiv = entry.querySelector('.form-number');
    if (numberDiv) {
      numberDiv.textContent = `Item ${index + 1}`;
    }
  });
}

// Call this once on page load
updateFormNumbers();

// Also call it every time you clone/add a new form-entry
// For example, inside your add button event listener, after appending:
formRowsContainer.appendChild(template);
updateFormNumbers();


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

<?php
$conn->close();
?>
