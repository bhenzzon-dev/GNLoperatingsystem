<?php
session_start();
if (!isset($_SESSION["architect_loggedin"]) || $_SESSION["architect_loggedin"] !== true) {
    header("location: architect_login.php");
    exit;
}
require_once "db_connect.php";

$sql = "SELECT id, project_name FROM projects ORDER BY created_at DESC";
$result = $conn->query($sql);

$mrfResult = $conn->query("SELECT COUNT(*) AS total FROM mrf");
$requestedResult = $conn->query("SELECT COUNT(*) AS total FROM requested_mrf");

$mrfCount = $mrfResult->fetch_assoc()['total'];
$requestedCount = $requestedResult->fetch_assoc()['total'];

$totalSubmitted = $mrfCount + $requestedCount;

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

.remove-item-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 1;
}

.modal.fade .modal-dialog {
  transform: translateY(-30px);
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  opacity: 0;
}

.modal.fade.show .modal-dialog {
  transform: translateY(0);
  opacity: 1;
}

.modal-backdrop.show {
  opacity: 0.5;
  backdrop-filter: blur(4px);
}

@keyframes fadeInSlideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.mrf-form-wrapper.animate-in {
  animation: fadeInSlideUp 0.4s ease-out;
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

.container.content {
  margin-left: 240px; /* Allow space for the sidebar */
  padding: 20px;
}

.right-side-bar {
  position: fixed;                /* stays in place */
  top: 50px;                      /* start 50px from top */
  right: 0;                       /* stick to right edge */
  width: 250px;                   /* set a width */
  height: calc(100% - 50px);      /* full height minus top gap */
  background: #fff;               /* white background */
  box-shadow: -3px 0px 15px rgba(0, 0, 0, 0.3); /* shadow on the left side */
  display: flex;
  flex-direction: column;         /* stack items vertically */
  padding: 20px;
}

.close-btn {
  position: absolute;
  top: 10px;
  right: 15px;
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #333;
  transition: color 0.2s ease;
}

.close-btn:hover {
  color: red;
}
.announcement {
  color: green;
  margin-bottom: 30px;
  margin-top: 10px;
  text-decoration: underline 1px;
  text-underline-offset: 5px;
  font: bold;
}

.text{
  font-size: 0.95rem; 
  line-height: 1.5;
}

@media (max-width: 768px) {
  .right-side-bar {
    position: relative;   /* no longer fixed */
    top: auto;            /* reset top */
    right: auto;          /* reset right */
    width: 80%;          /* take full width */
    height: auto;         /* shrink to content */
    box-shadow: 0 -3px 15px rgba(0, 0, 0, 0.1); /* shadow on top */
    margin-top: 20px;     /* spacing from above content */
    margin-left: 10px;
  }
}
/* Overall Layout */
body {
    background: #f4f6f9;
    font-family: "Segoe UI", Tahoma, sans-serif;
    color: #2c3e50;
  }

  

  .project-container {
  background: #fff;
  border: 1px solid #dcdcdc;
  padding: 25px;
  margin-bottom: 30px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  margin-top: 100px;

  width: auto;        /* 👈 container grows with content */
  max-width: 100%;    /* 👈 but it won’t overflow the screen */
  display: inline-block; /* 👈 needed so width:auto shrinks/grows with content */
}


  .project-container h3 {
    font-weight: 700;
    font-size: 1.4rem;
    border-bottom: 2px solid #2c3e50;
    padding-bottom: 8px;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
  }

  /* Project Button (Rectangular) */
  .project-btn {
    margin: 6px;
    padding: 12px 20px;
    font-weight: 600;
    font-size: 0.95rem;
    text-transform: uppercase;
    border-radius: 50px; /* sharp edges */
    border: 1px solid #2c3e50;
    background: #fff;
    color: #2c3e50;
    transition: all 0.25s ease;
  }
  .d-flex {
  display: flex;
  flex-wrap: wrap;   /* ✅ lets buttons go to the next line */
  justify-content: center; /* optional: center buttons */
}


  .project-btn:hover {
    background: #2c3e50;
    color: #fff;
    box-shadow: 0px 3px 15px rgba(0, 0, 0, 0.3);
  }
  @media (max-width: 768px) {
  .project-container .d-flex {
    flex-direction: column;   /* stack buttons */
    align-items: stretch;     /* make buttons full width */
  }

  .project-btn {
    width: 100%;              /* buttons take full container width */
    text-align: center;       /* center button text */
  }
  }

  /* Modal */
  .modal-content {
    border-radius: 0;
    border: 1px solid #ccc;
  }

  .modal-header {
    background: #2c3e50;
    color: #fff;
    border-radius: 0;
    padding: 15px 20px;
  }

  .modal-title {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 1.1rem;
  }

  .modal-body {
    background: #f9f9f9;
    padding: 20px;
  }

  /* MRF Form Group */
  .mrf-form-wrapper {
    border: 1px solid #dcdcdc;
    padding: 15px;
    margin-bottom: 15px;
    background: #fff;
  }

  .mrf-form-wrapper h6 {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 12px;
    text-transform: uppercase;
    font-size: 0.9rem;
  }

  .form-group label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #333;
    margin-bottom: 5px;
  }

  .form-control, .form-control:focus, select.form-control {
    border-radius: 0;
    border: 1px solid #ccc;
    box-shadow: none;
  }

  /* Buttons */
  #addMoreBtn {
    border-radius: 0;
    font-weight: 600;
    background: #2980b9;
    color: white;
    border: none;
  }

  #addMoreBtn:hover {
    background: #21618c;
  }

  .btn-success {
    border-radius: 0;
    font-weight: 600;
    background: #27ae60;
    border: none;
  }

  .btn-success:hover {
    background: #1e8449;
  }

  .btn-secondary {
    border-radius: 0;
    font-weight: 600;
  }

  .remove-item-btn {
    border-radius: 0;
    padding: 2px 6px;
    font-size: 0.8rem;
  }

