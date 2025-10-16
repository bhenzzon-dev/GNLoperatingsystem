<?php
session_start();

// Only unset the admin session variables
unset($_SESSION["admin_loggedin"]);
unset($_SESSION["admin_id"]);
unset($_SESSION["admin_username"]);

// Redirect to login page
header("location: admin_login.php");
exit;
?>
