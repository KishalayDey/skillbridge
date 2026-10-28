<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

// ---------- find one request with its post ----------

function getRequest($conn, $request_id) {

    $stmt = $conn->prepare("SELECT r.*, p.user_id AS post_owner, p.post_status
                            FROM requests r
                            JOIN posts p ON r.post_id = p.post_id
                            WHERE r.request_id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row;
}

// ---------- accept a request ----------

if ($action == "accept" && $id > 0) {

    $request = getRequest($conn, $id);

    // only the owner of the post can accept
    if ($request != null && $request["post_owner"] == $my_id) {

        $stmt = $conn->prepare("UPDATE requests SET status = 'accepted' WHERE request_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE posts SET post_status = 'matched' WHERE post_id = ?");
        $stmt->bind_param("i", $request["post_id"]);
        $stmt->execute();
        $stmt->close();

        // the other requests of the same post are rejected
        $stmt = $conn->prepare("SELECT * FROM requests
                                WHERE post_id = ? AND request_id != ? AND status = 'pending'");
        $stmt->bind_param("ii", $request["post_id"], $id);
        $stmt->execute();
        $others = $stmt->get_result();

        while ($other = $others->fetch_assoc()) {

            $update = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE request_id = ?");
            $update->bind_param("i", $other["request_id"]);
            $update->execute();
            $update->close();

            addNotification($conn, $other["user_id"],
                "Your exchange request is not selected this time.", "exchange-requests.php");
        }

        $stmt->close();

        addNotification($conn, $request["user_id"],
            getUserName($conn, $my_id) . " accepted your skill exchange request.", "exchange-requests.php");

        addActivity($conn, $my_id,
            "Accepted a skill exchange with " . getUserName($conn, $request["user_id"]));
    }

    header("Location: exchange-requests.php");
    exit();
}

// ---------- reject a request ----------

if ($action == "reject" && $id > 0) {

    $request = getRequest($conn, $id);

    if ($request != null && $request["post_owner"] == $my_id) {

        $stmt = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE request_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $request["user_id"],
            getUserName($conn, $my_id) . " could not accept your exchange request.", "exchange-requests.php");
    }

    header("Location: exchange-requests.php");
    exit();
}

// ---------- cancel my own request ----------

if ($action == "cancel" && $id > 0) {

    $stmt = $conn->prepare("DELETE FROM requests WHERE request_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: exchange-requests.php");
    exit();
}

// ---------- the exchange is finished ----------

if ($action == "finish" && $id > 0) {

    $request = getRequest($conn, $id);

    // both sides can mark it complete
    if ($request != null && ($request["post_owner"] == $my_id || $request["user_id"] == $my_id)) {

        $stmt = $conn->prepare("UPDATE posts SET post_status = 'done' WHERE post_id = ?");
        $stmt->bind_param("i", $request["post_id"]);
        $stmt->execute();
        $stmt->close();

        // who is my partner in this exchange
        if ($request["post_owner"] == $my_id) {
            $partner_id = $request["user_id"];
        }
        else {
            $partner_id = $request["post_owner"];
        }

        addNotification($conn, $partner_id,
            getUserName($conn, $my_id) . " marked your skill exchange as complete. You can give a review now.",
            "exchange-requests.php");

        addActivity($conn, $my_id, "Completed a skill exchange with " . getUserName($conn, $partner_id));

        header("Location: exchange-requests.php?review=" . $partner_id);
        exit();
    }

    header("Location: exchange-requests.php");
    exit();
}

// ============================================
// The review form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $to_id   = intval($_POST["to_id"]);
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

        // job_id = 0 means this review came from a skill exchange
        $stmt = $conn->prepare("INSERT INTO reviews (from_id, to_id, job_id, rating, comment, review_date, status)
                                VALUES (?, ?, 0, ?, ?, ?, 'visible')");
        $stmt->bind_param("iiiss", $my_id, $to_id, $rating_value, $comment, $today);
        $stmt->execute();
        $stmt->close();

        addNotification($conn, $to_id,
            getUserName($conn, $my_id) . " gave you a " . $rating_value . " star review.", "reviews.php");

        header("Location: exchange-requests.php");
        exit();
    }
}

$review_to = isset($_GET["review"]) ? intval($_GET["review"]) : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && count($errors) > 0) {
    $review_to = intval($_POST["to_id"]);
}

