<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_admin.php";     // only the admin can open this page

// ---------- count everything ----------

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$total_users = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status = 'active'");
$active_users = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status = 'suspended'");
$suspended_users = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM jobs");
$total_jobs = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM posts");
$total_posts = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM proposals");
$total_proposals = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM reviews");
$total_reviews = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE status = 'pending'");
$pending_reports = $result->fetch_assoc()["total"];

// ---------- jobs by status ----------

$result = $conn->query("SELECT COUNT(*) AS total FROM jobs WHERE job_status = 'open'");
$open_jobs = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM jobs WHERE job_status = 'working'");
$working_jobs = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM jobs WHERE job_status = 'done'");
$done_jobs = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT SUM(budget) AS total FROM jobs");
$total_money = $result->fetch_assoc()["total"];

$page_title = "Admin Overview";
include "header.php";
?>

    <div class="page">

        <h1>Platform Overview</h1>
        <p class="page-info">All the numbers of KaajKoro in one page.</p>

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-name">Total Users</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_jobs; ?></div>
                <div class="stat-name">Total Jobs</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_posts; ?></div>
                <div class="stat-name">Exchange Posts</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $pending_reports; ?></div>
                <div class="stat-name">Pending Reports</div>
            </div>

        </div>

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo $active_users; ?></div>
                <div class="stat-name">Active Users</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $suspended_users; ?></div>
                <div class="stat-name">Suspended Users</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_proposals; ?></div>
                <div class="stat-name">Proposals</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_reviews; ?></div>
                <div class="stat-name">Reviews</div>
            </div>

        </div>

        <div class="row">

            <div class="col-left">

                <div class="box">

                    <h2 class="box-title">Jobs By Status</h2>

                    <table>
                        <tr><th>Status</th><th>How Many</th></tr>
                        <tr><td>Open</td><td><?php echo $open_jobs; ?></td></tr>
                        <tr><td>Working</td><td><?php echo $working_jobs; ?></td></tr>
                        <tr><td>Completed</td><td><?php echo $done_jobs; ?></td></tr>
                        <tr><td><b>Total job value</b></td><td><b><?php echo showMoney($total_money); ?></b></td></tr>
                    </table>

                </div>

                <div class="box">

                    <h2 class="box-title">Most Wanted Skills</h2>

                    <table>

                        <tr><th>Skill</th><th>Jobs</th><th>Exchange Posts</th><th>Total</th></tr>

                        <?php
                        // this query counts every skill in the job_skills table
                        // and in the posts table, and adds them together
                        $result = $conn->query("SELECT s.skill_name,
                                                       (SELECT COUNT(*) FROM job_skills j WHERE j.skill_id = s.skill_id) AS job_count,
                                                       (SELECT COUNT(*) FROM posts p WHERE p.want_skill_id = s.skill_id) AS post_count
                                                FROM skills s
                                                ORDER BY (job_count + post_count) DESC");

                        while ($row = $result->fetch_assoc()) {

                            $total = $row["job_count"] + $row["post_count"];

                            if ($total > 0) {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["skill_name"]); ?></td>
                                    <td><?php echo $row["job_count"]; ?></td>
                                    <td><?php echo $row["post_count"]; ?></td>
                                    <td><b><?php echo $total; ?></b></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>

                    </table>

                </div>

            </div>

            <div class="col-right">

                <div class="box">

                    <h2 class="box-title">New Members</h2>

                    <?php
                    $result = $conn->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 5");

                    while ($user = $result->fetch_assoc()) {
                        ?>

                        <p>
                            <b><?php echo htmlspecialchars($user["name"]); ?></b> <?php echo showStatus($user["status"]); ?><br>
                            <span class="gray-text"><?php echo htmlspecialchars($user["university"]); ?>
                                &nbsp; | &nbsp; joined <?php echo showDate($user["joined"]); ?></span>
                        </p>
                        <hr style="border:0;border-top:1px solid #eee;margin:8px 0">

                        <?php
                    }
                    ?>

                </div>

                <div class="box">

                    <h2 class="box-title">Pending Reports</h2>

                    <?php
                    $result = $conn->query("SELECT r.*, u.name AS reporter
                                            FROM reports r
                                            JOIN users u ON r.from_id = u.user_id
                                            WHERE r.status = 'pending'
                                            ORDER BY r.report_id DESC");

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>No pending report. Everything is fine.</div>";
                    }

                    while ($report = $result->fetch_assoc()) {
                        ?>

                        <div class="item">
                            <h3><?php echo strtoupper($report["type"]); ?> ( id : <?php echo $report["target_id"]; ?> )</h3>
                            <p class="item-text"><?php echo htmlspecialchars($report["reason"]); ?></p>
                            <p class="gray-text">Sent by <?php echo htmlspecialchars($report["reporter"]); ?>
                                on <?php echo showDate($report["created_at"]); ?></p>
                        </div>

                        <?php
                    }
                    ?>

                    <a href="admin-reports.php">See all reports</a>

                </div>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
