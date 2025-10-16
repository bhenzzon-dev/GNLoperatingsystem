<?php
session_start();
if (!isset($_SESSION["architect_loggedin"]) || $_SESSION["architect_loggedin"] !== true) {
    header("location: architect_login.php");
    exit;
}
require_once "db_connect.php";

// Fetch all projects
$sql_projects = "SELECT id, project_name FROM projects ORDER BY created_at DESC";
$result_projects = $conn->query($sql_projects);

// Fetch combined MRF data from mrf and requested_mrf
$sql_mrf = "
    SELECT m.id, m.category, p.project_name, m.item_description, m.qty, m.unit, m.comment, m.created_at, m.mrf_id, m.status
    FROM mrf m
    JOIN projects p ON m.project_id = p.id
    UNION ALL
    SELECT r.id, r.category, p.project_name, r.item_description, r.qty, r.unit, r.comment, r.created_at, r.mrf_id, r.status
    FROM requested_mrf r
    JOIN projects p ON r.project_id = p.id
    ORDER BY project_name, created_at DESC
";

$result_mrf = $conn->query($sql_mrf);

// Group results by status
$status_groups = [];

if ($result_mrf && $result_mrf->num_rows > 0) {
    while ($row = $result_mrf->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $project_name = $row['project_name'];

        if (!isset($status_groups[$status][$project_name])) {
            $status_groups[$status][$project_name] = [];
        }

        $status_groups[$status][$project_name][] = $row;

    }
}
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
  <title>Uploaded MRFs</title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css"
    crossorigin="anonymous"
  >
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

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
      margin-top: 320%;
      width: 100%;
      font-weight: bold;
      text-transform: uppercase;
    }

    .container.content {
      margin-left: 240px;
      padding: 20px;
    }

    .project-container {
      background: linear-gradient(to bottom right, #ffffff, #f7f9fb);
      border-radius: 16px;
      padding: 40px 30px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
      margin-top: 80px;
      max-width: 1000px;
      margin-left: 20%;
      margin-right: auto;
      margin: 0 auto;
      width: 100%;
      max-width: 1000px;
      margin-top: 100px;
      margin-right: 50px;
    }

    .project-container h3 {
      font-weight: 700;
      font-size: 36px;
      color: #2c3e50;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 20px;
      text-align: center;
      position: relative;
      padding-bottom: 10px;
    }

    .mrf-form-wrapper {
      position: relative;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 15px;
      margin-bottom: 15px;
      background-color: #f9f9f9;
    }

    .mrf-form-group {
      margin-bottom: 10px;
    }

    .mrf-form-group input, .mrf-form-group select {
      width: 100%;
      padding: 8px;
      border-radius: 4px;
      border: 1px solid #ccc;
    }

    .mrf-form-group label {
      font-weight: bold;
      color: #333;
    }

    .mrf-container {
      margin-top: 30px;
    }

    .mrf-item {
      margin-bottom: 20px;
    }

    .mrf-header {
      font-size: 18px;
      font-weight: bold;
      color: #1167b1;
    }

    .mrf-details {
      font-size: 14px;
      color: #555;
    }
    /* Make all tab text black */
.nav-tabs .nav-link {
  color: black !important;
}

@media (max-width: 768px) {
  .sidebar {
    position: relative;
    top: 0;
    width: 100%;
    height: auto;
    flex-direction: row;
    flex-wrap: nowrap;
    overflow-x: auto;
    white-space: nowrap;
    border-right: none;
    border-bottom: 3px solid #444;
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
}

@media (max-width: 768px) {
  .container.content {
    margin-left: 0;
    padding: 15px;
  }

  .project-container {
    margin-left: auto;
    margin-right: auto;
    padding: 20px 15px;
    margin-top: 40px;
    max-width: 100%;
    box-sizing: border-box;
  }

  .project-container h3 {
    font-size: 24px;
    letter-spacing: 1px;
    padding-bottom: 8px;
  }

  .mrf-form-wrapper {
    padding: 10px;
  }

  .mrf-form-group input,
  .mrf-form-group select {
    padding: 6px;
    font-size: 14px;
  }

  .mrf-header {
    font-size: 16px;
  }

  .mrf-details {
    font-size: 13px;
  }
}
@media (max-width: 768px) {
  .container.content {
    margin-left: 0;
    padding: 5px;
    font-size: 8px; /* Smaller overall font size */
  }

  .project-container {
    padding: 10px;
    margin-top: 20px;
  }

  table {
    font-size: 8px;
  }

  th, td {
    padding: 4px 6px;
  }
}
.layout-container {
  display: flex;
  flex-direction: row;
  gap: 0; /* Increased gap */
  align-items: flex-start;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding: 0 10px;
}


.main-column {
  flex: 5;
  display: flex;
  flex-direction: column;
}

.side-column {
  flex: 1;
  min-width: 250px;
  max-width: 300px;
}

.side-container {
  background-color: #f8f9fa;
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 4px;
  margin-top: 100px;
  min-height: 400px;
  margin-right: 50px;
}


.project-header {
  background-color: #e3f2fd;
  font-weight: bold;
}

/* Optional: Make responsive on smaller screens */
@media (max-width: 768px) {
  .layout-container {
    flex-direction: column;
  }
}

#calendar table {
  font-size: 14px;
}

#calendar th {
  background-color: #f1f1f1;
  font-weight: bold;
}

