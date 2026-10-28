<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

// ---------- delete one of my own reviews ----------

if (isset($_GET["action"]) && $_GET["action"] == "delete") {

    $review_id = intval($_GET["id"]);

    // the WHERE also checks from_id, so I can only delete a review I wrote
    $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ? AND from_id = ?");
    $stmt->bind_param("ii", $review_id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: reviews.php");
    exit();
}

$rating = getAverageRating($conn, $my_id);
$review_count = countReviews($conn, $my_id);
$given_count = countRows($conn, "SELECT COUNT(*) AS total FROM reviews WHERE from_id = ?", $my_id);

$page_title = "Ratings and Reviews";
include "header.php";
?>

    <div class="page">

        <h1>Ratings and Reviews</h1>
        <p class="page-info">Your reputation in KaajKoro comes from your completed jobs and skill exchanges.</p>

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo number_format($rating, 1); ?></div>
                <div class="stat-name">Average Rating</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $review_count; ?></div>
                <div class="stat-name">Reviews About Me</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $given_count; ?></div>
                <div class="stat-name">Reviews I Gave</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo showStars($rating); ?></div>
                <div class="stat-name">My Stars</div>
            </div>

        </div>

        <div class="row">

            <div class="col-half">

                <div class="box">

                    <h2 class="box-title">Reviews About Me</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT r.*, u.name AS writer
                                            FROM reviews r
                                            JOIN users u ON r.from_id = u.user_id
                                            WHERE r.to_id = ? AND r.status = 'visible'
                                            ORDER BY r.review_id DESC");
                    $stmt->bind_param("i", $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>Nobody gave you a review yet. Finish a job or an exchange first.</div>";
                    }

                    while ($review = $result->fetch_assoc()) {
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

                            <div class="buttons">
                                <button class="btn-small btn-red" onclick="window.location.href='report.php?type=review&id=<?php echo $review["review_id"]; ?>'">Report This Review</button>
                            </div>

                        </div>

                        <?php
                    }

                    $stmt->close();
                    ?>

                </div>

            </div>

            <div class="col-half">

                <div class="box">

                    <h2 class="box-title">Reviews I Gave</h2>

                    <?php
                    $stmt = $conn->prepare("SELECT r.*, u.name AS person
                                            FROM reviews r
                                            JOIN users u ON r.to_id = u.user_id
                                            WHERE r.from_id = ?
                                            ORDER BY r.review_id DESC");
                    $stmt->bind_param("i", $my_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 0) {
                        echo "<div class='empty'>You did not give any review yet.</div>";
                    }

                    while ($review = $result->fetch_assoc()) {
                        ?>

                        <div class="item item-green">

                            <h3>To <?php echo htmlspecialchars($review["person"]); ?></h3>
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

                            <div class="buttons">
                                <button class="btn-small btn-red" onclick="if (confirm('Do you want to delete this review?')) { window.location.href='reviews.php?action=delete&id=<?php echo $review["review_id"]; ?>'; }">Delete My Review</button>
                            </div>

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
