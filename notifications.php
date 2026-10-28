<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$action = isset($_GET["action"]) ? $_GET["action"] : "";

// ---------- remove one notification ----------

if ($action == "remove") {

    $notif_id = intval($_GET["id"]);

    $stmt = $conn->prepare("DELETE FROM notifications WHERE notif_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit();
}

// ---------- clear all my notifications ----------

if ($action == "clear_notif") {

    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit();
}

// ---------- clear my activity log ----------

if ($action == "clear_activity") {

    $stmt = $conn->prepare("DELETE FROM activities WHERE user_id = ?");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit();
}

$page_title = "Notifications";
include "header.php";
?>

    <div class="page">

        <h1>Notifications and Activity</h1>
        <p class="page-info">Everything that happened in your account.</p>

        <div class="row">

            <div class="col-half">

                <div class="box">

                    <h2 class="box-title">My Notifications</h2>

                    <div class="buttons">
                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to remove all your notifications?')) { window.location.href='notifications.php?action=clear_notif'; }">Clear All Notifications</button>
                    </div>

                    <div style="margin-top:15px">

                        <?php
                        $stmt = $conn->prepare("SELECT * FROM notifications
                                                WHERE user_id = ?
                                                ORDER BY notif_id DESC");
                        $stmt->bind_param("i", $my_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows == 0) {
                            echo "<div class='empty'>You have no notification.</div>";
                        }

                        while ($notif = $result->fetch_assoc()) {
                            ?>

                            <div class="item">
                                <p class="item-text">
                                    <a href="<?php echo $notif["link"]; ?>"><?php echo htmlspecialchars($notif["text"]); ?></a>
                                </p>
                                <p class="gray-text"><?php echo showDateTime($notif["created_at"]); ?></p>
                                <button class="btn-small btn-gray" onclick="window.location.href='notifications.php?action=remove&id=<?php echo $notif["notif_id"]; ?>'">Remove</button>
                            </div>

                            <?php
                        }

                        $stmt->close();
                        ?>

                    </div>

                </div>

            </div>

            <div class="col-half">

                <div class="box">

                    <h2 class="box-title">My Account Activity</h2>

                    <div class="buttons">
                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to clear your activity log?')) { window.location.href='notifications.php?action=clear_activity'; }">Clear Activity Log</button>
                    </div>

                    <div style="margin-top:15px">

                        <?php
                        $stmt = $conn->prepare("SELECT * FROM activities
                                                WHERE user_id = ?
                                                ORDER BY activity_id DESC");
                        $stmt->bind_param("i", $my_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows == 0) {
                            echo "<div class='empty'>No activity is saved yet.</div>";
                        }

                        while ($activity = $result->fetch_assoc()) {
                            ?>

                            <div class="item item-green">
                                <p class="item-text"><?php echo htmlspecialchars($activity["text"]); ?></p>
                                <p class="gray-text"><?php echo showDateTime($activity["created_at"]); ?></p>
                            </div>

                            <?php
                        }

                        $stmt->close();
                        ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