#calendar td {
  vertical-align: middle;
  text-align: center;
  padding: 6px;
}

.today-highlight {
  background-color: #1167b1;
  color: #fff;
  font-weight: bold;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: inline-block;
  line-height: 36px;
}

.created-highlight {
  background-color: #ffc107;
  color: #000;
  font-weight: bold;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: inline-block;
  line-height: 36px;
  cursor: pointer;
}

@media (max-width: 576px) {
  .layout-container {
    display: flex;              /* ✅ Needed to enable flex behavior */
    flex-direction: column;     /* ✅ Stack vertically */
    padding: 0 5px;
    gap: 10px;
  }

  .side-container {
    order: -1;                  /* ✅ Move calendar above */
    margin-top: 20px;
    padding: 10px;
    border-radius: 6px;
    min-height: auto;
  }

  .main-column {
    flex: 1 1 100%;
    width: 100%;
  }
}


  </style>
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php">
      <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ml-auto">
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

  <div class="layout-container">
  
  <!-- Main Column -->
<div class="main-column">
  <div class="project-container">
    <h3 id="mrf-title">Submitted MRFs</h3>

    <div class="table-responsive mt-3">
      <table class="table table-bordered table-striped" id="mrf-table">
        <thead class="thead-dark">
          <tr>
            <th>Category</th>
            <th>Project Name</th>
            <th>Item Description</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Created At</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $createdDates = []; // to be used in JS

          // Step 1: Flatten all entries from status_groups
          $all_entries = [];
          foreach ($status_groups as $status => $entries) {
              foreach ($entries as $project_name => $grouped_entries) {
                  foreach ($grouped_entries as $row) {
                      $all_entries[] = $row;
                  }
              }
          }

          // Step 2: Group by project
          $projects = [];
          foreach ($all_entries as $row) {
              $projects[$row['project_name']][] = $row;
          }

          // Step 3: Render table grouped by project
          foreach ($projects as $project_name => $entries):
              echo "<tr><td colspan='7' class='font-weight-bold bg-warning text-dark'>{$project_name}</td></tr>";

              foreach ($entries as $row):
                  $dateOnly = date('Y-m-d', strtotime($row['created_at']));
                  $createdDates[] = $dateOnly;
          ?>
                  <tr data-date="<?= $dateOnly ?>">
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars($row['project_name']) ?></td>
                    <td><?= htmlspecialchars($row['item_description']) ?></td>
                    <td><?= number_format((float)$row['qty'], 0) ?></td>
                    <td><?= htmlspecialchars($row['unit']) ?></td>
                    <td><?= date('F j, Y g:i A', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                  </tr>
          <?php
              endforeach;
          endforeach;
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>



  <!-- Side Column -->
  <div class="side-container">
  <h4>Calendar Tracker</h4>
  <div id="calendar"></div>
  <button id="reset-filter" class="btn btn-secondary btn-sm mt-2">Reset View</button>

</div>


</div>




<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
  <script>
  const createdDates = <?= json_encode(array_values(array_unique($createdDates))) ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');
  const tableRows = document.querySelectorAll('#mrf-table tbody tr');

  let currentMonth = new Date().getMonth();
  let currentYear = new Date().getFullYear();

  function generateCalendar(year, month) {
    const date = new Date(year, month);
    const monthName = date.toLocaleString('default', { month: 'long' });
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const startDay = new Date(year, month, 1).getDay();
    const today = new Date();
    const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;
    const todayDate = today.getDate();

    let calendarHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <button id="prevMonth" class="btn btn-sm btn-outline-primary">&lt;</button>
        <h5 style="margin: 0;">${monthName} ${year}</h5>
        <button id="nextMonth" class="btn btn-sm btn-outline-primary">&gt;</button>
      </div>
      <table class="table table-bordered text-center">
        <thead><tr>
          <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
          <th>Thu</th><th>Fri</th><th>Sat</th>
        </tr></thead><tbody><tr>`;

    for (let i = 0; i < startDay; i++) {
      calendarHTML += '<td></td>';
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const isToday = isCurrentMonth && day === todayDate;
      const isCreated = createdDates.includes(fullDate);

      let dayHTML = `${day}`;

      if (isToday && isCreated) {
        dayHTML = `<div class="today-highlight created-highlight" data-date="${fullDate}">${day}</div>`;
      } else if (isToday) {
        dayHTML = `<div class="today-highlight">${day}</div>`;
      } else if (isCreated) {
        dayHTML = `<div class="created-highlight" data-date="${fullDate}">${day}</div>`;
      }

      calendarHTML += `<td>${dayHTML}</td>`;

      if ((startDay + day) % 7 === 0) {
        calendarHTML += '</tr><tr>';
      }
    }

    const remainingCells = (startDay + daysInMonth) % 7;
    if (remainingCells !== 0) {
      for (let i = remainingCells; i < 7; i++) {
        calendarHTML += '<td></td>';
      }
    }

    calendarHTML += '</tr></tbody></table>';
    return calendarHTML;
  }

  function filterTableByDate(date) {
  const tableBody = document.querySelector('#mrf-table tbody');
  const rows = tableBody.querySelectorAll('tr');

  let currentProjectVisible = false;

  rows.forEach(row => {
    if (row.hasAttribute('data-date')) {
      // It's a normal item row
      if (row.dataset.date === date) {
        row.style.display = '';
        currentProjectVisible = true;
      } else {
        row.style.display = 'none';
      }
    } else {
      // It's a project header row
      currentProjectVisible = false; // reset for this project
      row.style.display = 'none';

      // Check if any of the following rows for this project match the filter
      let next = row.nextElementSibling;
      while (next && !next.hasAttribute('data-date') && next.children.length) {
        next = next.nextElementSibling;
      }
      let hasVisible = false;
      while (next && next.dataset && next.dataset.date) {
        if (next.dataset.date === date) {
          hasVisible = true;
          break;
        }
        next = next.nextElementSibling;
      }

      if (hasVisible) row.style.display = '';
    }
  });
}


  function renderCalendar(year, month) {
    const calendarHTML = generateCalendar(year, month);
    calendarEl.innerHTML = calendarHTML;

    // Attach click listeners to arrows
    document.getElementById('prevMonth').addEventListener('click', () => {
      if (month === 0) {
        currentMonth = 11;
        currentYear -= 1;
      } else {
        currentMonth -= 1;
      }
      renderCalendar(currentYear, currentMonth);
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
      if (month === 11) {
        currentMonth = 0;
        currentYear += 1;
      } else {
        currentMonth += 1;
      }
      renderCalendar(currentYear, currentMonth);
    });

    // Handle clicks on created-highlight days
    // Handle clicks on created-highlight days
calendarEl.querySelectorAll('.created-highlight').forEach(el => {
  el.addEventListener('click', () => {
    const selectedDate = el.getAttribute('data-date');
    filterTableByDate(selectedDate);

    // Update the <h3> title with the selected date
    const formatted = new Date(selectedDate).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    document.getElementById('mrf-title').textContent = `Submitted MRFs - ${formatted}`;
  });
});

  }

  // Initial render
  renderCalendar(currentYear, currentMonth);
  document.getElementById('reset-filter').addEventListener('click', () => {
  // Show all rows
  tableRows.forEach(row => row.style.display = '');
  // Reset header
  document.getElementById('mrf-title').textContent = 'Submitted MRFs';
});
});


</script>




</body>
</html>

<?php $conn->close(); ?>
