<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_admin.php";

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

// ---------- remove a job ----------

if ($action == "remove_job" && $id > 0) {

    $job = getJob($conn, $id);

    if ($job != null) {

        addNotification($conn, $job["client_id"],
            "The admin removed your job : " . $job["title"], "my-jobs.php");

        // the proposals of this job are deleted automatically (ON DELETE CASCADE)
        $stmt = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin-jobs.php");
    exit();
}

// ---------- remove an exchange post ----------

if ($action == "remove_post" && $id > 0) {

    $post = getPost($conn, $id);

    if ($post != null) {

        addNotification($conn, $post["user_id"],
            "The admin removed one of your skill exchange posts.", "post-exchange.php");

        $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin-jobs.php");
    exit();
}

// ---------- the two searches ----------

$job_search  = isset($_GET["job_search"])  ? trim($_GET["job_search"]) : "";
$job_status  = isset($_GET["job_status"])  ? $_GET["job_status"] : "";
$post_search = isset($_GET["post_search"]) ? trim($_GET["post_search"]) : "";
$post_status = isset($_GET["post_status"]) ? $_GET["post_status"] : "";

// ---------- the job list ----------

$sql = "SELECT j.*, u.name AS client_name
        FROM jobs j
        JOIN users u ON j.client_id = u.user_id
        WHERE 1 = 1";

$allowed_job_status = array("open", "working", "done");

if (in_array($job_status, $allowed_job_status)) {
    $sql = $sql . " AND j.job_status = '" . $job_status . "'";
}

if ($job_search != "") {
    $sql = $sql . " AND (j.title LIKE ? OR u.name LIKE ?)";
}

$sql = $sql . " ORDER BY j.job_id DESC";

$job_stmt = $conn->prepare($sql);

if ($job_search != "") {
    $like = "%" . $job_search . "%";
    $job_stmt->bind_param("ss", $like, $like);
}

$job_stmt->execute();
$job_result = $job_stmt->get_result();

// ---------- the exchange post list ----------

$sql = "SELECT p.*, u.name, s1.skill_name AS offer_skill, s2.skill_name AS want_skill
        FROM posts p
        JOIN users u   ON p.user_id = u.user_id
        JOIN skills s1 ON p.offer_skill_id = s1.skill_id
        JOIN skills s2 ON p.want_skill_id = s2.skill_id
        WHERE 1 = 1";

$allowed_post_status = array("open", "matched", "done");

if (in_array($post_status, $allowed_post_status)) {
    $sql = $sql . " AND p.post_status = '" . $post_status . "'";
}

if ($post_search != "") {
    $sql = $sql . " AND (p.description LIKE ? OR u.name LIKE ?)";
}

$sql = $sql . " ORDER BY p.post_id DESC";

$post_stmt = $conn->prepare($sql);

if ($post_search != "") {
    $like2 = "%" . $post_search . "%";
    $post_stmt->bind_param("ss", $like2, $like2);
}

$post_stmt->execute();
$post_result = $post_stmt->get_result();

$page_title = "Jobs and Posts";
include "header.php";
?>

    <div class="page">

        <h1>Jobs and Exchange Posts</h1>
        <p class="page-info">Check what the students are posting and remove the wrong ones.</p>

        <!-- ---------- Jobs ---------- -->

        <div class="box">

            <h2 class="box-title">All Jobs</h2>

            <form method="GET" action="admin-jobs.php">

                <div class="row">

                    <div class="col-half">
                        <label for="job_search">Search job</label>
                        <input type="search" id="job_search" name="job_search" placeholder="Write a job title or a client name"
                               value="<?php echo htmlspecialchars($job_search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="job_status">Status</label>
                        <select id="job_status" name="job_status">
                            <option value=""        <?php if ($job_status == "")        { echo "selected"; } ?>>All jobs</option>
                            <option value="open"    <?php if ($job_status == "open")    { echo "selected"; } ?>>Open</option>
                            <option value="working" <?php if ($job_status == "working") { echo "selected"; } ?>>Working</option>
                            <option value="done"    <?php if ($job_status == "done")    { echo "selected"; } ?>>Completed</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='admin-jobs.php'">Clear</button>
                </div>

            </form>

            <div style="margin-top:20px">

                <?php if ($job_result->num_rows == 0) { ?>

                    <div class="empty">No job found for this search.</div>

                <?php } else { ?>

                    <table>

                        <tr>
                            <th>Job</th><th>Client</th><th>Budget</th><th>Deadline</th>
                            <th>Proposals</th><th>Status</th><th>Action</th>
                        </tr>

                        <?php while ($job = $job_result->fetch_assoc()) {

                            $proposal_count = countRows($conn,
                                "SELECT COUNT(*) AS total FROM proposals WHERE job_id = ?", $job["job_id"]);
                            ?>

                            <tr>
                                <td><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></td>
                                <td><?php echo htmlspecialchars($job["client_name"]); ?></td>
                                <td><?php echo showMoney($job["budget"]); ?></td>
                                <td><?php echo showDate($job["deadline"]); ?></td>
                                <td><?php echo $proposal_count; ?></td>
                                <td><?php echo showStatus($job["job_status"]); ?></td>
                                <td>
                                    <button class="btn-small btn-red" onclick="if (confirm('Remove this job? The proposals will also be deleted.')) { window.location.href='admin-jobs.php?action=remove_job&id=<?php echo $job["job_id"]; ?>'; }">Remove</button>
                                </td>
                            </tr>

                        <?php } ?>

                    </table>

                <?php } ?>

            </div>

        </div>

        <!-- ---------- Exchange posts ---------- -->

        <div class="box">

            <h2 class="box-title">All Skill Exchange Posts</h2>

            <form method="GET" action="admin-jobs.php">

                <div class="row">

                    <div class="col-half">
                        <label for="post_search">Search post</label>
                        <input type="search" id="post_search" name="post_search" placeholder="Write a word or a student name"
                               value="<?php echo htmlspecialchars($post_search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="post_status">Status</label>
                        <select id="post_status" name="post_status">
                            <option value=""        <?php if ($post_status == "")        { echo "selected"; } ?>>All posts</option>
                            <option value="open"    <?php if ($post_status == "open")    { echo "selected"; } ?>>Open</option>
                            <option value="matched" <?php if ($post_status == "matched") { echo "selected"; } ?>>Matched</option>
                            <option value="done"    <?php if ($post_status == "done")    { echo "selected"; } ?>>Completed</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='admin-jobs.php'">Clear</button>
                </div>

            </form>

            <div style="margin-top:20px">

                <?php if ($post_result->num_rows == 0) { ?>

                    <div class="empty">No exchange post found for this search.</div>

                <?php } else { ?>

                    <table>

                        <tr>
                            <th>Student</th><th>Can Teach</th><th>Wants To Learn</th>
                            <th>Description</th><th>Requests</th><th>Status</th><th>Action</th>
                        </tr>

                        <?php while ($post = $post_result->fetch_assoc()) {

                            $request_count = countRows($conn,
                                "SELECT COUNT(*) AS total FROM requests WHERE post_id = ?", $post["post_id"]);
                            ?>

                            <tr>
                                <td><?php echo htmlspecialchars($post["name"]); ?></td>
                                <td><?php echo htmlspecialchars($post["offer_skill"]); ?></td>
                                <td><?php echo htmlspecialchars($post["want_skill"]); ?></td>
                                <td><?php echo htmlspecialchars($post["description"]); ?></td>
                                <td><?php echo $request_count; ?></td>
                                <td><?php echo showStatus($post["post_status"]); ?></td>
                                <td>
                                    <button class="btn-small btn-red" onclick="if (confirm('Remove this exchange post?')) { window.location.href='admin-jobs.php?action=remove_post&id=<?php echo $post["post_id"]; ?>'; }">Remove</button>
                                </td>
                            </tr>

                        <?php } ?>

                    </table>

                <?php } ?>

            </div>

        </div>

    </div>

<?php
$job_stmt->close();
$post_stmt->close();
include "footer.php";
?>
