<?php
session_start();
require_once "config.php";
require_once "functions.php";

$my_id = 0;

if (isset($_SESSION["user_id"])) {
    $my_id = $_SESSION["user_id"];
}

$errors = array();
$message = "";

// ============================================
// The exchange request form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $my_id > 0) {

    $post_id = intval($_POST["post_id"]);
    $message = trim($_POST["message"]);

    $post = getPost($conn, $post_id);

    if ($message == "") {
        $errors["message"] = "Please write a message.";
    }
    else if (strlen($message) < 15) {
        $errors["message"] = "Write at least 15 characters.";
    }

    if (count($errors) == 0 && $post != null) {

        $today = date("Y-m-d");

        $stmt = $conn->prepare("INSERT INTO requests (post_id, user_id, message, status, created_at)
                                VALUES (?, ?, ?, 'pending', ?)");
        $stmt->bind_param("iiss", $post_id, $my_id, $message, $today);
        $stmt->execute();
        $stmt->close();

        // the same message also starts the chat
        addMessage($conn, $my_id, $post["user_id"], $message);

        addNotification($conn, $post["user_id"],
            getUserName($conn, $my_id) . " sent you a skill exchange request.", "exchange-requests.php");

        addActivity($conn, $my_id,
            "Sent a skill exchange request to " . getUserName($conn, $post["user_id"]));

        header("Location: exchange-requests.php");
        exit();
    }
}

// ---------- the filters ----------

$search      = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$offer_skill = isset($_GET["offer"])  ? intval($_GET["offer"]) : 0;
$want_skill  = isset($_GET["want"])   ? intval($_GET["want"])  : 0;
$status      = isset($_GET["status"]) ? $_GET["status"] : "open";

$allowed_status = array("open", "matched", "done", "all");

if (!in_array($status, $allowed_status)) {
    $status = "open";
}

$sql = "SELECT p.*, u.name, u.university, u.city,
               s1.skill_name AS offer_skill,
               s2.skill_name AS want_skill
        FROM posts p
        JOIN users u   ON p.user_id = u.user_id
        JOIN skills s1 ON p.offer_skill_id = s1.skill_id
        JOIN skills s2 ON p.want_skill_id = s2.skill_id
        WHERE 1 = 1";

if ($status != "all") {
    $sql = $sql . " AND p.post_status = '" . $status . "'";
}

if ($offer_skill > 0) {
    $sql = $sql . " AND p.offer_skill_id = " . $offer_skill;
}

if ($want_skill > 0) {
    $sql = $sql . " AND p.want_skill_id = " . $want_skill;
}

if ($search != "") {
    $sql = $sql . " AND (p.description LIKE ? OR u.name LIKE ?)";
}

$sql = $sql . " ORDER BY p.post_id DESC";

$stmt = $conn->prepare($sql);

if ($search != "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("ss", $like, $like);
}

$stmt->execute();
$result = $stmt->get_result();
$total_found = $result->num_rows;

// ---------- the posts I already sent a request to ----------

$my_requests = array();

if ($my_id > 0) {

    $stmt2 = $conn->prepare("SELECT post_id FROM requests WHERE user_id = ?");
    $stmt2->bind_param("i", $my_id);
    $stmt2->execute();
    $sent = $stmt2->get_result();

    while ($row = $sent->fetch_assoc()) {
        $my_requests[] = $row["post_id"];
    }

    $stmt2->close();
}

// ---------- is the request form open? ----------

$request_post = isset($_GET["request"]) ? intval($_GET["request"]) : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && count($errors) > 0) {
    $request_post = intval($_POST["post_id"]);
}

$all_skills = getAllSkills($conn);

