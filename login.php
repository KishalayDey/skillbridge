<?php
session_start();
require_once "config.php";
require_once "functions.php";

// somebody who is already logged in does not need this page
if (isset($_SESSION["user_logged_in"])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_SESSION["admin_logged_in"])) {
    header("Location: admin-dashboard.php");
    exit();
}

$errors = array();

// ---------- COOKIE : the email of the last login is remembered ----------

$email = "";

if (isset($_COOKIE["remember_email"])) {
    $email = $_COOKIE["remember_email"];
}

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    // ---------- validation ----------

    if ($email == "") {
        $errors["email"] = "Email is required.";
    }
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Invalid email address.";
    }

    if ($password == "") {
        $errors["password"] = "Password is required.";
    }

    if (count($errors) == 0) {

        // ---------- remember the email in a cookie for 30 days ----------

        if (isset($_POST["remember"])) {
            setcookie("remember_email", $email, time() + (30 * 24 * 60 * 60), "/");
        }
        else {
            // the box is not ticked, so delete the old cookie
            setcookie("remember_email", "", time() - 3600, "/");
        }

        // ---------- first check the admin table ----------

        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if ($admin != null && password_verify($password, $admin["password"])) {

            $_SESSION["admin_logged_in"] = true;
            $_SESSION["admin_id"]        = $admin["admin_id"];
            $_SESSION["admin_name"]      = $admin["name"];

            header("Location: admin-dashboard.php");
            exit();
        }

        // ---------- then check the student table ----------

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user == null) {
            $errors["email"] = "No account found with this email.";
        }
        else if (!password_verify($password, $user["password"])) {
            $errors["password"] = "Wrong password. Please try again.";
        }
        else if ($user["status"] == "suspended") {
            $errors["email"] = "This account is suspended by the admin.";
        }
        else {

            // ---------- SESSION : the login is successful ----------

            $_SESSION["user_logged_in"] = true;
            $_SESSION["user_id"]        = $user["user_id"];
            $_SESSION["user_name"]      = $user["name"];

            addActivity($conn, $user["user_id"], "Logged in to KaajKoro");

            header("Location: dashboard.php");
            exit();
        }
    }
}

$page_title = "Login";
include "header.php";
?>

    <div class="small-page">

        <div class="box">

            <h2 class="center">Login to KaajKoro</h2>

            <!--<div class="result">
                <b>Demo accounts</b><br>
                Student : rawan@kaajkoro.com &nbsp; password : 123456<br>
                Client : farhan@kaajkoro.com &nbsp; password : 123456<br>
                Admin : admin@kaajkoro.com &nbsp; password : admin123
            </div>-->

            <form method="POST" action="login.php" id="loginForm">

                <!-- Email -->

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="example@kaajkoro.com"
                       value="<?php echo htmlspecialchars($email); ?>">
                <small id="emailError" class="error"><?php if (isset($errors["email"])) { echo $errors["email"]; } ?></small>

                <!-- Password -->

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password">
                <small id="passwordError" class="error"><?php if (isset($errors["password"])) { echo $errors["password"]; } ?></small>

                <!-- Remember me : this makes the cookie -->

                <label>
                    <input type="checkbox" name="remember" style="width:auto"
                           <?php if ($email != "") { echo "checked"; } ?>>
                    Remember my email in this browser
                </label>

                <button type="submit" class="btn-full">Login</button>

            </form>

            <p class="center" style="margin-top:15px">
                <a href="forgot.php">Forgot password?</a> &nbsp; | &nbsp;
                <a href="register.php">Create a new account</a>
            </p>

        </div>

    </div>

    <script src="login.js"></script>

<?php include "footer.php"; ?>
