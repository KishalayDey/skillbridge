<?php
// ============================================
// This file is called by AJAX from register.js only.
// It checks the database and sends back JSON like:
// {"exists":true,"message":"This email is already registered."}
// ============================================

header('Content-Type: application/json');
require_once "config.php";

$email = isset($_GET["email"]) ? trim($_GET["email"]) : "";

$response = array("exists" => false, "message" => "");

if ($email != "") {

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $response["exists"]  = true;
        $response["message"] = "This email is already registered.";
    }

    $stmt->close();
}

echo json_encode($response);

$conn->close();
?>