$page_title = "Exchange Requests";
include "header.php";
?>

    <div class="page">

        <h1>Exchange Requests</h1>
        <p class="page-info">The requests you received for your posts and the requests you sent to others.</p>

        <div class="buttons">
            <button onclick="window.location.href='exchange.php'">Browse Exchange Posts</button>
            <button class="btn-gray" onclick="window.location.href='post-exchange.php'">Post My Offer</button>
        </div>

        <?php if ($review_to > 0) { ?>

            <div class="box">

                <h2 class="box-title">Give Rating and Review</h2>

                <form method="POST" action="exchange-requests.php">

                    <p class="page-info">Your review for <b><?php echo htmlspecialchars(getUserName($conn, $review_to)); ?></b>
                        will be shown in the public profile.</p>

                    <input type="hidden" name="to_id" value="<?php echo $review_to; ?>">

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
                    <textarea id="comment" name="comment" placeholder="Did your partner teach you properly? Were the classes useful?"></textarea>
                    <small class="error"><?php if (isset($errors["comment"])) { echo $errors["comment"]; } ?></small>

                    <div class="buttons">
                        <button type="submit">Submit Review</button>
                        <button type="button" class="btn-gray" onclick="window.location.href='exchange-requests.php'">Cancel</button>
                    </div>

                </form>

            </div>

        <?php } ?>

        <!-- ---------- Requests I received ---------- -->

        <div class="box">

            <h2 class="box-title">Requests I Received</h2>

            <?php
            $stmt = $conn->prepare("SELECT r.*, u.name, u.university,
                                           p.post_status, p.user_id AS post_owner,
                                           s1.skill_name AS offer_skill,
                                           s2.skill_name AS want_skill
                                    FROM requests r
                                    JOIN posts p   ON r.post_id = p.post_id
                                    JOIN users u   ON r.user_id = u.user_id
                                    JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                    JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                    WHERE p.user_id = ?
                                    ORDER BY r.request_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<div class='empty'>Nobody sent you a request yet. <a href='post-exchange.php'>Post your offer</a></div>";
            }

            while ($request = $result->fetch_assoc()) {

                $student_rating = getAverageRating($conn, $request["user_id"]);
                ?>

                <div class="item">

                    <h3><a href="student-profile.php?id=<?php echo $request["user_id"]; ?>"><?php echo htmlspecialchars($request["name"]); ?></a></h3>

                    <p class="item-info"><?php echo htmlspecialchars($request["university"]); ?>
                        &nbsp; | &nbsp; <?php echo showStars($student_rating); ?> <?php echo number_format($student_rating, 1); ?>
                        &nbsp; | &nbsp; <?php echo showDate($request["created_at"]); ?></p>

                    <p>
                        <span class="tag tag-blue">My post : <?php echo htmlspecialchars($request["offer_skill"]); ?>
                            for <?php echo htmlspecialchars($request["want_skill"]); ?></span>
                        <?php echo showStatus($request["status"]); ?>
                    </p>

                    <p class="item-text"><?php echo htmlspecialchars($request["message"]); ?></p>

                    <div class="buttons">

                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $request["user_id"]; ?>'">Message</button>

                        <?php if ($request["status"] == "pending") { ?>
                            <button class="btn-small btn-green" onclick="if (confirm('Do you want to accept this exchange?')) { window.location.href='exchange-requests.php?action=accept&id=<?php echo $request["request_id"]; ?>'; }">Accept</button>
                            <button class="btn-small btn-red" onclick="if (confirm('Do you want to reject this request?')) { window.location.href='exchange-requests.php?action=reject&id=<?php echo $request["request_id"]; ?>'; }">Reject</button>
                        <?php } ?>

                        <?php if ($request["status"] == "accepted" && $request["post_status"] != "done") { ?>
                            <button class="btn-small" onclick="window.location.href='meetings.php?to=<?php echo $request["user_id"]; ?>'">Arrange Meeting</button>
                            <button class="btn-small btn-green" onclick="if (confirm('Is this skill exchange complete?')) { window.location.href='exchange-requests.php?action=finish&id=<?php echo $request["request_id"]; ?>'; }">Exchange is Complete</button>
                        <?php } ?>

                        <?php if ($request["status"] == "accepted" && $request["post_status"] == "done") { ?>
                            <button class="btn-small" onclick="window.location.href='exchange-requests.php?review=<?php echo $request["user_id"]; ?>'">Give Review</button>
                        <?php } ?>

                    </div>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

        <!-- ---------- Requests I sent ---------- -->

        <div class="box">

            <h2 class="box-title">Requests I Sent</h2>

            <?php
            $stmt = $conn->prepare("SELECT r.*, u.name, u.university,
                                           p.post_status, p.user_id AS post_owner,
                                           s1.skill_name AS offer_skill,
                                           s2.skill_name AS want_skill
                                    FROM requests r
                                    JOIN posts p   ON r.post_id = p.post_id
                                    JOIN users u   ON p.user_id = u.user_id
                                    JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                    JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                    WHERE r.user_id = ?
                                    ORDER BY r.request_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<div class='empty'>You did not send any request yet. <a href='exchange.php'>Browse exchange posts</a></div>";
            }

            while ($request = $result->fetch_assoc()) {
                ?>

                <div class="item item-green">

                    <h3><a href="student-profile.php?id=<?php echo $request["post_owner"]; ?>"><?php echo htmlspecialchars($request["name"]); ?></a></h3>

                    <p class="item-info">Sent on <?php echo showDate($request["created_at"]); ?></p>

                    <p>
                        <span class="tag tag-blue">Can teach : <?php echo htmlspecialchars($request["offer_skill"]); ?></span>
                        <span class="tag tag-orange">Wants to learn : <?php echo htmlspecialchars($request["want_skill"]); ?></span>
                        <?php echo showStatus($request["status"]); ?>
                    </p>

                    <p class="item-text"><?php echo htmlspecialchars($request["message"]); ?></p>

                    <div class="buttons">

                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $request["post_owner"]; ?>'">Message</button>

                        <?php if ($request["status"] == "pending") { ?>
                            <button class="btn-small btn-red" onclick="if (confirm('Do you want to cancel this request?')) { window.location.href='exchange-requests.php?action=cancel&id=<?php echo $request["request_id"]; ?>'; }">Cancel Request</button>
                        <?php } ?>

                        <?php if ($request["status"] == "accepted" && $request["post_status"] != "done") { ?>
                            <button class="btn-small" onclick="window.location.href='meetings.php?to=<?php echo $request["post_owner"]; ?>'">Arrange Meeting</button>
                            <button class="btn-small btn-green" onclick="if (confirm('Is this skill exchange complete?')) { window.location.href='exchange-requests.php?action=finish&id=<?php echo $request["request_id"]; ?>'; }">Exchange is Complete</button>
                        <?php } ?>

                        <?php if ($request["status"] == "accepted" && $request["post_status"] == "done") { ?>
                            <button class="btn-small" onclick="window.location.href='exchange-requests.php?review=<?php echo $request["post_owner"]; ?>'">Give Review</button>
                        <?php } ?>

                    </div>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

    </div>

<?php include "footer.php"; ?>
