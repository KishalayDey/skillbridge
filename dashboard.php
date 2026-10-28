<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";     // only a logged in student can open this page

$my_id = $me["user_id"];

// ---------- the four numbers ----------

$proposal_count = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ?", $my_id);
$job_count      = countRows($conn, "SELECT COUNT(*) AS total FROM jobs WHERE client_id = ?", $my_id);

// requests that other students sent to my exchange posts
$request_count = countRows($conn, "SELECT COUNT(*) AS total
                                   FROM requests r
                                   JOIN posts p ON r.post_id = p.post_id
                                   WHERE p.user_id = ? AND r.status = 'pending'", $my_id);

$rating = getAverageRating($conn, $my_id);

$page_title = "Dashboard";
include "header.php";
?>

    <div class="page">

        <h1>Welcome back, <?php echo htmlspecialchars($me["name"]); ?></h1>
        <p class="page-info">This is everything happening in your account today.</p>

        <!-- Numbers -->

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo $proposal_count; ?></div>
                <div class="stat-name">Proposals Sent</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $job_count; ?></div>
                <div class="stat-name">Jobs I Posted</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $request_count; ?></div>
                <div class="stat-name">Exchange Requests</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo number_format($rating, 1); ?></div>
                <div class="stat-name">My Rating</div>
            </div>

        </div>

        <div class="buttons">
            <button onclick="window.location.href='post-job.php'">Post a Job</button>
            <button class="btn-green" onclick="window.location.href='post-exchange.php'">Post a Skill Exchange</button>
            <button class="btn-gray" onclick="window.location.href='jobs.php'">Find Work</button>
        </div>

        <div class="row" style="margin-top:20px">

            <!-- Left side -->

            <div class="col-left">

                <div class="box">

                    <h2 class="box-title">My Active Work</h2>

                    <?php
                    // a job I am doing for a client, or a job I posted and somebody is doing
                    $stmt = $conn->prepare("SELECT * FROM jobs
                                            WHERE job_status = 'working'
                                            AND (hired_id = ? OR client_id = ?)");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>You have no active work right now.</div>";
                    }

                    while ($job = $result->fetch_assoc()) {
                        ?>

                        <div class="item">
                            <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>

                            <?php if ($job["hired_id"] == $my_id) { ?>
                                <p class="item-info">I am working for <?php echo htmlspecialchars(getUserName($conn, $job["client_id"])); ?></p>
                            <?php } else { ?>
                                <p class="item-info"><?php echo htmlspecialchars(getUserName($conn, $job["hired_id"])); ?> is doing this job for me</p>
                            <?php } ?>

                            <p class="price"><?php echo showMoney($job["budget"]); ?> &nbsp; <?php echo showStatus($job["job_status"]); ?></p>
                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                </div>

                <div class="box">

                    <h2 class="box-title">Jobs For My Skills</h2>

                    <?php
                    // this query joins four tables to find the jobs that need a skill I have
                    $stmt = $conn->prepare("SELECT DISTINCT j.*
                                            FROM jobs j
                                            JOIN job_skills js  ON j.job_id = js.job_id
                                            JOIN user_skills us ON js.skill_id = us.skill_id
                                            WHERE us.user_id = ?
                                              AND j.job_status = 'open'
                                              AND j.client_id != ?
                                            ORDER BY j.job_id DESC
                                            LIMIT 3");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>No job is matching your skills now.</div>";
                    }

                    while ($job = $result->fetch_assoc()) {

                        $skills = getJobSkills($conn, $job["job_id"]);
                        ?>

                        <div class="item">
                            <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>
                            <p class="item-info">Deadline : <?php echo showDate($job["deadline"]); ?>
                                &nbsp; | &nbsp; Skills : <?php echo htmlspecialchars(implode(", ", $skills)); ?></p>
                            <p class="price"><?php echo showMoney($job["budget"]); ?></p>
                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                    <a href="jobs.php">See all jobs</a>

                </div>

                <div class="box">

                    <h2 class="box-title">Students Who Want My Skills</h2>

                    <?php
                    // exchange posts where the wanted skill is one of my skills
                    $stmt = $conn->prepare("SELECT p.*, u.name,
                                                   s1.skill_name AS offer_skill,
                                                   s2.skill_name AS want_skill
                                            FROM posts p
                                            JOIN users u   ON p.user_id = u.user_id
                                            JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                            JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                            JOIN user_skills us ON us.skill_id = p.want_skill_id
                                            WHERE us.user_id = ?
                                              AND p.post_status = 'open'
                                              AND p.user_id != ?
                                            LIMIT 3");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>Nobody is asking for your skills now.</div>";
                    }

                    while ($post = $result->fetch_assoc()) {
                        ?>

                        <div class="item item-green">
                            <h3><?php echo htmlspecialchars($post["name"]); ?></h3>
                            <p class="item-text">Wants to learn <b><?php echo htmlspecialchars($post["want_skill"]); ?></b>
                                and can teach <b><?php echo htmlspecialchars($post["offer_skill"]); ?></b></p>
                            <button class="btn-small" onclick="window.location.href='exchange.php'">Open Skill Exchange</button>
                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                    <a href="exchange.php">See all exchange posts</a>

                </div>

            </div>

            <!-- Right side -->

            <div class="col-right">

                <div class="box">

                    <h2 class="box-title">My Profile</h2>

                    <h3><?php echo htmlspecialchars($me["name"]); ?></h3>
                    <p class="gray-text"><?php echo htmlspecialchars($me["university"]); ?> , <?php echo htmlspecialchars($me["city"]); ?></p>
                    <p><?php echo showStars($rating); ?> <?php echo number_format($rating, 1); ?>
                        ( <?php echo countReviews($conn, $my_id); ?> reviews )</p>

                    <p style="margin-top:10px">
                        <?php
                        $my_skills = getUserSkills($conn, $my_id);

                        foreach ($my_skills as $skill) {
                            echo "<span class='tag'>" . htmlspecialchars($skill) . "</span>";
                        }
                        ?>
                    </p>

                    <div class="buttons">
                        <button class="btn-small" onclick="window.location.href='profile.php'">Edit Profile</button>
                    </div>

                </div>

                <div class="box">

                    <h2 class="box-title">Next Meetings</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT * FROM meetings
                                            WHERE (from_id = ? OR to_id = ?)
                                              AND status = 'scheduled'
                                            ORDER BY date_time
                                            LIMIT 3");
                    $stmt->bind_param("ii", $my_id, $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>No meeting is scheduled.</div>";
                    }

                    while ($meeting = $result->fetch_assoc()) {

                        // who is the other person of this meeting
                        if ($meeting["from_id"] == $my_id) {
                            $other_id = $meeting["to_id"];
                        }
                        else {
                            $other_id = $meeting["from_id"];
                        }
                        ?>

                        <p>
                            <b><?php echo htmlspecialchars(getUserName($conn, $other_id)); ?></b><br>
                            <span class="gray-text"><?php echo showDateTime($meeting["date_time"]); ?>
                                ( <?php echo $meeting["mode"]; ?> )</span>
                        </p>
                        <hr style="border:0;border-top:1px solid #eee;margin:8px 0">

                        <?php
                    }

                    $stmt->close();
                    ?>

                    <a href="meetings.php">All meetings</a>

                </div>

                <div class="box">

                    <h2 class="box-title">Notifications</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT * FROM notifications
                                            WHERE user_id = ?
                                            ORDER BY notif_id DESC
                                            LIMIT 4");
                    $stmt->bind_param("i", $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>No notification.</div>";
                    }

                    while ($notif = $result->fetch_assoc()) {
                        ?>

                        <p>
                            <a href="<?php echo $notif["link"]; ?>"><?php echo htmlspecialchars($notif["text"]); ?></a><br>
                            <span class="gray-text"><?php echo showDateTime($notif["created_at"]); ?></span>
                        </p>
                        <hr style="border:0;border-top:1px solid #eee;margin:8px 0">

                        <?php
                    }

                    $stmt->close();
                    ?>

                    <a href="notifications.php">All notifications</a>

                </div>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
