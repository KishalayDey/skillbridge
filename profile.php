<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();
$saved = "";

// ---------- delete my account ----------

if (isset($_GET["action"]) && $_GET["action"] == "delete") {

    // all my jobs, posts, proposals and messages are deleted automatically
    // because of ON DELETE CASCADE in the database
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $stmt->close();

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

// ============================================
// One of the two forms was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["form_name"] == "profile") {

    $name       = trim($_POST["name"]);
    $email      = trim($_POST["email"]);
    $phone      = trim($_POST["phone"]);
    $university = trim($_POST["university"]);
    $city       = $_POST["city"];
    $bio        = trim($_POST["bio"]);
    $education  = trim($_POST["education"]);
    $experience = trim($_POST["experience"]);
    $portfolio  = trim($_POST["portfolio"]);

    $picked_skills = isset($_POST["skill"]) ? $_POST["skill"] : array();

    // ---------- validation ----------

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

        // another account cannot have this email
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $my_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $errors["email"] = "Another account is using this email.";
        }

        $stmt->close();
    }

    if (!preg_match("/^01[3-9][0-9]{2}-?[0-9]{6}$/", $phone)) {
        $errors["phone"] = "Write a valid number like 01711-223344.";
    }

    if ($university == "") {
        $errors["university"] = "University is required.";
    }

    if (strlen($bio) < 15) {
        $errors["bio"] = "Write at least 15 characters about yourself.";
    }

    if (count($picked_skills) == 0) {
        $errors["skill"] = "Select at least one skill.";
    }

    // ---------- save ----------

    if (count($errors) == 0) {

        $stmt = $conn->prepare("UPDATE users
                                SET name = ?, email = ?, phone = ?, university = ?, city = ?,
                                    bio = ?, education = ?, experience = ?, portfolio = ?
                                WHERE user_id = ?");
        $stmt->bind_param("sssssssssi", $name, $email, $phone, $university, $city,
                                        $bio, $education, $experience, $portfolio, $my_id);
        $stmt->execute();
        $stmt->close();

        // delete the old skills and save the new ones
        $stmt = $conn->prepare("DELETE FROM user_skills WHERE user_id = ?");
        $stmt->bind_param("i", $my_id);
        $stmt->execute();
        $stmt->close();

        foreach ($picked_skills as $skill_id) {
            $stmt = $conn->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $my_id, $skill_id);
            $stmt->execute();
            $stmt->close();
        }

        addActivity($conn, $my_id, "Updated profile information");

        $_SESSION["user_name"] = $name;

        // read the new information from the database
        $me = getUser($conn, $my_id);
        $saved = "Your profile is updated.";
    }
}

// ---------- change password ----------

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["form_name"] == "password") {

    $old_password = $_POST["old_password"];
    $new_password = $_POST["new_password"];
    $confirm      = $_POST["confirm_password"];

    if (!password_verify($old_password, $me["password"])) {
        $errors["old_password"] = "Your current password is wrong.";
    }

    if (strlen($new_password) < 6) {
        $errors["new_password"] = "New password must be at least 6 characters.";
    }
    else if (password_verify($new_password, $me["password"])) {
        $errors["new_password"] = "The new password must be different.";
    }

    if ($confirm != $new_password) {
        $errors["confirm_password"] = "The two passwords are not same.";
    }

    if (count($errors) == 0) {

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $my_id);
        $stmt->execute();
        $stmt->close();

        addActivity($conn, $my_id, "Changed the account password");

        $me = getUser($conn, $my_id);
        $saved = "Your password is changed.";
    }
}

// ---------- the information shown in the form ----------

$my_skill_ids = array();

$stmt = $conn->prepare("SELECT skill_id FROM user_skills WHERE user_id = ?");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $my_skill_ids[] = $row["skill_id"];
}

$stmt->close();

$all_skills = getAllSkills($conn);
$rating = getAverageRating($conn, $my_id);

$job_posted = countRows($conn, "SELECT COUNT(*) AS total FROM jobs WHERE client_id = ?", $my_id);
$job_done   = countRows($conn, "SELECT COUNT(*) AS total FROM jobs WHERE hired_id = ? AND job_status = 'done'", $my_id);
$proposal_count = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ?", $my_id);

