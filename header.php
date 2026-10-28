<?php
// ============================================
// The top part of every page (include file).
// The menu is different for a visitor, a student and the admin,
// and PHP decides it from the SESSION.
// ============================================

if (!isset($page_title)) {
    $page_title = "KaajKoro";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - KaajKoro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- Header -->

    <div class="header">
        <div class="container">

            <h2 class="logo">Kaaj<span>Koro</span><?php if (isset($_SESSION["admin_logged_in"])) { echo " Admin"; } ?></h2>

            <div class="menu">

            <?php if (isset($_SESSION["user_logged_in"])) { ?>

                <a href="dashboard.php">Dashboard</a>
                <a href="jobs.php">Find Work</a>
                <a href="exchange.php">Skill Exchange</a>
                <a href="students.php">Students</a>
                <a href="my-jobs.php">My Jobs</a>
                <a href="messages.php">Messages</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>

            <?php } else if (isset($_SESSION["admin_logged_in"])) { ?>

                <a href="admin-dashboard.php">Overview</a>
                <a href="admin-users.php">Users</a>
                <a href="admin-jobs.php">Jobs and Posts</a>
                <a href="admin-reports.php">Reports and Reviews</a>
                <a href="logout.php">Logout</a>

            <?php } else { ?>

                <a href="index.php">Home</a>
                <a href="jobs.php">Find Work</a>
                <a href="exchange.php">Skill Exchange</a>
                <a href="students.php">Students</a>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>

            <?php } ?>

            </div>

        </div>
    </div>
