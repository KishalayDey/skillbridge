<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();
$saved = "";

// the type and the id can come from another page, like report.php?type=user&id=9
$type      = isset($_GET["type"]) ? $_GET["type"] : "";
$target_id = isset($_GET["id"])   ? intval($_GET["id"]) : 0;
$cause     = "";
$details   = "";

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $type      = $_POST["type"];
    $target_id = intval($_POST["target_id"]);
    $cause     = $_POST["cause"];
    $details   = trim($_POST["details"]);

    // ---------- validation ----------

    $allowed_type = array("user", "job", "post", "review");

    if (!in_array($type, $allowed_type)) {
        $errors["type"] = "Please select what you are reporting.";
    }

    if ($target_id == 0) {
        $errors["target_id"] = "Please select which one you are reporting.";
    }

    if ($cause == "") {
        $errors["cause"] = "Please select a reason.";
    }

    if ($details == "") {
        $errors["details"] = "Please write what happened.";
    }
    else if (strlen($details) < 20) {
        $errors["details"] = "Write at least 20 characters.";
    }

    if (!isset($_POST["confirm"])) {
        $errors["confirm"] = "Please confirm that the report is true.";
    }

    // ---------- save ----------

    if (count($errors) == 0) {

        $reason = $cause . " : " . $details;
        $today = date("Y-m-d");

        $stmt = $conn->prepare("INSERT INTO reports (from_id, type, target_id, reason, status, created_at)
                                VALUES (?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("isiss", $my_id, $type, $target_id, $reason, $today);
        $stmt->execute();
        $stmt->close();

        addActivity($conn, $my_id, "Sent a report about a " . $type);

        $saved = "Your report is sent to the admin.";

        $type = "";
        $target_id = 0;
        $cause = "";
        $details = "";
    }
}

$page_title = "Report a Problem";
include "header.php";
?>

    <div class="medium-page">

        <h1>Report a Problem</h1>
        <p class="page-info">Tell the admin about a user, a job, an exchange post or a review that breaks the rules.</p>

        <?php if ($saved != "") { ?>
            <div class="result"><b><?php echo $saved; ?></b></div>
        <?php } ?>

        <div class="box">

            <form method="POST" action="report.php">

                <label for="type">What are you reporting?</label>

                <select id="type" name="type" onchange="showTargets()">
                    <option value="">-- Select one --</option>
                    <option value="user"   <?php if ($type == "user")   { echo "selected"; } ?>>A user</option>
                    <option value="job"    <?php if ($type == "job")    { echo "selected"; } ?>>A job</option>
                    <option value="post"   <?php if ($type == "post")   { echo "selected"; } ?>>A skill exchange post</option>
                    <option value="review" <?php if ($type == "review") { echo "selected"; } ?>>A review</option>
                </select>

                <small class="error"><?php if (isset($errors["type"])) { echo $errors["type"]; } ?></small>

                <label for="target_id">Which one?</label>

                <select id="target_id" name="target_id">
                    <option value="0">-- Select one --</option>

                    <?php
                    // the second dropdown is filled by PHP with the rows of the chosen table
                    if ($type == "user") {

                        $stmt = $conn->prepare("SELECT user_id, name, city FROM users WHERE user_id != ? ORDER BY name");
                        $stmt->bind_param("i", $my_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            $selected = ($target_id == $row["user_id"]) ? "selected" : "";
                            echo "<option value='" . $row["user_id"] . "' " . $selected . ">"
                                 . htmlspecialchars($row["name"]) . " ( " . htmlspecialchars($row["city"]) . " )</option>";
                        }

                        $stmt->close();
                    }
                    else if ($type == "job") {

                        $result = $conn->query("SELECT job_id, title FROM jobs ORDER BY job_id DESC");

                        while ($row = $result->fetch_assoc()) {
                            $selected = ($target_id == $row["job_id"]) ? "selected" : "";
                            echo "<option value='" . $row["job_id"] . "' " . $selected . ">"
                                 . htmlspecialchars($row["title"]) . "</option>";
                        }
                    }
                    else if ($type == "post") {

                        $result = $conn->query("SELECT p.post_id, u.name,
                                                       s1.skill_name AS offer_skill, s2.skill_name AS want_skill
                                                FROM posts p
                                                JOIN users u   ON p.user_id = u.user_id
                                                JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                                JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                                ORDER BY p.post_id DESC");

                        while ($row = $result->fetch_assoc()) {
                            $selected = ($target_id == $row["post_id"]) ? "selected" : "";
                            echo "<option value='" . $row["post_id"] . "' " . $selected . ">"
                                 . htmlspecialchars($row["offer_skill"]) . " for " . htmlspecialchars($row["want_skill"])
                                 . " ( " . htmlspecialchars($row["name"]) . " )</option>";
                        }
                    }
                    else if ($type == "review") {

                        $result = $conn->query("SELECT r.review_id, r.rating, u1.name AS writer, u2.name AS person
                                                FROM reviews r
                                                JOIN users u1 ON r.from_id = u1.user_id
                                                JOIN users u2 ON r.to_id = u2.user_id
                                                ORDER BY r.review_id DESC");

                        while ($row = $result->fetch_assoc()) {
                            $selected = ($target_id == $row["review_id"]) ? "selected" : "";
                            echo "<option value='" . $row["review_id"] . "' " . $selected . ">"
                                 . $row["rating"] . " star by " . htmlspecialchars($row["writer"])
                                 . " about " . htmlspecialchars($row["person"]) . "</option>";
                        }
                    }
                    else {
                        echo "<option value='0'>-- Select the type first --</option>";
                    }
                    ?>

                </select>

                <small class="error"><?php if (isset($errors["target_id"])) { echo $errors["target_id"]; } ?></small>

                <label for="cause">Reason</label>

                <select id="cause" name="cause">
                    <option value="">-- Select a reason --</option>
                    <?php
                    $cause_list = array("Did not deliver the work", "Payment problem", "Bad behaviour",
                                        "Fake profile or fake information", "Spam", "Other");

                    foreach ($cause_list as $one) {
                        $selected = ($cause == $one) ? "selected" : "";
                        echo "<option value='" . $one . "' " . $selected . ">" . $one . "</option>";
                    }
                    ?>
                </select>

                <small class="error"><?php if (isset($errors["cause"])) { echo $errors["cause"]; } ?></small>

                <label for="details">What happened?</label>
                <textarea id="details" name="details"
                          placeholder="Write the full problem with dates if possible."><?php echo htmlspecialchars($details); ?></textarea>
                <small class="error"><?php if (isset($errors["details"])) { echo $errors["details"]; } ?></small>

                <label>
                    <input type="checkbox" name="confirm" style="width:auto">
                    I confirm that this report is true.
                </label>

                <small class="error"><?php if (isset($errors["confirm"])) { echo $errors["confirm"]; } ?></small>

                <div class="buttons">
                    <button type="submit" class="btn-red">Send Report</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='dashboard.php'">Cancel</button>
                </div>

            </form>

        </div>

        <div class="box">

            <h2 class="box-title">My Old Reports</h2>

            <?php
            $stmt = $conn->prepare("SELECT * FROM reports WHERE from_id = ? ORDER BY report_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<div class='empty'>You did not report anything. That is good.</div>";
            }

            while ($report = $result->fetch_assoc()) {
                ?>

                <div class="item">
                    <h3><?php echo strtoupper($report["type"]); ?> ( id : <?php echo $report["target_id"]; ?> )</h3>
                    <p class="item-text"><?php echo htmlspecialchars($report["reason"]); ?></p>
                    <p class="gray-text">Sent on <?php echo showDate($report["created_at"]); ?>
                        &nbsp; <?php echo showStatus($report["status"]); ?></p>
                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

    </div>

    <script>
        // when the first dropdown changes, the page reloads with the chosen type
        // so PHP can fill the second dropdown from the right table
        function showTargets() {
            let type = document.getElementById("type").value;
            window.location.href = "report.php?type=" + type;
        }
    </script>

<?php include "footer.php"; ?>
