<?php
session_start();
if (!isset($_SESSION["architect_loggedin"]) || $_SESSION["architect_loggedin"] !== true) {
    header("location: architect_login.php");
    exit;
}

require_once "db_connect.php";

// Fetch projects list
$sql = "SELECT id, project_name FROM projects ORDER BY created_at DESC";
$result = $conn->query($sql);

// Count MRFs
$mrfResult = $conn->query("SELECT COUNT(*) AS total FROM mrf");
$requestedResult = $conn->query("SELECT COUNT(*) AS total FROM requested_mrf");
$mrfCount = $mrfResult->fetch_assoc()['total'];
$requestedCount = $requestedResult->fetch_assoc()['total'];
$totalSubmitted = $mrfCount + $requestedCount;

// Fetch all MRFs grouped by project_id
$groupedQuery = "
  SELECT p.project_name, m.project_id, m.id, m.item_description, m.qty, m.status, m.created_at
  FROM mrf AS m 
  JOIN projects AS p ON m.project_id = p.id
  WHERE m.status = 'Pending'
  ORDER BY p.project_name, m.created_at DESC
";
$groupedResult = $conn->query($groupedQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Architect</title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css"
    crossorigin="anonymous"
  >
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body {
  font-family: 'Poppins', sans-serif;
  background-color: #f7f9fc;
  margin: 0;
  padding: 0;
}

.navbar {
  background: #2c3e50;
  backdrop-filter: blur(8px);
}

.navbar-brand img {
  height: 30px;
}

.nav-link {
  font-weight: 500;
  color: #fff !important; /* Make the text color white */

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
  justify-content: flex-start; /* Align items to the top */
  border-right: 3px solid #444;
  box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); /* Adding a subtle shadow */
}

.sidebar a {
  color: #fff;
  text-decoration: none;
  padding: 8px 20px;
  margin: 10px 0; /* Small margin for spacing between links */
  border-radius: 6px;
  transition: background-color 0.3s ease;
  font-weight: 500;
  text-transform: uppercase;
}

.sidebar a:hover {
  background-color: #555;
}

.btn-logout {
  background-color: #dc3545; /* Red background */
  color: #fff;
  border: none;
  padding: 5px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  margin-top: 320%; /* Push it to the bottom */
  width: 100%; /* Full width */
  font-weight: bold;
  text-transform: uppercase;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
  transition: background-color 0.3s, transform 0.2s ease-in-out;
}

.btn-logout:hover {
  background-color: #c82333; /* Darker red on hover */
  transform: translateY(-2px); /* Slightly lift the button on hover */
}

.btn-logout:active {
  transform: translateY(0); /* Return to normal when clicked */
}

/* MAIN CONTAINER */
.main-container {
  margin-left: 240px; /* Offset for sidebar */
  padding: 40px;
  margin-top: 80px; /* Offset for navbar */
  background-color: #f7f9fc;
  min-height: 100vh;
}

.main-container h2 {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 30px;
}

/* PROJECT CARD */
.project-card {
  background: #fff;
  border-radius: 12px;
  margin-bottom: 30px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.project-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 14px rgba(0,0,0,0.1);
}

/* CARD HEADER */
.project-card .card-header {
  background-color: #2c3e50;
  color: white;
  font-weight: 600;
  padding: 12px 20px;
  text-transform: uppercase;
}

/* TABLE STYLING */
.project-card table {
  width: 100%;
  border-collapse: collapse;
}

.project-card th,
.project-card td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #eee;
  font-size: 14px;
  color: gray;
}

.project-card th {
  background-color: #f2f3f5;
  font-weight: 600;
  color: #333;
}

.project-card tbody tr:hover {
  background-color: #f9fafb;
}

/* STATUS BADGES */
.badge-success {
  background-color: #28a745;
  color: white;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 12px;
}

.badge-danger {
  background-color: #dc3545;
  color: white;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 12px;
}

.badge-secondary {
  background-color: #6c757d;
  color: white;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 12px;
}

.modal-container{
    margin-top: 50px;
}

.modal-content {
  border-radius: 10px;
  margin-top: 10%;
}
.modal-header {
  border-top-left-radius: 10px;
  border-top-right-radius: 10px;
}


