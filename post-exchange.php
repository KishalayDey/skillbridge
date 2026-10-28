<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();

// ---------- delete one of my posts ----------

if (isset($_GET["action"]) && $_GET["action"] == "delete") {

    $post_id = intval($_GET["id"]);

    // the WHERE also checks user_id, so I can only delete my own post.
    // the requests of this post are deleted automatically (ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: post-exchange.php");
    exit();
}

// ---------- are we editing an old post? ----------

$edit_id = isset($_GET["edit"]) ? intval($_GET["edit"]) : 0;

$offer_skill = 0;
$want_skill  = 0;
$description = "";
$post_status = "open";

if ($edit_id > 0) {

    $edit_post = getPost($conn, $edit_id);

    if ($edit_post == null || $edit_post["user_id"] != $my_id) {
        header("Location: exchange.php");
        exit();
    }

    $offer_skill = $edit_post["offer_skill_id"];
    $want_skill  = $edit_post["want_skill_id"];
    $description = $edit_post["description"];
    $post_status = $edit_post["post_status"];
}

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $offer_skill = intval($_POST["offer_skill"]);
    $want_skill  = intval($_POST["want_skill"]);
    $description = trim($_POST["description"]);

    if (isset($_POST["post_status"])) {
        $post_status = $_POST["post_status"];
    }

    // ---------- validation ----------

    if ($offer_skill == 0) {
        $errors["offer_skill"] = "Select the skill you can teach.";
    }

    if ($want_skill == 0) {
        $errors["want_skill"] = "Select the skill you want to learn.";
    }
    else if ($want_skill == $offer_skill) {
        $errors["want_skill"] = "Both skills cannot be the same.";
    }

    if ($description == "") {
        $errors["description"] = "Description is required.";
    }
    else if (strlen($description) < 30) {
        $errors["description"] = "Write at least 30 characters.";
    }

    // ---------- save ----------

    if (count($errors) == 0) {

        if ($edit_id > 0) {

            $stmt = $conn->prepare("UPDATE posts
                                    SET offer_skill_id = ?, want_skill_id = ?, description = ?, post_status = ?
                                    WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("iissii", $offer_skill, $want_skill, $description, $post_status, $edit_id, $my_id);
            $stmt->execute();
            $stmt->close();

            header("Location: exchange.php");
            exit();
        }
        else {

            $today = date("Y-m-d");

            $stmt = $conn->prepare("INSERT INTO posts (user_id, offer_skill_id, want_skill_id, description, post_status, created_at)
                                    VALUES (?, ?, ?, ?, 'open', ?)");
            $stmt->bind_param("iiiss", $my_id, $offer_skill, $want_skill, $description, $today);
            $stmt->execute();
            $stmt->close();

            addActivity($conn, $my_id, "Posted a skill exchange offer");

            // tell the students who know the skill I want to learn
            $stmt = $conn->prepare("SELECT user_id FROM user_skills WHERE skill_id = ? AND user_id != ?");
            $stmt->bind_param("ii", $want_skill, $my_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $want_skill_name = "";
            $skill_row = getAllSkills($conn);

            foreach ($skill_row as $one) {
                if ($one["skill_id"] == $want_skill) {
                    $want_skill_name = $one["skill_name"];
                }
            }

            while ($row = $result->fetch_assoc()) {
                addNotification($conn, $row["user_id"],
                    getUserName($conn, $my_id) . " is looking for someone who knows " . $want_skill_name . ".",
                    "exchange.php");
            }

            $stmt->close();

            header("Location: exchange.php");
            exit();
        }
    }
}

$all_skills = getAllSkills($conn);

$page_title = ($edit_id > 0) ? "Edit Exchange Post" : "Post a Skill Exchange";
include "header.php";
?>

    <div class="medium-page">

        <h1><?php echo ($edit_id > 0) ? "Edit Exchange Post" : "Post a Skill Exchange"; ?></h1>
        <p class="page-info">Write what you can teach and what you want to learn. No payment is needed.</p>

        <div class="box">

            <form method="POST" action="post-exchange.php<?php if ($edit_id > 0) { echo "?edit=" . $edit_id; } ?>" id="postForm">

                <label for="offer_skill">I Can Teach</label>

                <select id="offer_skill" name="offer_skill">
                    <option value="0">-- Select the skill you can teach --</option>
                    <?php
                    foreach ($all_skills as $skill) {
                        if ($offer_skill == $skill["skill_id"]) {
                            echo "<option value='" . $skill["skill_id"] . "' selected>" . $skill["skill_name"] . "</option>";
                        }
                        else {
                            echo "<option value='" . $skill["skill_id"] . "'>" . $skill["skill_name"] . "</option>";
                        }
                    }
                    ?>
                </select>

                <small class="error"><?php if (isset($errors["offer_skill"])) { echo $errors["offer_skill"]; } ?></small>

                <label for="want_skill">I Want To Learn</label>

                <select id="want_skill" name="want_skill">
                    <option value="0">-- Select the skill you want to learn --</option>
                    <?php
                    foreach ($all_skills as $skill) {
                        if ($want_skill == $skill["skill_id"]) {
                            echo "<option value='" . $skill["skill_id"] . "' selected>" . $skill["skill_name"] . "</option>";
                        }
                        else {
                            echo "<option value='" . $skill["skill_id"] . "'>" . $skill["skill_name"] . "</option>";
                        }
                    }
                    ?>
                </select>

                <small class="error"><?php if (isset($errors["want_skill"])) { echo $errors["want_skill"]; } ?></small>

                <label for="description">Description</label>
                <textarea id="description" name="description"
                          placeholder="Up to which level can you teach? How many classes? When are you free?"><?php echo htmlspecialchars($description); ?></textarea>
                <small class="error"><?php if (isset($errors["description"])) { echo $errors["description"]; } ?></small>

                <?php if ($edit_id > 0) { ?>

                    <label for="post_status">Post Status</label>

                    <select id="post_status" name="post_status">
                        <option value="open"    <?php if ($post_status == "open")    { echo "selected"; } ?>>Open</option>
                        <option value="matched" <?php if ($post_status == "matched") { echo "selected"; } ?>>Matched</option>
                        <option value="done"    <?php if ($post_status == "done")    { echo "selected"; } ?>>Completed</option>
                    </select>

                <?php } ?>

                <div class="buttons">
                    <button type="submit"><?php echo ($edit_id > 0) ? "Save Changes" : "Publish Post"; ?></button>
                    <button type="button" class="btn-gray" onclick="window.location.href='exchange.php'">Cancel</button>
                </div>

            </form>

        </div>

        <div class="box">

            <h2 class="box-title">My Exchange Posts</h2>

            <?php
            $stmt = $conn->prepare("SELECT p.*, s1.skill_name AS offer_skill, s2.skill_name AS want_skill
                                    FROM posts p
                                    JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                    JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                    WHERE p.user_id = ?
                                    ORDER BY p.post_id DESC");
            $stmt->bind_param("i", $my_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<div class='empty'>You did not post any skill exchange yet.</div>";
            }

            while ($post = $result->fetch_assoc()) {

                $request_count = countRows($conn, "SELECT COUNT(*) AS total FROM requests WHERE post_id = ?", $post["post_id"]);
                ?>

                <div class="item item-green">

                    <p>
                        <span class="tag tag-blue">Can teach : <?php echo htmlspecialchars($post["offer_skill"]); ?></span>
                        <span class="tag tag-orange">Wants to learn : <?php echo htmlspecialchars($post["want_skill"]); ?></span>
                        <?php echo showStatus($post["post_status"]); ?>
                    </p>

                    <p class="item-text"><?php echo htmlspecialchars($post["description"]); ?></p>

                    <p class="gray-text"><?php echo $request_count; ?> request(s)
                        &nbsp; | &nbsp; Posted on <?php echo showDate($post["created_at"]); ?></p>

                    <div class="buttons">
                        <button class="btn-small" onclick="window.location.href='post-exchange.php?edit=<?php echo $post["post_id"]; ?>'">Edit</button>
                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to delete this post?')) { window.location.href='post-exchange.php?action=delete&id=<?php echo $post["post_id"]; ?>'; }">Delete</button>
                        <button class="btn-small btn-gray" onclick="window.location.href='exchange-requests.php'">See Requests</button>
                    </div>

                </div>

                <?php
            }

            $stmt->close();
            ?>

        </div>

    </div>

<?php include "footer.php"; ?>
