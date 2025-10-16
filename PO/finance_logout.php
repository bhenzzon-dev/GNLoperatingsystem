<?php
session_start();

// Only unset the admin session variables
unset($_SESSION["finance_loggedin"]);
unset($_SESSION["finance_id"]);
unset($_SESSION["finance_username"]);

// Redirect to login page
header("location: finance_login.php");
exit;
?>
    