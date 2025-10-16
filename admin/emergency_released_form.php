<?php
session_start();
include 'db_connect.php';
//fetch project naem from project table
//for project field
$projectsQuery = "SELECT id, project_name from projects ORDER BY project_name ASC";
$projectResult = $conn->query($projectsQuery);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#000000">
    <title>Document</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>
    body{
        font-family: sans-serif;
        background-image: url(/gnlproject/img/bg.jpg);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        margin: 0;
        padding: 0;
    }


    .nav-bar{
        background-color: rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .nav-links {
    display: flex;
    gap: 50px;
    margin-right: 20px;
    list-style: none; /* remove bullets */
    margin-left: 190px;
    }

    .nav-links a {
        color: white;
        text-decoration: none;
        padding: 8px 12px; /* clickable area */
        border-radius: 4px;
        transition: background-color 0.2s, color 0.2s;
    }

    .nav-links a {
    position: relative;
    text-decoration: none;
}

.nav-links a::after {
    content: "";
    position: absolute;
    width: 0;
    height: 2px;
    left: 0;
    bottom: -3px;
    background-color: white;
    transition: width 0.2s ease-in-out;
}

.nav-links a:hover::after {
    width: 100%;
}

    .nav-links a:active {
        background-color: rgba(255, 255, 255, 0.4); /* pressed state */
    }


    .nav-items {
        display: flex;
        margin: 0;
        padding: 0;
        cursor: pointer;
        color: aliceblue;
    }

    .form-container{
        display: flex;
        padding: 20px;
        margin: auto;
        width: 430px;
        margin-top: 10px;
        justify-content: center;

        background-color: white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .release-form h2{
        margin-bottom: 50px;
        text-align: center;
        color:rgb(22, 155, 231);
    }
    
    label {
        font-weight: bold;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: block;
        color: rgb(22, 155, 231);
        text-align: center;
    }

     
     .form-input{
        display: inline-block;
        margin-bottom: 20px;
        
     }

     .inputs {
        display: flex;
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid #ccc;
        font-size: 0.9;
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        text-align:center;
        width: 100%;
     }

     .submit-btn {
        padding: 10px;
        background-color: rgb(22, 155, 231);
        border: none;
        border-radius: 6px;
        color: white;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s;
        width: 106%;
        text-align: center;
        margin-bottom: 20px;
        }

        .submit-btn:hover {
            transition: ease-in-out 0.2s;
            transform: scale(1.1);
        }

        .input-name {
            margin-bottom: 20px;
        }

        .drop-down {
            width: 150%;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border-color: 1px solid #ccc;
        }
        .swal-title {
         font-size: 16px; /* smaller title */
        }

        .swal-popup {
            padding: 5px; /* reduce padding */
        }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px; /* space between columns */
        }

        .two-columns select,
        .two-columns input {
            width: 100%;
        }

</style>
<body>
    <div>
    <nav class="nav-bar">
    <ul class="nav-links">
        <li class="nav-items"><a href="feedback.php">Feedback</a></li>
        <li class="nav-items"><a href="Release_form.php">Emergency Release</a></li>
        <li class="nav-items"><a href="Release_form.php">Summary Request</a></li>
        <li class="nav-items"><a href="Release_form.php">Summary Approved</a></li>
        <li class="nav-items"><a href="Release_form.php">Summary Released</a></li>
    </ul>
    </div>
    </nav>

    <div class="form-container">
        <form class="release-form" action="submit_released.php" method="POST">
            <h2>Emergency Released Form</h2>
            <div class="form-input two-columns">
    <div>
        <label class="input-name" for="project_id">Project</label>
        <select id="project_id" name="project_id" class="drop-down" required>
            <option value="" disabled selected>Select Project</option>
            <?php while($row = $projectResult->fetch_assoc()): ?>
            <option value ="<?= $row['id']?>"><?= htmlspecialchars($row['project_name'])?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div>
        <label class="input-name" for="release_date"> Date</label>
        <input class="inputs" type="date" name="release_date" id="release_date">
    </div>
</div>

<label class="input-name" for="group_number">Batch Number</label>
<input class="inputs" type="text" name="group_number" placeholder="Enter Group Number">

<label class="input-name" for="particulars">Particulars</label>
<input class="inputs" type="text" name="particulars" placeholder="Enter Particulars">

<label class="input-name" for="Amount">Amount</label>
<input class="inputs" type="number" name="amount" placeholder="Enter total amount" step="0.01">
            
            <button class="submit-btn" type="submit">submit</button>
        </form>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Swal.fire({
            icon: 'success',
            title: 'Data Saved!',
            text: 'The emergency release record has been added successfully.',
            width: '300px', // smaller width
            confirmButtonColor: '#169be7',
            customClass: {
                title: 'swal-title',
                popup: 'swal-popup'
            }
        });

    }
    if (urlParams.get('error') === '1') {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong. Please try again.',
            confirmButtonColor: '#d33'
        });
    }
});
</script>
</body>
</html>