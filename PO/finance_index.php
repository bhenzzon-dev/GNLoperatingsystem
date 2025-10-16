<?php

ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params([
    'lifetime' => 3600, // cookie valid for 1 hour
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
if (!isset($_SESSION["finance_loggedin"]) || $_SESSION["finance_loggedin"] !== true) {
    header("location: finance_login.php");
    exit;
}
require_once 'db_connect.php';

// Fetch all distinct project IDs and their project names, along with MRF count
$projectQuery = "SELECT DISTINCT 
                    p.id AS project_id, 
                    p.project_name, 
                    (
                        SELECT COUNT(*) 
                        FROM mrf 
                        WHERE project_id = p.id AND status IN ('Pending', 'Acknowledged')
                    ) AS mrf_count
                 FROM projects p
                 ORDER BY p.project_name ASC";

$projectsResult = $conn->query($projectQuery);

$idQuery = $conn->prepare("SELECT id FROM mrf WHERE mrf_id = ?");
$idQuery->bind_param("s", $mrfRow['mrf_id']);
$idQuery->execute();
$idResult = $idQuery->get_result();
$idArray = [];

while ($idRow = $idResult->fetch_assoc()) {
    $idArray[] = $idRow['id'];
}
$idList = implode(',', $idArray);
$idQuery->close();

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
    <title>Material Requisition Forms | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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

        .container.content {
            margin-left: 240px;
            padding: 20px;
        }

        .project-section {
            margin-bottom: 40px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative; /* Make this container relative for positioning the count */
            width: 102%;
        }

        .mrf-group-table {
            display: none;
            margin-top: 15px;
        }

        .toggle-btn {
            min-width: 140px;
            text-align: left;
            margin-bottom: 8px;
        }

        .mrf-container h3 {
            font-weight: 600;
            font-size: 28px;
            color: #333;
            text-transform: uppercase;
            margin-bottom: 30px;
            text-align: center;
        }
        .mrf-btn-narrow {
            width: 250px; /* Adjust to your preference */
            text-align: left;
        }

        .mrf-count {
            position: absolute;
            top: 10px;
            right: 20px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 5px 10px;
            font-size: 14px;
            font-weight: bold;
        }
/* Modal content styling */
.modal-content {
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  background-color: #fff;
}

/* Modal header styling */
.modal-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 1.5rem;
  border-radius: 10px 10px 0 0;
}

.modal-title {
  font-weight: 600;
  color: #495057;
  font-size: 1.25rem;
}

.close {
  color: #495057;
  font-size: 1.5rem;
  background: none;
  border: none;
}

/* Modal body styling with overflow and padding */
.modal-body {
  padding: 1.5rem;
  max-height: 400px; /* Set a max height for the modal */
}

/* Input fields styling */
.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  font-weight: 500;
  color: #495057;
  font-size: 1rem;
}

.form-control {
  border-radius: 8px;
  padding: 0.8rem;
  border: 1px solid #ced4da;
  font-size: 1rem;
  color: #495057;
}

