<?php
session_start();
require_once "config.php";
require_once "functions.php";

$errors = array();
$done = false;
$email = "";

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm  = $_POST["confirm"];

    // ---------- validation ----------

    if ($email == "") {
        $errors["email"] = "Email is required.";
    }
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Invalid email address.";
    }

    if ($password == "") {
        $errors["password"] = "New password is required.";
    }
    else if (strlen($password) < 6) {
        $errors["password"] = "Password must be at least 6 characters.";
    }

    if ($confirm != $password) {
        $errors["confirm"] = "The two passwords are not same.";
    }

    // ---------- find the account ----------

    if (count($errors) == 0) {

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user == null) {
            $errors["email"] = "No account found with this email.";
        }
        else {

            // ---------- update the password ----------

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user["user_id"]);
            $stmt->execute();
            $stmt->close();

            addActivity($conn, $user["user_id"], "Changed the account password");

            $done = true;
        }
    }
}

$page_title = "Reset Password";
include "header.php";
?>

    <div class="small-page">

        <div class="box">

            <h2 class="center">Reset Your Password</h2>
            <p class="page-info center">Write your email, then set a new password.</p>

            <?php if ($done) { ?>

                <div class="result">
                    <b>Your password is changed.</b><br>
                    You can login now with your new password.
                </div>

                <div class="buttons">
                    <button onclick="window.location.href='login.php'">Go to Login</button>
                </div>

            <?php } else { ?>

                <form method="POST" action="forgot.php" id="resetForm">

                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="example@kaajkoro.com"
                           value="<?php echo htmlspecialchars($email); ?>">
                    <small id="emailError" class="error"><?php if (isset($errors["email"])) { echo $errors["email"]; } ?></small>

                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters">
                    <small id="passwordError" class="error"><?php if (isset($errors["password"])) { echo $errors["password"]; } ?></small>

                    <label for="confirm">Confirm New Password</label>
                    <input type="password" id="confirm" name="confirm" placeholder="Write the new password again">
                    <small id="confirmError" class="error"><?php if (isset($errors["confirm"])) { echo $errors["confirm"]; } ?></small>

                    <button type="submit" class="btn-full">Update Password</button>

                </form>

                <p class="center" style="margin-top:15px">
                    <a href="login.php">Back to login</a>
                </p>

            <?php } ?>

        </div>

    </div>

<?php include "footer.php"; ?>
