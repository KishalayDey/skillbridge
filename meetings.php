<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();

// ---------- the buttons of the meeting list ----------

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

if ($action == "done" && $id > 0) {

    // the WHERE checks that this meeting belongs to me
    $stmt = $conn->prepare("UPDATE meetings SET status = 'done'
                            WHERE meeting_id = ? AND (from_id = ? OR to_id = ?)");
    $stmt->bind_param("iii", $id, $my_id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: meetings.php");
    exit();
}

if ($action == "cancel" && $id > 0) {

    $stmt = $conn->prepare("SELECT * FROM meetings WHERE meeting_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $meeting = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($meeting != null && ($meeting["from_id"] == $my_id || $meeting["to_id"] == $my_id)) {

        $stmt = $conn->prepare("UPDATE meetings SET status = 'cancelled' WHERE meeting_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        if ($meeting["from_id"] == $my_id) {
            $other_id = $meeting["to_id"];
        }
        else {
            $other_id = $meeting["from_id"];
        }

        addNotification($conn, $other_id,
            getUserName($conn, $my_id) . " cancelled the meeting of " . showDateTime($meeting["date_time"]) . ".",
            "meetings.php");
    }

    header("Location: meetings.php");
    exit();
}

// ---------- if we came from the message page, this person is selected ----------

$to_id = isset($_GET["to"]) ? intval($_GET["to"]) : 0;

$date = "";
$time = "";
$mode = "online";
$place = "";
$topic = "";

// ============================================
// The meeting form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $to_id = intval($_POST["person"]);
    $date  = $_POST["date"];
    $time  = $_POST["time"];
    $mode  = $_POST["mode"];
    $place = trim($_POST["place"]);
    $topic = trim($_POST["topic"]);

    // ---------- validation ----------

    if ($to_id == 0) {
        $errors["person"] = "Please select a person.";
    }

    if ($date == "") {
        $errors["date"] = "Date is required.";
    }
    else if ($date < date("Y-m-d")) {
        $errors["date"] = "The meeting date cannot be a past date.";
    }

    if ($time == "") {
        $errors["time"] = "Time is required.";
    }

    if ($place == "") {
        $errors["place"] = "Write the meeting link or the place.";
    }

    if ($topic == "") {
        $errors["topic"] = "Write the topic of the meeting.";
    }

    // ---------- save ----------

    if (count($errors) == 0) {

        $date_time = $date . " " . $time . ":00";

        $stmt = $conn->prepare("INSERT INTO meetings (from_id, to_id, date_time, mode, place, topic, status)
                                VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
        $stmt->bind_param("iissss", $my_id, $to_id, $date_time, $mode, $place, $topic);
        $stmt->execute();
        $stmt->close();

        // the invitation is also written in the chat
        addMessage($conn, $my_id, $to_id,
            "Meeting invitation : " . showDateTime($date_time) . " ( " . $mode . " ) at " . $place);

        addNotification($conn, $to_id,
            getUserName($conn, $my_id) . " invited you to a meeting on " . showDateTime($date_time) . ".",
            "meetings.php");

        addActivity($conn, $my_id, "Arranged a meeting with " . getUserName($conn, $to_id));

        header("Location: meetings.php");
        exit();
    }
}

$page_title = "Meetings";
include "header.php";
?>

    <div class="page">

        <h1>Meetings</h1>
        <p class="page-info">Fix a time with a client or with your skill exchange partner.</p>

        <div class="row">

            <!-- Left : the form -->

            <div class="col-right">

                <div class="box">

                    <h2 class="box-title">Arrange a Meeting</h2>

                    <form method="POST" action="meetings.php">

                        <label for="person">With</label>

                        <select id="person" name="person">
                            <option value="0">-- Select a person --</option>
                            <?php
                            $all_users = $conn->query("SELECT user_id, name FROM users WHERE status = 'active' ORDER BY name");

                            while ($one = $all_users->fetch_assoc()) {

                                if ($one["user_id"] != $my_id) {

                                    if ($to_id == $one["user_id"]) {
                                        echo "<option value='" . $one["user_id"] . "' selected>" . htmlspecialchars($one["name"]) . "</option>";
                                    }
                                    else {
                                        echo "<option value='" . $one["user_id"] . "'>" . htmlspecialchars($one["name"]) . "</option>";
                                    }
                                }
                            }
                            ?>
                        </select>

                        <small class="error"><?php if (isset($errors["person"])) { echo $errors["person"]; } ?></small>

                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        <small class="error"><?php if (isset($errors["date"])) { echo $errors["date"]; } ?></small>

                        <label for="time">Time</label>
                        <input type="time" id="time" name="time" value="<?php echo htmlspecialchars($time); ?>">
                        <small class="error"><?php if (isset($errors["time"])) { echo $errors["time"]; } ?></small>

                        <label for="mode">Mode</label>

                        <select id="mode" name="mode">
                            <option value="online"  <?php if ($mode == "online")  { echo "selected"; } ?>>Online</option>
                            <option value="offline" <?php if ($mode == "offline") { echo "selected"; } ?>>Face to face</option>
                        </select>

                        <label for="place">Meeting Link or Place</label>
                        <input type="text" id="place" name="place" placeholder="Zoom or Meet link, or a campus place"
                               value="<?php echo htmlspecialchars($place); ?>">
                        <small class="error"><?php if (isset($errors["place"])) { echo $errors["place"]; } ?></small>

                        <label for="topic">Topic</label>
                        <textarea id="topic" name="topic" placeholder="What will you discuss in this meeting?"
                                  style="height:70px"><?php echo htmlspecialchars($topic); ?></textarea>
                        <small class="error"><?php if (isset($errors["topic"])) { echo $errors["topic"]; } ?></small>

                        <div class="buttons">
                            <button type="submit">Send Invitation</button>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Right : the meeting lists -->

            <div class="col-left">

                <div class="box">

                    <h2 class="box-title">Next Meetings</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT * FROM meetings
                                            WHERE (from_id = ? OR to_id = ?) AND status = 'scheduled'
                                            ORDER BY date_time");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>You have no meeting now. Use the form to arrange one.</div>";
                    }

                    while ($meeting = $result->fetch_assoc()) {

                        if ($meeting["from_id"] == $my_id) {
                            $other_id = $meeting["to_id"];
                        }
                        else {
                            $other_id = $meeting["from_id"];
                        }
                        ?>

                        <div class="item">

                            <h3><?php echo htmlspecialchars(getUserName($conn, $other_id)); ?></h3>

                            <p class="item-info"><?php echo showDateTime($meeting["date_time"]); ?>
                                &nbsp; | &nbsp; <?php echo $meeting["mode"]; ?>
                                &nbsp; | &nbsp; <?php echo showStatus($meeting["status"]); ?></p>

                            <p class="item-text"><b>Place :</b> <?php echo htmlspecialchars($meeting["place"]); ?></p>
                            <p class="item-text"><b>Topic :</b> <?php echo htmlspecialchars($meeting["topic"]); ?></p>

                            <div class="buttons">
                                <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $other_id; ?>'">Message</button>

                                <?php if ($meeting["mode"] == "online") { ?>
                                    <button class="btn-small" onclick="window.open('<?php echo htmlspecialchars($meeting["place"]); ?>')">Join Meeting</button>
                                <?php } ?>

                                <button class="btn-small btn-green" onclick="window.location.href='meetings.php?action=done&id=<?php echo $meeting["meeting_id"]; ?>'">Meeting is Done</button>
                                <button class="btn-small btn-red" onclick="if (confirm('Do you want to cancel this meeting?')) { window.location.href='meetings.php?action=cancel&id=<?php echo $meeting["meeting_id"]; ?>'; }">Cancel</button>
                            </div>

                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                </div>

                <div class="box">

                    <h2 class="box-title">Old Meetings</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT * FROM meetings
                                            WHERE (from_id = ? OR to_id = ?) AND status != 'scheduled'
                                            ORDER BY date_time DESC");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>No old meeting.</div>";
                    }

                    while ($meeting = $result->fetch_assoc()) {

                        if ($meeting["from_id"] == $my_id) {
                            $other_id = $meeting["to_id"];
                        }
                        else {
                            $other_id = $meeting["from_id"];
                        }
                        ?>

                        <div class="item">
                            <h3><?php echo htmlspecialchars(getUserName($conn, $other_id)); ?></h3>
                            <p class="item-info"><?php echo showDateTime($meeting["date_time"]); ?>
                                &nbsp; | &nbsp; <?php echo $meeting["mode"]; ?>
                                &nbsp; | &nbsp; <?php echo showStatus($meeting["status"]); ?></p>
                            <p class="item-text"><b>Topic :</b> <?php echo htmlspecialchars($meeting["topic"]); ?></p>
                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                </div>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
