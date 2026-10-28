<?php
session_start();
require_once "config.php";
require_once "functions.php";

$my_id = 0;

if (isset($_SESSION["user_id"])) {
    $my_id = $_SESSION["user_id"];
}

$student_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

// if I open my own profile, go to the page where I can edit it
if ($student_id == $my_id && $my_id > 0) {
    header("Location: profile.php");
    exit();
}

$student = getUser($conn, $student_id);

$page_title = "Student Profile";
include "header.php";
?>

    <div class="page">

        <p><a href="students.php">&#8592; Back to all students</a></p>

        <?php if ($student == null) { ?>

            <div class="empty">This student is not found. The account may be deleted.</div>

        <?php } else {

            $rating = getAverageRating($conn, $student_id);
            $skills = getUserSkills($conn, $student_id);

            $job_posted = countRows($conn, "SELECT COUNT(*) AS total FROM jobs WHERE client_id = ?", $student_id);
            $job_done   = countRows($conn, "SELECT COUNT(*) AS total FROM jobs WHERE hired_id = ? AND job_status = 'done'", $student_id);
            ?>

            <div class="row">

                <div class="col-left">

                    <div class="box">

                        <h1><?php echo htmlspecialchars($student["name"]); ?></h1>

                        <p class="page-info"><?php echo htmlspecialchars($student["university"]); ?>
                            &nbsp; | &nbsp; <?php echo htmlspecialchars($student["city"]); ?>
                            &nbsp; | &nbsp; <?php echo showStatus($student["status"]); ?></p>

                        <p><?php echo showStars($rating); ?> <?php echo number_format($rating, 1); ?>
                            ( <?php echo countReviews($conn, $student_id); ?> reviews )</p>

                        <h3 style="margin-top:20px">About</h3>
                        <p class="item-text"><?php echo htmlspecialchars($student["bio"]); ?></p>

                        <h3>Skills</h3>
                        <p>
                            <?php
                            foreach ($skills as $skill) {
                                echo "<span class='tag tag-blue'>" . htmlspecialchars($skill) . "</span>";
                            }
                            ?>
                        </p>

                        <h3>Education</h3>
                        <p class="item-text"><?php echo htmlspecialchars($student["education"]); ?></p>

                        <h3>Experience</h3>
                        <p class="item-text"><?php echo htmlspecialchars($student["experience"]); ?></p>

                        <?php if ($student["portfolio"] != "") { ?>
                            <h3>Portfolio</h3>
                            <p class="item-text">
                                <a href="https://<?php echo htmlspecialchars($student["portfolio"]); ?>" target="_blank">
                                    <?php echo htmlspecialchars($student["portfolio"]); ?></a>
                            </p>
                        <?php } ?>

                        <div class="buttons">

                            <?php if ($my_id > 0) { ?>
                                <button onclick="window.location.href='messages.php?to=<?php echo $student_id; ?>'">Send Message</button>
                                <button class="btn-gray" onclick="window.location.href='post-job.php'">Hire For a Job</button>
                                <button class="btn-red" onclick="window.location.href='report.php?type=user&id=<?php echo $student_id; ?>'">Report This User</button>
                            <?php } else { ?>
                                <button onclick="window.location.href='login.php'">Login to contact</button>
                            <?php } ?>

                        </div>

                    </div>

                    <div class="box">

                        <h2 class="box-title">Reviews</h2>

                        <?php
                        $stmt = $conn->prepare("SELECT r.*, u.name AS writer
                                                FROM reviews r
                                                JOIN users u ON r.from_id = u.user_id
                                                WHERE r.to_id = ? AND r.status = 'visible'
                                                ORDER BY r.review_id DESC");
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $reviews = $stmt->get_result();

                        if ($reviews->num_rows == 0) {
                            echo "<div class='empty'>This student has no review yet.</div>";
                        }

                        while ($review = $reviews->fetch_assoc()) {
                            ?>

                            <div class="item">
                                <h3><?php echo htmlspecialchars($review["writer"]); ?></h3>
                                <p class="item-info"><?php echo showStars($review["rating"]); ?>
                                    &nbsp; | &nbsp; <?php echo showDate($review["review_date"]); ?></p>
                                <p class="item-text"><?php echo htmlspecialchars($review["comment"]); ?></p>

                                <?php
                                if ($review["job_id"] > 0) {
                                    $job = getJob($conn, $review["job_id"]);
                                    if ($job != null) {
                                        echo "<p class='gray-text'>Job : " . htmlspecialchars($job["title"]) . "</p>";
                                    }
                                }
                                else {
                                    echo "<p class='gray-text'>Skill exchange</p>";
                                }
                                ?>
                            </div>

                            <?php
                        }

                        $stmt->close();
                        ?>

                    </div>

                </div>

                <div class="col-right">

                    <div class="box">

                        <h2 class="box-title">Information</h2>

                        <table>
                            <tr><th>Member since</th><td><?php echo showDate($student["joined"]); ?></td></tr>
                            <tr><th>Jobs completed</th><td><?php echo $job_done; ?></td></tr>
                            <tr><th>Jobs posted</th><td><?php echo $job_posted; ?></td></tr>
                            <tr><th>Reviews</th><td><?php echo countReviews($conn, $student_id); ?></td></tr>

                            <?php if ($my_id > 0) { ?>
                                <tr><th>Email</th><td><?php echo htmlspecialchars($student["email"]); ?></td></tr>
                                <tr><th>Phone</th><td><?php echo htmlspecialchars($student["phone"]); ?></td></tr>
                            <?php } ?>
                        </table>

                    </div>

                    <div class="box">

                        <h2 class="box-title">Open Exchange Posts</h2>

                        <?php
                        $stmt = $conn->prepare("SELECT p.*, s1.skill_name AS offer_skill, s2.skill_name AS want_skill
                                                FROM posts p
                                                JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                                JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                                WHERE p.user_id = ? AND p.post_status = 'open'");
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $posts = $stmt->get_result();

                        if ($posts->num_rows == 0) {
                            echo "<div class='empty'>No open exchange post.</div>";
                        }

                        while ($post = $posts->fetch_assoc()) {
                            ?>

                            <div class="item item-green">
                                <p>
                                    <span class="tag tag-blue">Can teach : <?php echo htmlspecialchars($post["offer_skill"]); ?></span>
                                    <span class="tag tag-orange">Wants : <?php echo htmlspecialchars($post["want_skill"]); ?></span>
                                </p>
                                <p class="item-text"><?php echo htmlspecialchars($post["description"]); ?></p>
                                <button class="btn-small" onclick="window.location.href='exchange.php'">Open Skill Exchange</button>
                            </div>

                            <?php
                        }

                        $stmt->close();
                        ?>

                    </div>

                </div>

            </div>

        <?php } ?>

    </div>

<?php include "footer.php"; ?>
