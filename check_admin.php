<?php
// ============================================
// Included at the top of every admin page.
// ============================================

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: login.php");
    exit();
}
?>
