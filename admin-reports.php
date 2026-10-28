<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_admin.php";

$admin_id = $_SESSION["admin_id"];

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

// ---------- find one report ----------

function getReport($conn, $report_id) {

    $stmt = $conn->prepare("SELECT * FROM reports WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row;
}

// ---------- mark a report as solved ----------

if ($action == "solve" && $id > 0) {

    $report = getReport($conn, $id);

    if ($report != null) {

        $stmt = $conn->prepare("UPDATE reports SET status = 'solved' WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $report["from_id"],
            "The admin checked your report and solved it.", "report.php");
    }

    header("Location: admin-reports.php");
    exit();
}

// ---------- suspend the reported user ----------

if ($action == "suspend" && $id > 0) {

    $report = getReport($conn, $id);

    if ($report != null && $report["type"] == "user") {

        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
        $stmt->bind_param("i", $report["target_id"]);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE reports SET status = 'solved' WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $report["target_id"], "Your account is suspended after a report.", "index.php");
        addNotification($conn, $report["from_id"], "The admin took action on the user you reported.", "report.php");
    }

    header("Location: admin-reports.php");
    exit();
}

// ---------- hide the reported review ----------

if ($action == "hide_reported" && $id > 0) {

    $report = getReport($conn, $id);

    if ($report != null && $report["type"] == "review") {

        $stmt = $conn->prepare("UPDATE reviews SET status = 'hidden' WHERE review_id = ?");
        $stmt->bind_param("i", $report["target_id"]);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE reports SET status = 'solved' WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin-reports.php");
    exit();
}

// ---------- delete a report ----------

if ($action == "delete_report" && $id > 0) {

    $stmt = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-reports.php");
    exit();
}

// ---------- hide, show or delete a review ----------

if ($action == "hide" && $id > 0) {

    $stmt = $conn->prepare("UPDATE reviews SET status = 'hidden' WHERE review_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-reports.php");
    exit();
}

if ($action == "show" && $id > 0) {

    $stmt = $conn->prepare("UPDATE reviews SET status = 'visible' WHERE review_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-reports.php");
    exit();
}

if ($action == "delete_review" && $id > 0) {

    $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-reports.php");
    exit();
}

// ---------- the filters ----------

$report_filter = isset($_GET["report_filter"]) ? $_GET["report_filter"] : "pending";
$review_filter = isset($_GET["review_filter"]) ? $_GET["review_filter"] : "";
$review_search = isset($_GET["review_search"]) ? trim($_GET["review_search"]) : "";

$sql = "SELECT r.*, u.name AS reporter FROM reports r
        JOIN users u ON r.from_id = u.user_id";

if ($report_filter == "pending" || $report_filter == "solved") {
    $sql = $sql . " WHERE r.status = '" . $report_filter . "'";
}

$sql = $sql . " ORDER BY r.report_id DESC";

$report_result = $conn->query($sql);

$page_title = "Reports and Reviews";
include "header.php";
?>

    <div class="page">

        <h1>Reports and Reviews</h1>
        <p class="page-info">Check the complaints of the students and moderate the bad reviews.</p>

        <!-- ---------- Reports ---------- -->

        <div class="box">

            <h2 class="box-title">Reports</h2>

            <form method="GET" action="admin-reports.php">

                <label for="report_filter">Show</label>

                <select id="report_filter" name="report_filter" style="width:250px" onchange="this.form.submit()">
                    <option value="pending" <?php if ($report_filter == "pending") { echo "selected"; } ?>>Pending reports</option>
                    <option value="solved"  <?php if ($report_filter == "solved")  { echo "selected"; } ?>>Solved reports</option>
                    <option value="all"     <?php if ($report_filter == "all")     { echo "selected"; } ?>>All reports</option>
                </select>

            </form>

            <div style="margin-top:20px">

                <?php
                if ($report_result->num_rows == 0) {
                    echo "<div class='empty'>No report in this list.</div>";
                }

                while ($report = $report_result->fetch_assoc()) {
                    ?>

                    <div class="item">

                        <h3>Report <?php echo $report["report_id"]; ?> - <?php echo strtoupper($report["type"]); ?></h3>

                        <p class="item-info">Sent by <?php echo htmlspecialchars($report["reporter"]); ?>
                            on <?php echo showDate($report["created_at"]); ?>
                            &nbsp; <?php echo showStatus($report["status"]); ?></p>

                        <p class="item-text"><b>Reason :</b> <?php echo htmlspecialchars($report["reason"]); ?></p>

                        <div class="result">
                            <?php
                            // show what exactly was reported
                            if ($report["type"] == "user") {

                                $user = getUser($conn, $report["target_id"]);

                                if ($user == null) {
                                    echo "<p class='gray-text'>This user is already deleted.</p>";
                                }
                                else {
                                    echo "<p><b>" . htmlspecialchars($user["name"]) . "</b> ( "
                                         . htmlspecialchars($user["email"]) . " ) " . showStatus($user["status"]) . "</p>";
                                }
                            }
                            else if ($report["type"] == "job") {

                                $job = getJob($conn, $report["target_id"]);

                                if ($job == null) {
                                    echo "<p class='gray-text'>This job is already deleted.</p>";
                                }
                                else {
                                    echo "<p><b>" . htmlspecialchars($job["title"]) . "</b><br>";
                                    echo "<span class='gray-text'>Posted by " . htmlspecialchars(getUserName($conn, $job["client_id"]))
                                         . " , budget " . showMoney($job["budget"]) . "</span></p>";
                                }
                            }
                            else if ($report["type"] == "post") {

                                $post = getPost($conn, $report["target_id"]);

                                if ($post == null) {
                                    echo "<p class='gray-text'>This post is already deleted.</p>";
                                }
                                else {
                                    echo "<p><b>" . htmlspecialchars($post["offer_skill"]) . " for "
                                         . htmlspecialchars($post["want_skill"]) . "</b><br>";
                                    echo "<span class='gray-text'>" . htmlspecialchars($post["description"]) . "</span></p>";
                                }
                            }
                            else {

                                $stmt = $conn->prepare("SELECT r.*, u1.name AS writer, u2.name AS person
                                                        FROM reviews r
                                                        JOIN users u1 ON r.from_id = u1.user_id
                                                        JOIN users u2 ON r.to_id = u2.user_id
                                                        WHERE r.review_id = ?");
                                $stmt->bind_param("i", $report["target_id"]);
                                $stmt->execute();
                                $review = $stmt->get_result()->fetch_assoc();
                                $stmt->close();

                                if ($review == null) {
                                    echo "<p class='gray-text'>This review is already deleted.</p>";
                                }
                                else {
                                    echo "<p>" . showStars($review["rating"]) . " <b>"
                                         . htmlspecialchars($review["comment"]) . "</b><br>";
                                    echo "<span class='gray-text'>By " . htmlspecialchars($review["writer"])
                                         . " about " . htmlspecialchars($review["person"]) . "</span></p>";
                                }
                            }
                            ?>
                        </div>

                        <div class="buttons">

                            <?php if ($report["status"] == "pending") { ?>

                                <button class="btn-small btn-green" onclick="window.location.href='admin-reports.php?action=solve&id=<?php echo $report["report_id"]; ?>'">Mark as Solved</button>

                                <?php if ($report["type"] == "user") { ?>
                                    <button class="btn-small btn-red" onclick="if (confirm('Do you want to suspend this user?')) { window.location.href='admin-reports.php?action=suspend&id=<?php echo $report["report_id"]; ?>'; }">Suspend This User</button>
                                <?php } ?>

                                <?php if ($report["type"] == "review") { ?>
                                    <button class="btn-small btn-red" onclick="window.location.href='admin-reports.php?action=hide_reported&id=<?php echo $report["report_id"]; ?>'">Hide This Review</button>
                                <?php } ?>

                            <?php } ?>

                            <button class="btn-small btn-gray" onclick="if (confirm('Do you want to delete this report?')) { window.location.href='admin-reports.php?action=delete_report&id=<?php echo $report["report_id"]; ?>'; }">Delete Report</button>

                        </div>

                    </div>

                    <?php
                }
                ?>

            </div>

        </div>

        <!-- ---------- Reviews ---------- -->

        <div class="box">

            <h2 class="box-title">All Reviews</h2>

            <form method="GET" action="admin-reports.php">

                <div class="row">

                    <div class="col-half">
                        <label for="review_search">Search review</label>
                        <input type="search" id="review_search" name="review_search" placeholder="Write a word or a student name"
                               value="<?php echo htmlspecialchars($review_search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="review_filter">Show</label>
                        <select id="review_filter" name="review_filter">
                            <option value=""        <?php if ($review_filter == "")        { echo "selected"; } ?>>All reviews</option>
                            <option value="visible" <?php if ($review_filter == "visible") { echo "selected"; } ?>>Visible only</option>
                            <option value="hidden"  <?php if ($review_filter == "hidden")  { echo "selected"; } ?>>Hidden only</option>
                            <option value="low"     <?php if ($review_filter == "low")     { echo "selected"; } ?>>1 and 2 star only</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='admin-reports.php'">Clear</button>
                </div>

            </form>

            <div style="margin-top:20px">

                <?php
                $sql = "SELECT r.*, u1.name AS writer, u2.name AS person
                        FROM reviews r
                        JOIN users u1 ON r.from_id = u1.user_id
                        JOIN users u2 ON r.to_id = u2.user_id
                        WHERE 1 = 1";

                if ($review_filter == "visible" || $review_filter == "hidden") {
                    $sql = $sql . " AND r.status = '" . $review_filter . "'";
                }

                if ($review_filter == "low") {
                    $sql = $sql . " AND r.rating <= 2";
                }

                if ($review_search != "") {
                    $sql = $sql . " AND (r.comment LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)";
                }

                $sql = $sql . " ORDER BY r.review_id DESC";

                $review_stmt = $conn->prepare($sql);

                if ($review_search != "") {
                    $like = "%" . $review_search . "%";
                    $review_stmt->bind_param("sss", $like, $like, $like);
                }

                $review_stmt->execute();
                $review_result = $review_stmt->get_result();

                if ($review_result->num_rows == 0) {
                    echo "<div class='empty'>No review found for this search.</div>";
                }
                else {
                    ?>

                    <table>

                        <tr>
                            <th>From</th><th>About</th><th>Rating</th><th>Comment</th>
                            <th>Date</th><th>Status</th><th>Action</th>
                        </tr>

                        <?php while ($review = $review_result->fetch_assoc()) { ?>

                            <tr>
                                <td><?php echo htmlspecialchars($review["writer"]); ?></td>
                                <td><?php echo htmlspecialchars($review["person"]); ?></td>
                                <td><?php echo showStars($review["rating"]); ?></td>
                                <td><?php echo htmlspecialchars($review["comment"]); ?></td>
                                <td><?php echo showDate($review["review_date"]); ?></td>
                                <td><?php echo showStatus($review["status"]); ?></td>
                                <td>
                                    <?php if ($review["status"] == "visible") { ?>
                                        <button class="btn-small btn-red" onclick="if (confirm('Hide this review from the public profile?')) { window.location.href='admin-reports.php?action=hide&id=<?php echo $review["review_id"]; ?>'; }">Hide</button>
                                    <?php } else { ?>
                                        <button class="btn-small btn-green" onclick="window.location.href='admin-reports.php?action=show&id=<?php echo $review["review_id"]; ?>'">Show</button>
                                    <?php } ?>

                                    <button class="btn-small btn-gray" onclick="if (confirm('Delete this review permanently?')) { window.location.href='admin-reports.php?action=delete_review&id=<?php echo $review["review_id"]; ?>'; }">Delete</button>
                                </td>
                            </tr>

                        <?php } ?>

                    </table>

                    <?php
                }

                $review_stmt->close();
                ?>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
