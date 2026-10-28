<?php
session_start();
require_once "config.php";
require_once "functions.php";

// a student who is already logged in does not need this page
if (isset($_SESSION["user_logged_in"])) {
    header("Location: dashboard.php");
    exit();
}

$errors = array();

// these keep the old input, so the form is not empty after an error
$name = "";
$email = "";
$phone = "";
$university = "";
$city = "";
$picked_skills = array();

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ---------- collect the input ----------

    $name       = trim($_POST["name"]);
    $email      = trim($_POST["email"]);
    $phone      = trim($_POST["phone"]);
    $university = trim($_POST["university"]);
    $city       = $_POST["city"];
    $password   = $_POST["password"];
    $confirm    = $_POST["confirm"];

    if (isset($_POST["skill"])) {
        $picked_skills = $_POST["skill"];
    }

    // ---------- server side validation ----------

    if ($name == "") {
        $errors["name"] = "Name is required.";
    }
    else if (!preg_match("/^[A-Za-z ]+$/", $name)) {
        $errors["name"] = "Only letters are allowed in the name.";
    }

    if ($email == "") {
        $errors["email"] = "Email is required.";
    }
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Invalid email address.";
    }
    else {

        // is this email already in the database?
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors["email"] = "This email is already registered.";
        }

        $stmt->close();
    }

    if ($phone == "") {
        $errors["phone"] = "Phone number is required.";
    }
    else if (!preg_match("/^01[3-9][0-9]{2}-?[0-9]{6}$/", $phone)) {
        $errors["phone"] = "Write a valid number like 01711-223344.";
    }

    if ($university == "") {
        $errors["university"] = "University name is required.";
    }

    if ($city == "") {
        $errors["city"] = "Please select your city.";
    }

    if (count($picked_skills) == 0) {
        $errors["skill"] = "Select at least one skill.";
    }

    if ($password == "") {
        $errors["password"] = "Password is required.";
    }
    else if (strlen($password) < 6) {
        $errors["password"] = "Password must be at least 6 characters.";
    }

    if ($confirm != $password) {
        $errors["confirm"] = "The two passwords are not same.";
    }

    if (!isset($_POST["rules"])) {
        $errors["rules"] = "Please accept the rules.";
    }

    // ---------- if everything is correct, save the new user ----------

    if (count($errors) == 0) {

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $today = date("Y-m-d");

        $stmt = $conn->prepare("INSERT INTO users
            (name, email, password, phone, university, city, bio, education, experience, portfolio, status, joined)
            VALUES (?, ?, ?, ?, ?, ?, '', '', '', '', 'active', ?)");

        $stmt->bind_param("sssssss", $name, $email, $hashed_password, $phone, $university, $city, $today);
        $stmt->execute();

        $new_user_id = $conn->insert_id;      // the id MySQL created for us
        $stmt->close();

        // save the ticked skills in the junction table
        foreach ($picked_skills as $skill_id) {

            $stmt = $conn->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $new_user_id, $skill_id);
            $stmt->execute();
            $stmt->close();
        }

        addActivity($conn, $new_user_id, "Created a KaajKoro account");
        addNotification($conn, $new_user_id, "Welcome to KaajKoro. Please complete your profile.", "profile.php");

        // ---------- SESSION : log the new student in ----------

        $_SESSION["user_logged_in"] = true;
        $_SESSION["user_id"]        = $new_user_id;
        $_SESSION["user_name"]      = $name;

        header("Location: dashboard.php");
        exit();
    }
}

$all_skills = getAllSkills($conn);

$page_title = "Register";
include "header.php";
?>

    <div class="small-page">

        <div class="box">

            <h2 class="center">Create Your Account</h2>

            <form method="POST" action="register.php" id="registerForm">

                <!-- Name -->

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Enter your full name"
                       value="<?php echo htmlspecialchars($name); ?>">
                <small id="nameError" class="error"><?php if (isset($errors["name"])) { echo $errors["name"]; } ?></small>

                <!-- Email -->

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="example@gmail.com"
                       value="<?php echo htmlspecialchars($email); ?>">
                <small id="emailError" class="error"><?php if (isset($errors["email"])) { echo $errors["email"]; } ?></small>

                <!-- Phone -->

                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="01711-223344"
                       value="<?php echo htmlspecialchars($phone); ?>">
                <small id="phoneError" class="error"><?php if (isset($errors["phone"])) { echo $errors["phone"]; } ?></small>

                <!-- University -->

                <label for="university">University</label>
                <input type="text" id="university" name="university" placeholder="Example : AIUB"
                       value="<?php echo htmlspecialchars($university); ?>">
                <small id="universityError" class="error"><?php if (isset($errors["university"])) { echo $errors["university"]; } ?></small>

                <!-- City -->

                <label for="city">City</label>

                <select id="city" name="city">
                    <option value="">-- Select your city --</option>
                    <?php
                    $city_list = array("Dhaka", "Chattogram", "Khulna", "Rajshahi",
                                       "Sylhet", "Barishal", "Rangpur", "Savar");

                    foreach ($city_list as $one_city) {

                        if ($city == $one_city) {
                            echo "<option value='" . $one_city . "' selected>" . $one_city . "</option>";
                        }
                        else {
                            echo "<option value='" . $one_city . "'>" . $one_city . "</option>";
                        }
                    }
                    ?>
                </select>

                <small id="cityError" class="error"><?php if (isset($errors["city"])) { echo $errors["city"]; } ?></small>

                <!-- Skills : one checkbox for every row of the skills table -->

                <label>Your Skills</label>

                <div class="check-group">
                    <?php
                    foreach ($all_skills as $skill) {

                        if (in_array($skill["skill_id"], $picked_skills)) {
                            echo "<label><input type='checkbox' name='skill[]' value='" . $skill["skill_id"] . "' checked>" . $skill["skill_name"] . "</label>";
                        }
                        else {
                            echo "<label><input type='checkbox' name='skill[]' value='" . $skill["skill_id"] . "'>" . $skill["skill_name"] . "</label>";
                        }
                    }
                    ?>
                </div>

                <small id="skillError" class="error"><?php if (isset($errors["skill"])) { echo $errors["skill"]; } ?></small>

                <!-- Password -->

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 6 characters">
                <small id="passwordError" class="error"><?php if (isset($errors["password"])) { echo $errors["password"]; } ?></small>

                <!-- Confirm Password -->

                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Write the password again">
                <small id="confirmError" class="error"><?php if (isset($errors["confirm"])) { echo $errors["confirm"]; } ?></small>

                <!-- Rules -->

                <label>
                    <input type="checkbox" id="rules" name="rules" style="width:auto">
                    I am a student and I agree to the rules of KaajKoro.
                </label>

                <small id="rulesError" class="error"><?php if (isset($errors["rules"])) { echo $errors["rules"]; } ?></small>

                <button type="submit" class="btn-full">Register</button>

            </form>

            <p class="center" style="margin-top:15px">
                Already have an account? <a href="login.php">Login here</a>
            </p>

        </div>

    </div>

    <script src="register.js"></script>

<?php include "footer.php"; ?>
