<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();

// ============================================
// The buttons of this page send an action in the link,
// for example  my-jobs.php?action=accept&id=5
// ============================================

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

// ---------- accept a proposal ----------

if ($action == "accept" && $id > 0) {

    $stmt = $conn->prepare("SELECT * FROM proposals WHERE proposal_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($proposal != null) {

        $job = getJob($conn, $proposal["job_id"]);

        // only the client of this job can accept
        if ($job != null && $job["client_id"] == $my_id) {

            // this proposal becomes accepted
            $stmt = $conn->prepare("UPDATE proposals SET status = 'accepted' WHERE proposal_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // the job becomes working and the student is hired
            $stmt = $conn->prepare("UPDATE jobs SET job_status = 'working', hired_id = ? WHERE job_id = ?");
            $stmt->bind_param("ii", $proposal["user_id"], $job["job_id"]);
            $stmt->execute();
            $stmt->close();

            // all the other proposals of this job are rejected
            $stmt = $conn->prepare("SELECT * FROM proposals
                                    WHERE job_id = ? AND proposal_id != ? AND status = 'pending'");
            $stmt->bind_param("ii", $job["job_id"], $id);
            $stmt->execute();
            $others = $stmt->get_result();

            while ($other = $others->fetch_assoc()) {

                $update = $conn->prepare("UPDATE proposals SET status = 'rejected' WHERE proposal_id = ?");
                $update->bind_param("i", $other["proposal_id"]);
                $update->execute();
                $update->close();

                addNotification($conn, $other["user_id"],
                    "Your proposal for " . $job["title"] . " is not selected.", "proposals.php");
            }

            $stmt->close();

            addNotification($conn, $proposal["user_id"],
                "Your proposal for " . $job["title"] . " is accepted. You can start the work.", "my-jobs.php");

            addActivity($conn, $my_id,
                "Hired " . getUserName($conn, $proposal["user_id"]) . " for the job : " . $job["title"]);
        }
    }

    header("Location: my-jobs.php");
    exit();
}

// ---------- reject a proposal ----------

if ($action == "reject" && $id > 0) {

    $stmt = $conn->prepare("SELECT * FROM proposals WHERE proposal_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($proposal != null) {

        $job = getJob($conn, $proposal["job_id"]);

        if ($job != null && $job["client_id"] == $my_id) {

            $stmt = $conn->prepare("UPDATE proposals SET status = 'rejected' WHERE proposal_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            addNotification($conn, $proposal["user_id"],
                "Your proposal for " . $job["title"] . " is rejected.", "proposals.php");
        }
    }

    header("Location: my-jobs.php");
    exit();
}

// ---------- delete a job ----------

if ($action == "delete" && $id > 0) {

    $job = getJob($conn, $id);

    if ($job != null && $job["client_id"] == $my_id) {

        // the proposals and the job skills are deleted automatically
        // because of ON DELETE CASCADE in the database
        $stmt = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: my-jobs.php");
    exit();
}

// ---------- the student says the work is delivered ----------

if ($action == "deliver" && $id > 0) {

    $job = getJob($conn, $id);

    if ($job != null && $job["hired_id"] == $my_id) {

        addNotification($conn, $job["client_id"],
            getUserName($conn, $my_id) . " delivered the work of : " . $job["title"], "my-jobs.php");

        addActivity($conn, $my_id, "Delivered the work of : " . $job["title"]);
    }

    header("Location: my-jobs.php");
    exit();
}

// ---------- the client says the job is complete ----------

if ($action == "finish" && $id > 0) {

    $job = getJob($conn, $id);

    if ($job != null && $job["client_id"] == $my_id) {

        $stmt = $conn->prepare("UPDATE jobs SET job_status = 'done' WHERE job_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $job["hired_id"],
            "The client marked " . $job["title"] . " as complete.", "reviews.php");

        addActivity($conn, $my_id, "Completed the job : " . $job["title"]);

        // open the review form for the student who did the work
        header("Location: my-jobs.php?review=" . $job["hired_id"] . "&job=" . $job["job_id"]);
        exit();
    }

    header("Location: my-jobs.php");
    exit();
}

// ============================================
// The review form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $to_id   = intval($_POST["to_id"]);
    $job_id  = intval($_POST["job_id"]);
    $rating  = $_POST["rating"];
    $comment = trim($_POST["comment"]);

    if ($rating == "") {
        $errors["rating"] = "Please select a rating.";
    }

    if ($comment == "") {
        $errors["comment"] = "Please write a review.";
    }
    else if (strlen($comment) < 10) {
        $errors["comment"] = "Write at least 10 characters.";
    }

    if (count($errors) == 0) {

        $rating_value = intval($rating);
        $today = date("Y-m-d");

        $stmt = $conn->prepare("INSERT INTO reviews (from_id, to_id, job_id, rating, comment, review_date, status)
                                VALUES (?, ?, ?, ?, ?, ?, 'visible')");
        $stmt->bind_param("iiiiss", $my_id, $to_id, $job_id, $rating_value, $comment, $today);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $to_id,
            getUserName($conn, $my_id) . " gave you a " . $rating_value . " star review.", "reviews.php");

        addActivity($conn, $my_id, "Gave a review to " . getUserName($conn, $to_id));

        header("Location: my-jobs.php");
        exit();
    }
}

// ---------- should the review form be open? ----------

$review_to  = isset($_GET["review"]) ? intval($_GET["review"]) : 0;
$review_job = isset($_GET["job"])    ? intval($_GET["job"])    : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $review_to  = intval($_POST["to_id"]);
    $review_job = intval($_POST["job_id"]);
}

$page_title = "My Jobs";
include "header.php";
?>

    <div class="page">

        <h1>My Jobs</h1>
        <p class="page-info">The jobs you posted as a client and the work you are doing as a freelancer.</p>

        <div class="buttons">
            <button onclick="window.location.href='post-job.php'">Post a New Job</button>
            <button class="btn-gray" onclick="window.location.href='proposals.php'">My Proposals</button>
        </div>

        <?php if ($review_to > 0) { ?>

            <!-- Review form -->

            <div class="box">

                <h2 class="box-title">Give Rating and Review</h2>

                <form method="POST" action="my-jobs.php">

                    <p class="page-info">Your review for <b><?php echo htmlspecialchars(getUserName($conn, $review_to)); ?></b>
                        will be shown in the public profile.</p>

                    <input type="hidden" name="to_id" value="<?php echo $review_to; ?>">
                    <input type="hidden" name="job_id" value="<?php echo $review_job; ?>">

                    <label for="rating">Rating</label>

                    <select id="rating" name="rating">
                        <option value="">-- Select a rating --</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Very Good</option>
                        <option value="3">3 - Good</option>
                        <option value="2">2 - Bad</option>
                        <option value="1">1 - Very Bad</option>
                    </select>

                    <small class="error"><?php if (isset($errors["rating"])) { echo $errors["rating"]; } ?></small>

                    <label for="comment">Your Review</label>
                    <textarea id="comment" name="comment" placeholder="How was the work quality, the communication and the time?"></textarea>
                    <small class="error"><?php if (isset($errors["comment"])) { echo $errors["comment"]; } ?></small>

                    <div class="buttons">
                        <button type="submit">Submit Review</button>
                        <button type="button" class="btn-gray" onclick="window.location.href='my-jobs.php'">Cancel</button>
                    </div>

                </form>

            </div>

        <?php } ?>

        <!-- ---------- Jobs I posted ---------- -->

        <div class="box">

            <h2 class="box-title">Jobs I Posted</h2>

            <?php
            $stmt = $conn->prepare("SELECT * FROM jobs
                                    WHERE client_id = ? AND job_status != 'done'
                                    ORDER BY job_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $my_jobs = $stmt->get_result();

            if ($my_jobs->num_rows == 0) {
                echo "<div class='empty'>You did not post any job yet.</div>";
            }

            while ($job = $my_jobs->fetch_assoc()) {
                ?>

                <div class="item">

                    <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>

                    <p class="item-info"><?php echo showMoney($job["budget"]); ?>
                        &nbsp; | &nbsp; Deadline : <?php echo showDate($job["deadline"]); ?>
                        &nbsp; | &nbsp; <?php echo showStatus($job["job_status"]); ?></p>

                    <div class="buttons">
                        <button class="btn-small" onclick="window.location.href='post-job.php?edit=<?php echo $job["job_id"]; ?>'">Edit</button>
                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to delete this job?')) { window.location.href='my-jobs.php?action=delete&id=<?php echo $job["job_id"]; ?>'; }">Delete</button>

                        <?php if ($job["job_status"] == "working") { ?>
                            <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $job["hired_id"]; ?>'">Message <?php echo htmlspecialchars(getUserName($conn, $job["hired_id"])); ?></button>
                            <button class="btn-small btn-green" onclick="if (confirm('Is this job complete?')) { window.location.href='my-jobs.php?action=finish&id=<?php echo $job["job_id"]; ?>'; }">Job is Complete</button>
                        <?php } ?>
                    </div>

                    <h3 style="margin-top:15px">Proposals</h3>

                    <?php
                    $stmt2 = $conn->prepare("SELECT p.*, u.name, u.university
                                             FROM proposals p
                                             JOIN users u ON p.user_id = u.user_id
                                             WHERE p.job_id = ?");
                    $stmt2->bind_param("i", $job["job_id"]);
                    $stmt2->execute();
                    $proposals = $stmt2->get_result();

                    if ($proposals->num_rows == 0) {
                        echo "<p class='gray-text'>No proposal is received yet.</p>";
                    }

                    while ($proposal = $proposals->fetch_assoc()) {

                        $student_rating = getAverageRating($conn, $proposal["user_id"]);
                        ?>

                        <div class="item item-orange">

                            <h3><a href="student-profile.php?id=<?php echo $proposal["user_id"]; ?>"><?php echo htmlspecialchars($proposal["name"]); ?></a></h3>
                            <p class="item-info"><?php echo htmlspecialchars($proposal["university"]); ?>
                                &nbsp; | &nbsp; <?php echo showStars($student_rating); ?> <?php echo number_format($student_rating, 1); ?></p>
                            <p class="item-text"><?php echo htmlspecialchars($proposal["letter"]); ?></p>
                            <p class="price">Bid : <?php echo showMoney($proposal["bid"]); ?> &nbsp; <?php echo showStatus($proposal["status"]); ?></p>

                            <?php if ($proposal["status"] == "pending") { ?>
                                <div class="buttons">
                                    <button class="btn-small btn-green" onclick="if (confirm('Do you want to hire this student?')) { window.location.href='my-jobs.php?action=accept&id=<?php echo $proposal["proposal_id"]; ?>'; }">Accept</button>
                                    <button class="btn-small btn-red" onclick="if (confirm('Do you want to reject this proposal?')) { window.location.href='my-jobs.php?action=reject&id=<?php echo $proposal["proposal_id"]; ?>'; }">Reject</button>
                                    <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $proposal["user_id"]; ?>'">Message</button>
                                </div>
                            <?php } ?>

                        </div>

                        <?php
                    }

                    $stmt2->close();
                    ?>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

        <!-- ---------- Work I am doing ---------- -->

        <div class="box">

            <h2 class="box-title">Work I Am Doing</h2>

            <?php
            $stmt = $conn->prepare("SELECT * FROM jobs
                                    WHERE hired_id = ? AND job_status = 'working'
                                    ORDER BY job_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $working = $stmt->get_result();

            if ($working->num_rows == 0) {
                echo "<div class='empty'>You are not doing any job right now. <a href='jobs.php'>Find work</a></div>";
            }

            while ($job = $working->fetch_assoc()) {
                ?>

                <div class="item item-green">

                    <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>
                    <p class="item-info">Client : <?php echo htmlspecialchars(getUserName($conn, $job["client_id"])); ?>
                        &nbsp; | &nbsp; Deadline : <?php echo showDate($job["deadline"]); ?></p>
                    <p class="price"><?php echo showMoney($job["budget"]); ?> &nbsp; <?php echo showStatus($job["job_status"]); ?></p>

                    <div class="buttons">
                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $job["client_id"]; ?>'">Message Client</button>
                        <button class="btn-small" onclick="window.location.href='meetings.php?to=<?php echo $job["client_id"]; ?>'">Arrange Meeting</button>
                        <button class="btn-small btn-green" onclick="if (confirm('Did you finish and deliver the work?')) { window.location.href='my-jobs.php?action=deliver&id=<?php echo $job["job_id"]; ?>'; }">Work is Delivered</button>
                    </div>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

        <!-- ---------- Completed jobs ---------- -->

        <div class="box">

            <h2 class="box-title">Completed Jobs</h2>

            <?php
            $stmt = $conn->prepare("SELECT * FROM jobs
                                    WHERE job_status = 'done' AND (client_id = ? OR hired_id = ?)
                                    ORDER BY job_id DESC");
            $stmt->bind_param("ii", $my_id, $my_id);
            $stmt->execute();
            $done_jobs = $stmt->get_result();

            if ($done_jobs->num_rows == 0) {
                echo "<div class='empty'>No completed job yet.</div>";
            }

            while ($job = $done_jobs->fetch_assoc()) {

                // who is the other person of this job
                if ($job["client_id"] == $my_id) {
                    $other_id = $job["hired_id"];
                }
                else {
                    $other_id = $job["client_id"];
                }

                // did I already give a review for this job?
                $stmt2 = $conn->prepare("SELECT review_id FROM reviews WHERE from_id = ? AND job_id = ?");
                $stmt2->bind_param("ii", $my_id, $job["job_id"]);
                $stmt2->execute();
                $given = $stmt2->get_result()->num_rows;
                $stmt2->close();
                ?>

                <div class="item">

                    <h3><?php echo htmlspecialchars($job["title"]); ?></h3>
                    <p class="item-info">With <?php echo htmlspecialchars(getUserName($conn, $other_id)); ?>
                        &nbsp; | &nbsp; <?php echo showMoney($job["budget"]); ?>
                        &nbsp; | &nbsp; <?php echo showStatus($job["job_status"]); ?></p>

                    <div class="buttons">
                        <?php if ($given > 0) { ?>
                            <span class="tag tag-green">You gave a review</span>
                        <?php } else { ?>
                            <button class="btn-small" onclick="window.location.href='my-jobs.php?review=<?php echo $other_id; ?>&job=<?php echo $job["job_id"]; ?>'">Give Review</button>
                        <?php } ?>

                        <button class="btn-small btn-gray" onclick="window.location.href='student-profile.php?id=<?php echo $other_id; ?>'">View Profile</button>
                    </div>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

    </div>

<?php include "footer.php"; ?>