.form-control:focus {
  border-color: #007bff;
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

.form-group select {
  width: 100%;
  padding: 0.8rem;
  border-radius: 8px;
  border: 1px solid #ced4da;
  color: #495057;
  background-color: #fff;
  z-index: 2; /* Ensure the select dropdown is above other content */
}

/* Modal footer styling */
.modal-footer {
  background-color: #f8f9fa;
  border-top: 1px solid #e9ecef;
  padding: 1rem;
  border-radius: 0 0 10px 10px;
  text-align: right;
}

.btn-primary {
  padding: 0.5rem 2rem;
  border-radius: 8px;
  font-weight: 600;
  background-color: #007bff;
  border: none;
  color: white;
}

.btn-primary:hover {
  background-color: #0056b3;
}
.modal-container {
    margin-top: 8%;
}
.modal.fade .modal-dialog {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  transform: translateY(-30px);
  opacity: 0;
}

.modal.show .modal-dialog {
  transform: translateY(0);
  opacity: 1;
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
    <a class="navbar-brand d-flex align-items-center" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo" style="height: 40px;">
        <span class="ml-2 font-weight-bold text-uppercase" style="color: gold;">
    GNL Development Corporation
</span>

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

<div class="container content mrf-container">
    <h3 class="mb-4">Material Requisition Forms</h3>

    <?php while ($project = $projectsResult->fetch_assoc()): ?>
        <div class="project-section">
        <h4 class="mb-3 text-primary" data-project-id="<?php echo $project['project_id']; ?>">
          <?php echo $project['project_name']; ?>
          <span class="mrf-count"><?php echo $project['mrf_count']; ?></span>
      </h4>

            <?php
$mrfQuery = $conn->prepare("
    SELECT mrf_id, MIN(created_at) AS created_at 
    FROM mrf 
    WHERE project_id = ? 
      AND status IN ('Pending' , 'acknowledged')
    GROUP BY mrf_id 
    ORDER BY created_at DESC
");
$mrfQuery->bind_param("i", $project['project_id']);
$mrfQuery->execute();
$mrfIdsResult = $mrfQuery->get_result();
?>


<?php if (!empty($project['mrf_count']) && $project['mrf_count'] > 0): ?>
    <button class="btn btn-info mb-3 toggle-project-btn" data-project="<?php echo $project['project_id']; ?>">
        Show MRFs for this Project
    </button>
<?php else: ?>
    <p class="text-muted mb-3">No MRFs found for this project.</p>
<?php endif; ?>


            <div class="mrf-group-table" id="mrf-table-<?php echo $project['project_id']; ?>" style="display:none;">
                <div class="d-flex flex-column align-items-start">
                    <?php while ($mrfRow = $mrfIdsResult->fetch_assoc()): ?>
                        <?php $formattedDate = date("F j, Y", strtotime($mrfRow['created_at'])); ?>
                        <div class="mb-2">
                            <button class="btn btn-outline-primary toggle-btn mrf-btn-narrow" data-mrf="<?php echo $mrfRow['mrf_id']; ?>">
                                MRF sent on <?php echo $formattedDate; ?>
                            </button>

                            <div class="mrf-table-wrapper" id="mrf-table-<?php echo $mrfRow['mrf_id']; ?>">
                                <table class="table table-bordered table-striped mt-2">
                                    <thead class="thead-dark">
                                        <tr>
                                           <th>Category</th>
                                            <th>Item Description</th>
                                            <th>Note</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php
                                    $itemQuery = $conn->prepare("
                                        SELECT id, category, item_description, comment, qty, unit, status 
                                        FROM mrf 
                                        WHERE mrf_id = ? 
                                          AND status IN ('Pending', 'Acknowledged')
                                    ");

                                    $itemQuery->bind_param("s", $mrfRow['mrf_id']);
                                    $itemQuery->execute();
                                    $itemsResult = $itemQuery->get_result();
                                    $idArray = [];
                                    while ($item = $itemsResult->fetch_assoc()):
                                        $idArray[] = $item['id'];
                                    ?>
                                            <tr data-id="<?php echo $item['id']; ?>">
                                            <td class="category-cell"><?php echo htmlspecialchars($item['category']); ?></td>
                                            <td class="description-cell"><?php echo htmlspecialchars($item['item_description']); ?></td>
                                            <td class="comment-cell"><?php echo htmlspecialchars($item['comment']); ?></td>
                                            <td class="qty-cell"><?php echo $item['qty']; ?></td>
                                            <td class="unit-cell"><?php echo htmlspecialchars($item['unit']); ?></td>
                                            <td class="status-cell"><?php echo htmlspecialchars($item['status']); ?></td>
                                            <td class="d-flex flex-wrap gap-1">
                                                <button
                                                class="btn btn-sm btn-warning action-btn mb-1 mr-1 edit-btn"
                                                data-toggle="modal"
                                                data-target="#editItemModal"
                                                data-item='<?php echo json_encode($item); ?>'
                                                >Edit</button>
                                                    <button class="btn btn-sm btn-danger delete-btn action-btn mb-1 mr-1" data-id="<?= $item['id']; ?>">Delete</button>
                                                    <button class="btn btn-sm btn-success action-btn mb-1 mr-1" data-id="<?= $item['id']; ?>" data-status="Acknowledged">Acknowledged</button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <?php $itemQuery->close(); ?>
                                    </tbody>
                                </table>


                                <div class="text-right">
                                <?php
                                    // Generate comma-separated IDs for current mrf_id
                                    $idList = implode(',', $idArray);
                                    ?>
                                    <button class="btn btn-sm btn-primary mt-2"
                                        data-mrf-id="<?php echo $mrfRow['mrf_id']; ?>"
                                        data-id-list="<?php echo htmlspecialchars($idList); ?>"
                                        onclick="window.location.href='create_po_form.php?mrf_id=<?php echo $mrfRow['mrf_id']; ?>&ids=<?php echo urlencode($idList); ?>';">
                                        Create PO 
                                    </button>
                                </div>

                            </div> <!-- closes table container -->
                        </div> <!-- closes mb-2 -->
                    <?php endwhile; ?>
                    <?php $mrfQuery->close(); ?>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>
<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
<div class="modal-container">                                         
<div class="modal-dialog" role="document">
    <form id="editItemForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit MRF Item</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editItemId">

          <div class="form-group">
            <label for="editCategory">Category</label>
            <select class="form-select" id="editCategory" required>
              <option value="Structural">Structural</option>
              <option value="Plumbing">Plumbing</option>
              <option value="Electrical">Electrical</option>
              <option value="Finishing">Finishing</option>
            </select>
          </div>

          <div class="form-group">
            <label for="editDescription">Item Description</label>
            <input type="text" class="form-control" id="editDescription" required>
          </div>

          <div class="form-group">
            <label for="editQty">Quantity</label>
            <input type="number" class="form-control" id="editQty" required>
          </div>

          <div class="form-group">
            <label for="editUnit">Unit</label>
            <select class="form-select" id="editUnit" name="unit" required>
              <option value="kg">kg</option>
              <option value="liter">liter</option>
              <option value="piece">piece</option>
              <option value="can">sack</option>
              <option value="box">box</option>
              <option value="meter">meter</option>
              <option value="dozen">dozen</option>
              <option value="pack">pack</option>
              <option value="set">set</option>
              <option value="roll">roll</option>
              <option value="bottle">bottle</option>
              <option value="can">can</option>
            </select>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </div>
    </form>
  </div>
</div>
                                        </div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>

<script>
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');

    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });

 // Toggle project MRF list
// On page load, hide all mrf-table elements
document.querySelectorAll('[id^="mrf-table-"]').forEach(el => {
    el.style.display = 'none';
});

// Toggle project buttons
document.querySelectorAll('.toggle-project-btn').forEach(button => {
    button.addEventListener('click', () => {
        const projectId = button.getAttribute('data-project');
        const target = document.getElementById('mrf-table-' + projectId);
        if (!target) return;
        target.style.display = target.style.display === 'none' ? 'block' : 'none';
    });
});

// Toggle buttons with data-mrf attribute
document.querySelectorAll('button[data-mrf]').forEach(button => {
    button.addEventListener('click', () => {
        const mrfId = button.getAttribute('data-mrf');
        const target = document.getElementById('mrf-table-' + mrfId);
        if (!target) return;
        target.style.display = target.style.display === 'none' ? 'block' : 'none';
    });
});



    
    document.querySelectorAll('.edit-btn').forEach(button => {
  button.addEventListener('click', function () {
    const item = JSON.parse(this.getAttribute('data-item'));
    document.getElementById('editItemId').value = item.id;
    document.getElementById('editCategory').value = item.category;
    document.getElementById('editDescription').value = item.item_description;
    document.getElementById('editQty').value = item.qty;
    document.getElementById('editUnit').value = item.unit;
  });
});

document.getElementById('editItemForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append('id', document.getElementById('editItemId').value);
  formData.append('category', document.getElementById('editCategory').value);
  formData.append('item_description', document.getElementById('editDescription').value);
  formData.append('qty', document.getElementById('editQty').value);
  formData.append('unit', document.getElementById('editUnit').value);

  fetch('update_mrf.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      Swal.fire({
        icon: 'success',
        title: 'Item Updated Successfully!',
        text: 'The item has been updated.',
        showConfirmButton: true,
      }).then(() => {
        // Update the table row without reloading the page
        const row = document.querySelector(`tr[data-id="${response.id}"]`);
        row.querySelector('.category-cell').textContent = response.category;
        row.querySelector('.description-cell').textContent = response.item_description;
        row.querySelector('.qty-cell').textContent = response.qty;
        row.querySelector('.unit-cell').textContent = response.unit;
        row.querySelector('.status-cell').textContent = response.status;
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Update Failed',
        text: response.message,
        showConfirmButton: true,
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'An error occurred',
      text: 'There was a problem with the update request.',
      showConfirmButton: true,
    });
  });

  $('#editItemModal').modal('hide'); // Close the modal after submission
});


