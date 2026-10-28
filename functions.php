<?php
// ============================================
// KaajKoro - Common functions
// This file is included in every page (like main.js in the first part).
// ============================================


// ---------- Find one user by id ----------

function getUser($conn, $user_id) {

    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    return $user;
}


// ---------- Only the name of a user ----------

function getUserName($conn, $user_id) {

    $user = getUser($conn, $user_id);

    if ($user == null) {
        return "Deleted user";
    }

    return $user["name"];
}


// ---------- Find one job ----------

function getJob($conn, $job_id) {

    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $job = $result->fetch_assoc();

    $stmt->close();

    return $job;
}


// ---------- Find one exchange post ----------

function getPost($conn, $post_id) {

    $stmt = $conn->prepare("SELECT p.*, s1.skill_name AS offer_skill, s2.skill_name AS want_skill
                            FROM posts p
                            JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                            JOIN skills s2 ON p.want_skill_id = s2.skill_id
                            WHERE p.post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $post = $result->fetch_assoc();

    $stmt->close();

    return $post;
}


// ---------- All the skills of one user ----------

function getUserSkills($conn, $user_id) {

    $list = array();

    $stmt = $conn->prepare("SELECT s.skill_name
                            FROM user_skills u
                            JOIN skills s ON u.skill_id = s.skill_id
                            WHERE u.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $list[] = $row["skill_name"];
    }

    $stmt->close();

    return $list;
}


// ---------- All the skills needed by one job ----------

function getJobSkills($conn, $job_id) {

    $list = array();

    $stmt = $conn->prepare("SELECT s.skill_name
                            FROM job_skills j
                            JOIN skills s ON j.skill_id = s.skill_id
                            WHERE j.job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $list[] = $row["skill_name"];
    }

    $stmt->close();

    return $list;
}


// ---------- The full skill list for the dropdowns ----------

function getAllSkills($conn) {

    $list = array();

    $result = $conn->query("SELECT * FROM skills ORDER BY skill_id");

    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }

    return $list;
}


// ---------- Average rating (the derived attribute of our ER diagram) ----------

function getAverageRating($conn, $user_id) {

    $stmt = $conn->prepare("SELECT AVG(rating) AS average
                            FROM reviews
                            WHERE to_id = ? AND status = 'visible'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    if ($row["average"] == null) {
        return 0;
    }

    return $row["average"];
}


// ---------- How many reviews one user has ----------

function countReviews($conn, $user_id) {

    $stmt = $conn->prepare("SELECT COUNT(*) AS total
                            FROM reviews
                            WHERE to_id = ? AND status = 'visible'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return $row["total"];
}


// ---------- Count anything with one small query ----------

function countRows($conn, $sql, $value) {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $value);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return $row["total"];
}


// ---------- Star line like  4 filled stars and 1 empty star ----------

function showStars($rating) {

    $stars = "";
    $full = round($rating);

    for ($i = 1; $i <= 5; $i++) {

        if ($i <= $full) {
            $stars = $stars . "&#9733;";
        }
        else {
            $stars = $stars . "&#9734;";
        }
    }

    return "<span class='stars'>" . $stars . "</span>";
}


// ---------- Money like  Tk 3,500 ----------

function showMoney($amount) {

    return "Tk " . number_format($amount);
}


// ---------- A coloured tag for any status ----------

function showStatus($status) {

    if ($status == "open" || $status == "accepted" || $status == "active"
        || $status == "solved" || $status == "visible" || $status == "done") {

        return "<span class='tag tag-green'>" . $status . "</span>";
    }

    if ($status == "pending" || $status == "working" || $status == "matched"
        || $status == "scheduled") {

        return "<span class='tag tag-orange'>" . $status . "</span>";
    }

    return "<span class='tag tag-red'>" . $status . "</span>";
}


// ---------- Save a notification for one user ----------

function addNotification($conn, $user_id, $text, $link) {

    $now = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, text, link, created_at)
                            VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $text, $link, $now);
    $stmt->execute();
    $stmt->close();
}


// ---------- Save one line in the activity log ----------

function addActivity($conn, $user_id, $text) {

    $now = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO activities (user_id, text, created_at)
                            VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $text, $now);
    $stmt->execute();
    $stmt->close();
}


// ---------- Save one message ----------

function addMessage($conn, $from_id, $to_id, $text) {

    $now = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO messages (from_id, to_id, text, sent_at)
                            VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $from_id, $to_id, $text, $now);
    $stmt->execute();
    $stmt->close();
}


// ---------- Short date like  14 Aug 2026 ----------

function showDate($date) {

    if ($date == null || $date == "0000-00-00") {
        return "-";
    }

    return date("d M Y", strtotime($date));
}


// ---------- Date with time ----------

function showDateTime($date) {

    if ($date == null) {
        return "-";
    }

    return date("d M Y, g:i A", strtotime($date));
}
?>
