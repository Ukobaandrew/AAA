<?php
session_start();

// Destroy the session to log out the user
session_unset();
session_destroy();

// Redirect to the login page or homepage
header("Location: login.php");
exit();