@media (max-width: 768px) {
  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
    top: 0;
    border-right: none;
    border-bottom: 3px solid #444;
    flex-direction: row;
    flex-wrap: nowrap;
    overflow-x: auto;
    white-space: nowrap;
    padding: 10px;
    margin-top: 59px;
  }

  .sidebar a {
    display: inline-block;
    margin: 0 10px;
    padding: 10px 15px;
    white-space: nowrap;
  }

  .btn-logout {
    margin-top: 0;
    margin-left: auto;
    flex-shrink: 0;
  }

  .container.content {
    margin-left: 0;
    padding-top: 20px;
  }
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
    <a href="edit_mrf.php">Edit MRF</a>
    <a href="contact.php">Contact</a>
    <a href="contact.php">Support</a>
    <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
</div>

<div class="right-side-bar" id="sidebar">
  <button class="close-btn" onclick="closeSidebar()">✖</button>
  <h3 class="announcement">Announcement</h3>
  <p class="text">
    <strong>GOOD DAY OFFICERS</strong>,<br><br>
    We have a new update on our website.<br>
    Every officer is now required to add a shipping category for each request.<br>
    The purpose of this update is to let the admin track the shipping fee of your orders.<br><br>
    <strong>Thank you!</strong>
  </p>
</div>




<div class="container content">
  <!-- Container for Projects -->
  <div class="project-container">
    <!-- Title inside the container -->
    <h3>GNL PROJECTS</h3>

    <!-- Project Buttons -->
    <div class="d-flex">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <button
            class="btn project-btn"
            data-toggle="modal"
            data-target="#projectModal"
            data-id="<?php echo $row['id']; ?>"
            data-name="<?php echo htmlspecialchars($row['project_name']); ?>"
          >
            <?php echo htmlspecialchars($row['project_name']); ?>
          </button>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No projects found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>


  <!-- MRF Modal -->
  <div class="modal fade" id="projectModal" tabindex="-1" role="dialog" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="mrfForm" action="submit_mrf.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectModalLabel">Material Requisition Form</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
    <!-- Hidden project_id field -->
    <input type="hidden" id="modalProjectId" name="project_id">

    <!-- Container for all MRF groups -->
    <div id="mrfGroupContainer">
        <!-- Initial MRF Form Group -->
        <div class="mrf-form-wrapper">
            <div class="mrf-form-group">
                <h6 class="text-primary">Item 1</h6>
                <div class="mrf-form-group">
                    <label for="category">Category</label>
                    <select class="form-control" name="category[]" required>
                        <option value="Structural">Structural</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Finishing">Finishing</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Raw_Material">Raw Material</option>
                        <option value="Welding">Welding</option>
                        <option value="Construction">Construction</option>
                        <option value="Construction Equipment">Construction Equipment</option>
                        <option value="Shipping">Shipping Fee</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item_description">Item Description</label>
                    <input type="text" class="form-control" name="item_description[]" required>
                </div>
                <div class="form-group">
                    <label for="qty">Quantity</label>
                    <input type="number" class="form-control" name="qty[]" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <select class="form-control" name="unit[]" required>
                      <option value="kg">kg</option>
                      <option value="liter">liter</option>
                      <option value="piece">piece</option>
                      <option value="sack">sack</option>
                      <option value="box">box</option>
                      <option value="meter">meter</option>
                      <option value="Cubic">Cubic Meters</option>
                      <option value="pack">pack</option>
                      <option value="set">set</option>
                      <option value="roll">roll</option>
                      <option value="bottle">bottle</option>
                      <option value="can">can</option>
                      <option value="sheets">sheets</option>
                      <option value="bags">bags</option>
                      <option value="bucket">bucket</option>
                      <option value="trip">trip</option>
                  </select>

                </div>
                <div class="form-group">
                  <label for="comment">Add Comment <small class="text-muted">(optional)</small></label>
                  <input type="text" class="form-control" name="comment[]">
              </div>
            </div>
        </div>
    </div>

    <!-- Add More Button -->
    <button type="button" class="btn btn-info mt-2" id="addMoreBtn">Add More</button>
