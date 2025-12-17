<?php
session_start();

session_unset();
session_destroy();


header("Location: ../HTML/login.php");
exit;
?>
