<?php
session_start();
session_unset();
session_destroy();

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: login.php");
exit;
