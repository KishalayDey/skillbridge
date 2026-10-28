<?php
session_start();
require_once "config.php";
require_once "functions.php";

$my_id = 0;

if (isset($_SESSION["user_id"])) {
    $my_id = $_SESSION["user_id"];
}

$job_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
$job = getJob($conn, $job_id);

$errors = array();
$bid = "";
$letter = "";

// ============================================
// The student sent a proposal
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $job != null && $my_id > 0) {

    $bid    = trim($_POST["bid"]);
    $letter = trim($_POST["letter"]);

    if ($bid == "") {
        $errors["bid"] = "Bid amount is required.";
    }
    else if (intval($bid) < 100) {
        $errors["bid"] = "The bid must be at least Tk 100.";
    }

    if ($letter == "") {
        $errors["letter"] = "Cover letter is required.";
    }
    else if (strlen($letter) < 30) {
        $errors["letter"] = "Write at least 30 characters.";
    }

    if (count($errors) == 0) {

        $bid_amount = intval($bid);
        $today = date("Y-m-d");

        $stmt = $conn->prepare("INSERT INTO proposals (job_id, user_id, bid, letter, status, created_at)
                                VALUES (?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("iiiss", $job_id, $my_id, $bid_amount, $letter, $today);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $job["client_id"],
            getUserName($conn, $my_id) . " sent a proposal for your job : " . $job["title"], "my-jobs.php");

        addActivity($conn, $my_id, "Sent a proposal for the job : " . $job["title"]);

        header("Location: proposals.php");
        exit();
    }
}

$page_title = "Job Details";
include "header.php";
?>

    <div class="page">

        <p><a href="jobs.php">&#8592; Back to all jobs</a></p>

        <?php if ($job == null) { ?>

            <div class="empty">This job is not found. It may be deleted by the client or the admin.</div>

        <?php } else {

            $client = getUser($conn, $job["client_id"]);
            $skills = getJobSkills($conn, $job["job_id"]);

            // did I already apply for this job?
            $already_applied = false;

            if ($my_id > 0) {
                $stmt = $conn->prepare("SELECT proposal_id FROM proposals WHERE job_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $job_id, $my_id);
                $stmt->execute();
                $check = $stmt->get_result();

                if ($check->num_rows > 0) {
                    $already_applied = true;
                }

                $stmt->close();
            }
            ?>

            <div class="row">

                <!-- Left side : the job -->

                <div class="col-left">

                    <div class="box">

                        <h1><?php echo htmlspecialchars($job["title"]); ?></h1>
                        <p class="page-info">Posted on <?php echo showDate($job["created_at"]); ?>
                            &nbsp; | &nbsp; <?php echo showStatus($job["job_status"]); ?></p>

                        <table>
                            <tr><th>Budget</th><td><?php echo showMoney($job["budget"]); ?></td></tr>
                            <tr><th>Deadline</th><td><?php echo showDate($job["deadline"]); ?></td></tr>
                            <tr><th>Skills Needed</th><td><?php echo htmlspecialchars(implode(", ", $skills)); ?></td></tr>
                        </table>

                        <h3 style="margin-top:20px">Job Description</h3>
                        <p class="item-text"><?php echo htmlspecialchars($job["description"]); ?></p>

                        <?php if ($job["hired_id"] != 0) { ?>
                            <div class="result">
                                <b><?php echo htmlspecialchars(getUserName($conn, $job["hired_id"])); ?></b> is selected for this job.
                            </div>
                        <?php } ?>

                        <?php if ($my_id == 0) { ?>
                            <div class="buttons">
                                <button onclick="window.location.href='login.php'">Login to send a proposal</button>
                            </div>
                        <?php } else if ($already_applied) { ?>
                            <div class="result">
                                You already sent a proposal for this job. You can see it in the My Proposals page.
                            </div>
                        <?php } else if ($job["job_status"] != "open") { ?>
                            <div class="result">
                                This job is not open now, so you cannot send a proposal.
                            </div>
                        <?php } ?>

                    </div>

                    <?php
                    // ---------- the apply form ----------
                    if ($my_id > 0 && $my_id != $job["client_id"] && $already_applied == false && $job["job_status"] == "open") {
                        ?>

                        <div class="box">

                            <h2 class="box-title">Send a Proposal</h2>

                            <form method="POST" action="job-details.php?id=<?php echo $job_id; ?>" id="applyForm">

                                <label for="bid">Your Bid Amount (Tk)</label>
                                <input type="number" id="bid" name="bid"
                                       value="<?php echo $bid == "" ? $job["budget"] : htmlspecialchars($bid); ?>">
                                <small id="bidError" class="error"><?php if (isset($errors["bid"])) { echo $errors["bid"]; } ?></small>

                                <label for="letter">Cover Letter</label>
                                <textarea id="letter" name="letter"
                                          placeholder="Write why you are the right person for this job and how much time you need."><?php echo htmlspecialchars($letter); ?></textarea>
                                <small id="letterError" class="error"><?php if (isset($errors["letter"])) { echo $errors["letter"]; } ?></small>

                                <button type="submit" class="btn-full">Send Proposal</button>

                            </form>

                        </div>

                        <?php
                    }
                    ?>

                    <?php
                    // ---------- the proposal list, only for the client of this job ----------
                    if ($my_id == $job["client_id"]) {
                        ?>

                        <div class="box">

                            <h2 class="box-title">Proposals For This Job</h2>

                            <?php
                            $stmt = $conn->prepare("SELECT p.*, u.name, u.university
                                                    FROM proposals p
                                                    JOIN users u ON p.user_id = u.user_id
                                                    WHERE p.job_id = ?");
                            $stmt->bind_param("i", $job_id);
                            $stmt->execute();
                            $proposals = $stmt->get_result();

                            if ($proposals->num_rows == 0) {
                                echo "<div class='empty'>No proposal is received for this job yet.</div>";
                            }

                            while ($proposal = $proposals->fetch_assoc()) {

                                $student_rating = getAverageRating($conn, $proposal["user_id"]);
                                ?>

                                <div class="item">
                                    <h3><a href="student-profile.php?id=<?php echo $proposal["user_id"]; ?>"><?php echo htmlspecialchars($proposal["name"]); ?></a></h3>
                                    <p class="item-info"><?php echo htmlspecialchars($proposal["university"]); ?>
                                        &nbsp; | &nbsp; <?php echo showStars($student_rating); ?> <?php echo number_format($student_rating, 1); ?></p>
                                    <p class="item-text"><?php echo htmlspecialchars($proposal["letter"]); ?></p>
                                    <p class="price">Bid : <?php echo showMoney($proposal["bid"]); ?> &nbsp; <?php echo showStatus($proposal["status"]); ?></p>

                                    <div class="buttons">
                                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $proposal["user_id"]; ?>'">Message</button>
                                        <button class="btn-small" onclick="window.location.href='my-jobs.php'">Accept or Reject</button>
                                    </div>
                                </div>

                                <?php
                            }

                            $stmt->close();
                            ?>

                        </div>

                        <?php
                    }
                    ?>

                </div>

                <!-- Right side : the client -->

                <div class="col-right">

                    <div class="box">

                        <h2 class="box-title">About The Client</h2>

                        <?php $client_rating = getAverageRating($conn, $client["user_id"]); ?>

                        <h3><?php echo htmlspecialchars($client["name"]); ?></h3>
                        <p class="gray-text"><?php echo htmlspecialchars($client["university"]); ?> , <?php echo htmlspecialchars($client["city"]); ?></p>
                        <p><?php echo showStars($client_rating); ?> <?php echo number_format($client_rating, 1); ?>
                            ( <?php echo countReviews($conn, $client["user_id"]); ?> reviews )</p>
                        <p class="item-text"><?php echo htmlspecialchars($client["bio"]); ?></p>
                        <p class="gray-text">Member since <?php echo showDate($client["joined"]); ?></p>

                        <div class="buttons">
                            <button class="btn-small" onclick="window.location.href='student-profile.php?id=<?php echo $client["user_id"]; ?>'">View Profile</button>

                            <?php if ($my_id > 0 && $my_id != $client["user_id"]) { ?>
                                <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $client["user_id"]; ?>'">Message</button>
                            <?php } ?>
                        </div>

                        <?php if ($my_id > 0 && $my_id != $client["user_id"]) { ?>
                            <p style="margin-top:12px">
                                <a href="report.php?type=job&id=<?php echo $job["job_id"]; ?>">Report this job</a>
                            </p>
                        <?php } ?>

                    </div>

                </div>

            </div>

        <?php } ?>

    </div>

<?php include "footer.php"; ?>
