<?php
// ============================================
// This file is included at the top of every page that needs login.
// It is the PHP version of checkLogin() from the first part.
// ============================================

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

// $me keeps all the information of the student who is logged in
$me = getUser($conn, $_SESSION["user_id"]);

// the admin may have deleted this account while the user was logged in
if ($me == null) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