</div>


            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Submit MRF</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script>
    // When modal opens, inject project ID
    $('#projectModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const projectId = button.data('id');
        $('#modalProjectId').val(projectId);
    });

    // Add More functionality
    $('#addMoreBtn').on('click', function () {
        const itemCount = $('.mrf-form-wrapper').length + 1;

        const original = $('.mrf-form-group').first();
        const cloneWrapper = $('<div class="mrf-form-wrapper position-relative border rounded p-3 mb-3 bg-light animate-in"></div>');
        const clone = original.clone();

        // Clean inputs
        clone.find('input').val('');
        clone.find('select').val('');

        // Remove old labels
        clone.find('h6.text-primary').remove();
        clone.find('hr').remove();

        // Add new header
        clone.prepend(`<hr><h6 class="text-primary">Item ${itemCount}</h6>`);

        // Add remove button
        const removeBtn = $('<button type="button" class="btn btn-danger btn-sm remove-item-btn" style="position:absolute; top:10px; right:10px;">&times;</button>');
        removeBtn.on('click', function () {
            cloneWrapper.remove();
            updateItemLabels(); // (make sure you defined this)
        });

        cloneWrapper.append(removeBtn).append(clone);
        $('#mrfGroupContainer').append(cloneWrapper);

        // Remove animation class after it runs once
        setTimeout(() => {
            cloneWrapper.removeClass('animate-in');
        }, 500);
    });

    // Reset modal when hidden
    $('#projectModal').on('hidden.bs.modal', function () {
        const form = document.getElementById('mrfForm');
        form.reset();

        // Remove all clones, keep only the first/original
        $('#mrfGroupContainer .mrf-form-wrapper:not(:first)').remove();

        // Clean up the original group (remove added labels/lines)
        $('#mrfGroupContainer .mrf-form-wrapper:first h6.text-primary').remove();
        $('#mrfGroupContainer .mrf-form-wrapper:first hr').remove();
    });

    // AJAX form submission
    document.getElementById('mrfForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        const formData = new FormData(form);

        fetch('submit_mrf.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(response => {
            $('#projectModal').modal('hide');

            Swal.fire({
                icon: 'success',
                title: 'MRF Added',
                text: 'Material Requisition Form has been successfully submitted!',
                timer: 2000,
                showConfirmButton: false
            });
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Something went wrong. Please try again.',
            });
        });
    });

    function closeSidebar() {
    document.getElementById("sidebar").style.display = "none";
  }
</script>

</body>
</html>

<?php $conn->close(); ?>