$page_title = "Skill Exchange";
include "header.php";
?>

    <div class="page">

        <h1>Skill Exchange</h1>
        <p class="page-info">Teach what you know and learn what you need. No money is needed here.</p>

        <!-- Search and filter -->

        <div class="box">

            <form method="GET" action="exchange.php">

                <div class="row">

                    <div class="col-half">
                        <label for="search">Search</label>
                        <input type="search" id="search" name="search" placeholder="Write a skill or a student name"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="offer">Skill offered</label>
                        <select id="offer" name="offer">
                            <option value="0">All skills</option>
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
                    </div>

                </div>

                <div class="row">

                    <div class="col-half">
                        <label for="want">Skill wanted</label>
                        <select id="want" name="want">
                            <option value="0">All skills</option>
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
                    </div>

                    <div class="col-half">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="open"    <?php if ($status == "open")    { echo "selected"; } ?>>Open posts</option>
                            <option value="matched" <?php if ($status == "matched") { echo "selected"; } ?>>Matched</option>
                            <option value="done"    <?php if ($status == "done")    { echo "selected"; } ?>>Completed</option>
                            <option value="all"     <?php if ($status == "all")     { echo "selected"; } ?>>All posts</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='exchange.php'">Clear</button>
                    <button type="button" class="btn-green" onclick="window.location.href='post-exchange.php'">Post My Offer</button>
                </div>

            </form>

        </div>

        <?php if ($request_post > 0 && $my_id > 0) {

            $chosen_post = getPost($conn, $request_post);

            if ($chosen_post != null) {
                ?>

                <!-- Request form -->

                <div class="box">

                    <h2 class="box-title">Send an Exchange Request</h2>

                    <form method="POST" action="exchange.php">

                        <p class="page-info">
                            <b><?php echo htmlspecialchars(getUserName($conn, $chosen_post["user_id"])); ?></b>
                            can teach you <b><?php echo htmlspecialchars($chosen_post["offer_skill"]); ?></b>
                            and wants to learn <b><?php echo htmlspecialchars($chosen_post["want_skill"]); ?></b>.
                        </p>

                        <input type="hidden" name="post_id" value="<?php echo $request_post; ?>">

                        <label for="message">Your Message</label>
                        <textarea id="message" name="message"
                                  placeholder="Write what you can teach, your level and when you are free."><?php echo htmlspecialchars($message); ?></textarea>
                        <small class="error"><?php if (isset($errors["message"])) { echo $errors["message"]; } ?></small>

                        <div class="buttons">
                            <button type="submit">Send Request</button>
                            <button type="button" class="btn-gray" onclick="window.location.href='exchange.php'">Cancel</button>
                        </div>

                    </form>

                </div>

                <?php
            }
        } ?>

        <p class="page-info"><?php echo $total_found; ?> exchange post(s) found</p>

        <?php
        if ($total_found == 0) {
            echo "<div class='empty'>No exchange post found. Please change the search or the filter.</div>";
        }

        while ($post = $result->fetch_assoc()) {
            ?>

            <div class="item item-green">

                <h3><a href="student-profile.php?id=<?php echo $post["user_id"]; ?>"><?php echo htmlspecialchars($post["name"]); ?></a></h3>

                <p class="item-info"><?php echo htmlspecialchars($post["university"]); ?>
                    &nbsp; | &nbsp; <?php echo htmlspecialchars($post["city"]); ?>
                    &nbsp; | &nbsp; <?php echo showDate($post["created_at"]); ?></p>

                <p>
                    <span class="tag tag-blue">Can teach : <?php echo htmlspecialchars($post["offer_skill"]); ?></span>
                    <span class="tag tag-orange">Wants to learn : <?php echo htmlspecialchars($post["want_skill"]); ?></span>
                    <?php echo showStatus($post["post_status"]); ?>
                </p>

                <p class="item-text"><?php echo htmlspecialchars($post["description"]); ?></p>

                <div class="buttons">

                    <?php if ($my_id == 0) { ?>

                        <button class="btn-small" onclick="window.location.href='login.php'">Login to send request</button>

                    <?php } else if ($post["user_id"] == $my_id) { ?>

                        <button class="btn-small btn-gray" onclick="window.location.href='exchange-requests.php'">This is my post</button>

                    <?php } else if (in_array($post["post_id"], $my_requests)) { ?>

                        <span class="tag tag-blue">You already sent a request</span>

                    <?php } else if ($post["post_status"] != "open") { ?>

                        <span class="tag tag-red">This post is closed</span>

                    <?php } else { ?>

                        <button class="btn-small" onclick="window.location.href='exchange.php?request=<?php echo $post["post_id"]; ?>'">Send Request</button>
                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $post["user_id"]; ?>'">Message</button>

                    <?php } ?>

                </div>

            </div>

            <?php
        }

        $stmt->close();
        ?>

    </div>

<?php include "footer.php"; ?>
