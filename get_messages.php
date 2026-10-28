<?php
// ============================================
// This file is called by AJAX from messages.js.
// It sends all the messages between two students as JSON.
// ============================================

header('Content-Type: application/json');

session_start();
require_once "config.php";

// nobody can read the messages without login
if (!isset($_SESSION["user_logged_in"])) {
    echo json_encode(array());
    exit();
}

$my_id = $_SESSION["user_id"];
$other_id = isset($_GET["to"]) ? intval($_GET["to"]) : 0;

$list = array();

$stmt = $conn->prepare("SELECT * FROM messages
                        WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?)
                        ORDER BY message_id");
$stmt->bind_param("iiii", $my_id, $other_id, $other_id, $my_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $list[] = array(
        "from_id" => $row["from_id"],
        "text"    => $row["text"],
        "time"    => date("d M Y, g:i A", strtotime($row["sent_at"]))
    );
}

$stmt->close();

echo json_encode($list);

$conn->close();
?>
