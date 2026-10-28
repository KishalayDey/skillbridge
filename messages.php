<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$error = "";

// ---------- who am I chatting with ----------

$chat_with = isset($_GET["to"]) ? intval($_GET["to"]) : 0;

// ============================================
// A message was sent
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $chat_with = intval($_POST["to_id"]);
    $text = trim($_POST["text"]);

    if ($text == "") {
        $error = "Please write a message.";
    }
    else {

        addMessage($conn, $my_id, $chat_with, $text);

        addNotification($conn, $chat_with,
            getUserName($conn, $my_id) . " sent you a message.", "messages.php?to=" . $my_id);

        header("Location: messages.php?to=" . $chat_with);
        exit();
    }
}

// ---------- all the people I talked with ----------

$stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.name, u.university
                        FROM users u
                        JOIN messages m ON (u.user_id = m.from_id OR u.user_id = m.to_id)
                        WHERE (m.from_id = ? OR m.to_id = ?) AND u.user_id != ?
                        ORDER BY u.name");
$stmt->bind_param("iii", $my_id, $my_id, $my_id);
$stmt->execute();
$people = $stmt->get_result();

$people_list = array();

while ($row = $people->fetch_assoc()) {
    $people_list[] = $row;
}

$stmt->close();

// the person I opened from another page may not be in the list yet
$found_in_list = false;

foreach ($people_list as $person) {
    if ($person["user_id"] == $chat_with) {
        $found_in_list = true;
    }
}

if ($chat_with > 0 && $found_in_list == false) {

    $new_person = getUser($conn, $chat_with);

    if ($new_person != null) {
        $people_list[] = $new_person;
    }
}

$page_title = "Messages";
include "header.php";
?>

    <div class="page">

        <h1>Messages</h1>
        <p class="page-info">Talk with the clients and the students before starting any work.</p>

        <div class="row">

            <!-- Left : the people list -->

            <div style="width:32%">

                <label for="newPerson">Start a new chat</label>

                <select id="newPerson" onchange="if (this.value != '') { window.location.href = 'messages.php?to=' + this.value; }">
                    <option value="">-- Select a student --</option>
                    <?php
                    $all_users = $conn->query("SELECT user_id, name FROM users WHERE status = 'active' ORDER BY name");

                    while ($one = $all_users->fetch_assoc()) {

                        if ($one["user_id"] != $my_id) {
                            echo "<option value='" . $one["user_id"] . "'>" . htmlspecialchars($one["name"]) . "</option>";
                        }
                    }
                    ?>
                </select>

                <div class="chat-list" style="margin-top:12px">

                    <?php
                    if (count($people_list) == 0) {
                        echo "<div class='empty'>No chat yet. Select a student from the box above.</div>";
                    }

                    foreach ($people_list as $person) {

                        // the last message with this person
                        $stmt = $conn->prepare("SELECT text FROM messages
                                                WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?)
                                                ORDER BY message_id DESC LIMIT 1");
                        $stmt->bind_param("iiii", $my_id, $person["user_id"], $person["user_id"], $my_id);
                        $stmt->execute();
                        $last = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $last_text = "No message yet";

                        if ($last != null) {
                            $last_text = $last["text"];

                            if (strlen($last_text) > 40) {
                                $last_text = substr($last_text, 0, 40) . "...";
                            }
                        }

                        $active_class = "";

                        if ($person["user_id"] == $chat_with) {
                            $active_class = "chat-active";
                        }
                        ?>

                        <div class="chat-person <?php echo $active_class; ?>"
                             onclick="window.location.href='messages.php?to=<?php echo $person["user_id"]; ?>'">
                            <b><?php echo htmlspecialchars($person["name"]); ?></b><br>
                            <span class="gray-text"><?php echo htmlspecialchars($last_text); ?></span>
                        </div>

                        <?php
                    }
                    ?>

                </div>

            </div>

            <!-- Right : the chat -->

            <div style="width:68%">

                <div class="box" style="padding:15px">

                    <?php if ($chat_with == 0) { ?>

                        <h3>Select a person</h3>
                        <div class="chat-box" style="margin-top:10px">
                            <div class="empty">Select a person from the left side to see the messages.</div>
                        </div>

                    <?php } else {

                        $other = getUser($conn, $chat_with);
                        ?>

                        <h3>
                            <a href="student-profile.php?id=<?php echo $chat_with; ?>"><?php echo htmlspecialchars($other["name"]); ?></a>
                            <span class="gray-text">(<?php echo htmlspecialchars($other["university"]); ?>)</span>
                        </h3>

                        <!-- the messages are written here by PHP, and AJAX keeps them fresh -->

                        <div class="chat-box" id="chatBox" style="margin-top:10px">

                            <?php
                            $stmt = $conn->prepare("SELECT * FROM messages
                                                    WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?)
                                                    ORDER BY message_id");
                            $stmt->bind_param("iiii", $my_id, $chat_with, $chat_with, $my_id);
                            $stmt->execute();
                            $chat = $stmt->get_result();

                            if ($chat->num_rows == 0) {
                                echo "<div class='empty'>No message yet. Write the first message.</div>";
                            }

                            while ($message = $chat->fetch_assoc()) {

                                if ($message["from_id"] == $my_id) {
                                    ?>
                                    <div class="msg-out">
                                        <?php echo htmlspecialchars($message["text"]); ?>
                                        <span class="msg-time"><?php echo showDateTime($message["sent_at"]); ?></span>
                                    </div>
                                    <?php
                                }
                                else {
                                    ?>
                                    <div class="msg-in">
                                        <b><?php echo htmlspecialchars($other["name"]); ?></b><br>
                                        <?php echo htmlspecialchars($message["text"]); ?>
                                        <span class="msg-time"><?php echo showDateTime($message["sent_at"]); ?></span>
                                    </div>
                                    <?php
                                }
                            }

                            $stmt->close();
                            ?>

                        </div>

                        <form method="POST" action="messages.php" style="margin-top:12px">

                            <input type="hidden" name="to_id" value="<?php echo $chat_with; ?>">

                            <textarea id="text" name="text" placeholder="Write your message here" style="height:70px"></textarea>
                            <small class="error"><?php echo $error; ?></small>

                            <div class="buttons">
                                <button type="submit">Send Message</button>
                                <button type="button" class="btn-gray" onclick="window.location.href='meetings.php?to=<?php echo $chat_with; ?>'">Arrange a Meeting</button>
                            </div>

                        </form>

                    <?php } ?>

                </div>

            </div>

        </div>

    </div>

    <?php if ($chat_with > 0) { ?>

        <script>
            // the id of the other person, used by the AJAX code
            let chatWith = <?php echo $chat_with; ?>;
            let myId = <?php echo $my_id; ?>;
        </script>

        <script src="messages.js"></script>

    <?php } ?>

<?php include "footer.php"; ?>