$page_title = "My Profile";
include "header.php";
?>

    <div class="page">

        <h1>My Profile</h1>
        <p class="page-info">This information is shown to the clients and to the other students.</p>

        <?php if ($saved != "") { ?>
            <div class="result"><b><?php echo $saved; ?></b></div>
        <?php } ?>

        <div class="row">

            <!-- Left side : the forms -->

            <div class="col-left">

                <div class="box">

                    <h2 class="box-title">Profile Information</h2>

                    <form method="POST" action="profile.php">

                        <input type="hidden" name="form_name" value="profile">

                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($me["name"]); ?>">
                        <small class="error"><?php if (isset($errors["name"])) { echo $errors["name"]; } ?></small>

                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($me["email"]); ?>">
                        <small class="error"><?php if (isset($errors["email"])) { echo $errors["email"]; } ?></small>

                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($me["phone"]); ?>">
                        <small class="error"><?php if (isset($errors["phone"])) { echo $errors["phone"]; } ?></small>

                        <label for="university">University</label>
                        <input type="text" id="university" name="university" value="<?php echo htmlspecialchars($me["university"]); ?>">
                        <small class="error"><?php if (isset($errors["university"])) { echo $errors["university"]; } ?></small>

                        <label for="city">City</label>

                        <select id="city" name="city">
                            <?php
                            $city_list = array("Dhaka", "Chattogram", "Khulna", "Rajshahi",
                                               "Sylhet", "Barishal", "Rangpur", "Savar");

                            foreach ($city_list as $one_city) {
                                if ($me["city"] == $one_city) {
                                    echo "<option value='" . $one_city . "' selected>" . $one_city . "</option>";
                                }
                                else {
                                    echo "<option value='" . $one_city . "'>" . $one_city . "</option>";
                                }
                            }
                            ?>
                        </select>

                        <label for="bio">Short Bio</label>
                        <textarea id="bio" name="bio"><?php echo htmlspecialchars($me["bio"]); ?></textarea>
                        <small class="error"><?php if (isset($errors["bio"])) { echo $errors["bio"]; } ?></small>

                        <label for="education">Education</label>
                        <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($me["education"]); ?>">

                        <label for="experience">Experience</label>
                        <textarea id="experience" name="experience"><?php echo htmlspecialchars($me["experience"]); ?></textarea>

                        <label for="portfolio">Portfolio Link</label>
                        <input type="text" id="portfolio" name="portfolio" value="<?php echo htmlspecialchars($me["portfolio"]); ?>"
                               placeholder="Example : behance.net/yourname">

                        <label>My Skills</label>

                        <div class="check-group">
                            <?php
                            foreach ($all_skills as $skill) {
                                if (in_array($skill["skill_id"], $my_skill_ids)) {
                                    echo "<label><input type='checkbox' name='skill[]' value='" . $skill["skill_id"] . "' checked>" . $skill["skill_name"] . "</label>";
                                }
                                else {
                                    echo "<label><input type='checkbox' name='skill[]' value='" . $skill["skill_id"] . "'>" . $skill["skill_name"] . "</label>";
                                }
                            }
                            ?>
                        </div>

                        <small class="error"><?php if (isset($errors["skill"])) { echo $errors["skill"]; } ?></small>

                        <div class="buttons">
                            <button type="submit">Save Changes</button>
                            <button type="button" class="btn-gray" onclick="window.location.href='student-profile.php?id=<?php echo $my_id; ?>'">See My Public Profile</button>
                        </div>

                    </form>

                </div>

                <div class="box">

                    <h2 class="box-title">Change Password</h2>

                    <form method="POST" action="profile.php">

                        <input type="hidden" name="form_name" value="password">

                        <label for="old_password">Current Password</label>
                        <input type="password" id="old_password" name="old_password">
                        <small class="error"><?php if (isset($errors["old_password"])) { echo $errors["old_password"]; } ?></small>

                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password">
                        <small class="error"><?php if (isset($errors["new_password"])) { echo $errors["new_password"]; } ?></small>

                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                        <small class="error"><?php if (isset($errors["confirm_password"])) { echo $errors["confirm_password"]; } ?></small>

                        <div class="buttons">
                            <button type="submit">Update Password</button>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Right side -->

            <div class="col-right">

                <div class="box">

                    <h2 class="box-title">My Summary</h2>

                    <h3><?php echo htmlspecialchars($me["name"]); ?></h3>
                    <p><?php echo showStars($rating); ?> <?php echo number_format($rating, 1); ?>
                        ( <?php echo countReviews($conn, $my_id); ?> reviews )</p>

                    <table style="margin-top:15px">
                        <tr><th>Member since</th><td><?php echo showDate($me["joined"]); ?></td></tr>
                        <tr><th>Jobs posted</th><td><?php echo $job_posted; ?></td></tr>
                        <tr><th>Jobs completed</th><td><?php echo $job_done; ?></td></tr>
                        <tr><th>Proposals sent</th><td><?php echo $proposal_count; ?></td></tr>
                        <tr><th>Account status</th><td><?php echo showStatus($me["status"]); ?></td></tr>
                    </table>

                </div>

                <div class="box">

                    <h2 class="box-title">Delete Account</h2>

                    <p class="item-text">If you delete your account, your jobs, posts, proposals and
                        messages will also be deleted from the database.</p>

                    <button class="btn-red" onclick="if (confirm('Do you really want to delete your account? This cannot be undone.')) { window.location.href='profile.php?action=delete'; }">Delete My Account</button>

                </div>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