</style>
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php">
      <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a class="nav-link" href="architect_index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        <li class="nav-item active"><a class="nav-link" href="architect_index.php">Projects</a></li>
      </ul>
    </div>
  </nav>

  <div class="sidebar">
    <a href="architect_index.php">Upload MRF</a>
    <a href="uploaded_mrf.php" style="position: relative; display: inline-block;">
  Submitted MRF
  <span class="badge badge-primary" style="
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(50%, -50%);
    font-size: 12px;
    padding: 8px 8px;
    border-radius: 50%;
  ">
    <?php echo $totalSubmitted; ?>
  </span>
</a>

    <a href="contact.php">Contact</a>
    <a href="contact.php">Support</a>
    <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
</div>

<div class="main-container">
  <h2>MRF Records by Project</h2>

  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">
      ✅ Record #<?php echo htmlspecialchars($_GET['id']); ?> updated successfully.
    </div>
  <?php endif; ?>

  <?php
  if ($groupedResult->num_rows > 0) {
      $currentProject = null;

      while ($row = $groupedResult->fetch_assoc()) {
          if ($currentProject !== $row['project_name']) {
              if ($currentProject !== null) {
                  echo "</tbody></table></div></div>";
              }

              echo "<div class='project-card'>";
              echo "<div class='card-header'>" . htmlspecialchars($row['project_name']) . "</div>";
              echo "<div class='card-body'>";
              echo "<table class='table table-bordered table-striped'>
                      <thead class='thead-dark'>
                        <tr>
                          <th>ID</th>
                          <th>Material Name</th>
                          <th>Quantity</th>
                          <th>Status</th>
                          <th>Created At</th>
                          <th>Action</th>
                        </tr>
                      </thead><tbody>";

              $currentProject = $row['project_name'];
          }

          $statusClass = ($row['status'] === 'Success')
              ? 'badge-success'
              : (($row['status'] === 'Denied') ? 'badge-danger' : 'badge-secondary');

          echo "<tr>
                  <td>{$row['id']}</td>
                  <td>" . htmlspecialchars($row['item_description']) . "</td>
                  <td>{$row['qty']}</td>
                  <td><span class='badge {$statusClass}'>{$row['status']}</span></td>
                  <td>" . date("F j, Y · g:i A", strtotime($row['created_at'])) . "</td>
                  <td>
                    <button 
                        class='btn btn-primary btn-sm editBtn' 
                        data-id='{$row['id']}'
                    >Edit</button>

                    <button 
                        class='btn btn-danger btn-sm cancelBtn' 
                        data-id='{$row['id']}'
                    >Cancel</button>
                    </td>
                </tr>";
      }

      echo "</tbody></table></div></div>";
  } else {
      echo "<div class='alert alert-info'>No MRF records found.</div>";
  }

  $conn->close();
  ?>
</div>

<!-- Modal -->
<div class="modal-container">
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="editForm">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title">Edit MRF Record</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="mrf_id">

          <div class="form-group">
            <label>Material Name</label>
            <input type="text" name="item_description" id="item_description" class="form-control" required>
          </div>

          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="qty" id="qty" class="form-control" required>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<!-- ✅ Required for Bootstrap 4 modals -->
<script src="https://code.jquery.com/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script>
// When Edit button is clicked
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.dataset.id;

    // Fetch record details
    fetch('update_edit.php?id=' + id)
      .then(response => response.json())
      .then(data => {
        document.getElementById('mrf_id').value = data.id;
        document.getElementById('item_description').value = data.item_description;
        document.getElementById('qty').value = data.qty;

        // Show modal
        $('#editModal').modal('show');
      });
  });
});

// Handle form submission
document.getElementById('editForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);

  fetch('update_edit.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(() => {
    $('#editModal').modal('hide');
    Swal.fire({
      icon: 'success',
      title: 'Updated!',
      text: 'The record has been updated successfully.',
      showConfirmButton: false,
      timer: 1500
    }).then(() => {
      location.reload();
    });
  });
});
// Handle Cancel button click
document.querySelectorAll('.cancelBtn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.dataset.id;

    Swal.fire({
      title: 'Are you sure?',
      text: "This will mark the MRF as Cancelled.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, cancel it!',
      cancelButtonText: 'No, keep it'
    }).then((result) => {
      if (result.isConfirmed) {
        fetch('update_edit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'action=cancel&id=' + encodeURIComponent(id)
        })
        .then(response => response.text())
        .then(() => {
          Swal.fire({
            icon: 'success',
            title: 'Cancelled!',
            text: 'The record has been marked as Cancelled.',
            showConfirmButton: false,
            timer: 1500
          }).then(() => {
            location.reload();
          });
        });
      }
    });
  });
});
</script>
</body>
</html>