document.querySelectorAll('.action-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.getAttribute('data-id');
        const status = this.getAttribute('data-status');

        // Only run if it's a status button (Received or Acknowledged)
        if (!id || !status) return;

        fetch('update_mrf_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id=${id}&status=${status}`
        })
        .then(response => response.text())
        .then(data => {
            if (data === 'success') {
                // Show success notification using SweetAlert
                Swal.fire({
                    icon: 'success',
                    title: 'Status Updated',
                    text: `MRF #${id} is now marked as ${status}.`,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Update the status locally
                const row = this.closest('tr');  // Find the row the button is in
                const statusCell = row.querySelector('td:nth-child(5)'); // Assuming status is in the 5th column
                statusCell.textContent = status;  // Update the status text in the table

                // Optionally change button color to reflect the new status
                const statusButtons = row.querySelectorAll('.action-btn');
                statusButtons.forEach(btn => {
                    if (btn.getAttribute('data-status') === status) {
                        btn.classList.add('disabled');  // Disable the button that was clicked
                    } else {
                        btn.classList.remove('disabled');
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: 'Please try again later.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Could not connect to server.'
            });
        });
    });
});

function updateMRFCount(projectId) {
    fetch('get_mrf_count.php?project_id=' + projectId)
        .then(response => response.json())
        .then(data => {
            const countSpan = document.querySelector(
                `h4[data-project-id="${projectId}"] .mrf-count`
            );
            if (countSpan) {
                countSpan.textContent = data.mrf_count;
            }
        });
}
document.addEventListener('click', function(e) {
    if (e.target.matches('[data-status="Acknowledged"]')) {
        const itemId = e.target.getAttribute('data-id');

        fetch('get_mrf_count.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${itemId}&status=Acknowledged`
        })
        .then(res => res.text())
        .then(() => {
            // Optionally update the status cell
            const row = e.target.closest('tr');
            if (row) {
                row.querySelector('.status-cell').textContent = 'Acknowledged';
            }

            // Find project ID by traversing up DOM
            const projectSection = e.target.closest('.project-section');
            const h4 = projectSection.querySelector('h4[data-project-id]');
            const projectId = h4?.getAttribute('data-project-id');

            if (projectId) {
                updateMRFCount(projectId);
            }
        });
    }
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

document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function () {
        const itemId = this.getAttribute('data-id');

        Swal.fire({
            title: 'Are you sure?',
            text: "This item will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('delete_mrf_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${itemId}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        Swal.fire(
                            'Deleted!',
                            'Item has been deleted.',
                            'success'
                        );

                        // Remove the row from the table
                        const row = button.closest('tr');
                        if (row) row.remove();
                    } else {
                        Swal.fire(
                            'Failed!',
                            'Could not delete the item.',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'Error!',
                        'There was a problem contacting the server.',
                        'error'
                    );
                });
            }
        });
    });
});
</script>

</body>
</html>

<?php $conn->close(); ?>
