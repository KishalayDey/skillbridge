<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_admin.php";

$errors = array();

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id     = isset($_GET["id"])     ? intval($_GET["id"]) : 0;

// ---------- suspend a user ----------

if ($action == "suspend" && $id > 0) {

    $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    addNotification($conn, $id, "Your account is suspended by the admin.", "index.php");

    header("Location: admin-users.php");
    exit();
}

// ---------- make a user active again ----------

if ($action == "activate" && $id > 0) {

    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    addNotification($conn, $id, "Your account is active again. Welcome back.", "dashboard.php");

    header("Location: admin-users.php");
    exit();
}

// ---------- remove a user ----------

if ($action == "remove" && $id > 0) {

    // the jobs, posts, proposals and messages of this user are deleted
    // automatically because of ON DELETE CASCADE
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-users.php");
    exit();
}

// ============================================
// The edit form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $edit_id    = intval($_POST["user_id"]);
    $name       = trim($_POST["name"]);
    $email      = trim($_POST["email"]);
    $university = trim($_POST["university"]);
    $status     = $_POST["status"];

    if ($name == "") {
        $errors["name"] = "Name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Invalid email address.";
    }
    else {

        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $edit_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $errors["email"] = "Another account is using this email.";
        }

        $stmt->close();
    }

    if ($university == "") {
        $errors["university"] = "University is required.";
    }

    if (count($errors) == 0) {

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, university = ?, status = ?
                                WHERE user_id = ?");
        $stmt->bind_param("ssssi", $name, $email, $university, $status, $edit_id);
        $stmt->execute();
        $stmt->close();

        header("Location: admin-users.php");
        exit();
    }
}

// ---------- is the edit form open? ----------

$edit_user = null;

if ($action == "edit" && $id > 0) {
    $edit_user = getUser($conn, $id);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && count($errors) > 0) {
    $edit_user = getUser($conn, intval($_POST["user_id"]));
}

// ---------- the search ----------

$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$status_filter = isset($_GET["status"]) ? $_GET["status"] : "";

$sql = "SELECT * FROM users WHERE 1 = 1";

if ($status_filter == "active" || $status_filter == "suspended") {
    $sql = $sql . " AND status = '" . $status_filter . "'";
}

if ($search != "") {
    $sql = $sql . " AND (name LIKE ? OR email LIKE ? OR university LIKE ?)";
}

$sql = $sql . " ORDER BY user_id";

$stmt = $conn->prepare($sql);

if ($search != "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("sss", $like, $like, $like);
}

$stmt->execute();
$result = $stmt->get_result();

$page_title = "Manage Users";
include "header.php";
?>

    <div class="page">

        <h1>Manage Users</h1>
        <p class="page-info">See, edit, suspend or remove the student accounts.</p>

        <?php if ($edit_user != null) { ?>

            <div class="box">

                <h2 class="box-title">Edit User</h2>

                <form method="POST" action="admin-users.php">

                    <input type="hidden" name="user_id" value="<?php echo $edit_user["user_id"]; ?>">

                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_user["name"]); ?>">
                    <small class="error"><?php if (isset($errors["name"])) { echo $errors["name"]; } ?></small>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_user["email"]); ?>">
                    <small class="error"><?php if (isset($errors["email"])) { echo $errors["email"]; } ?></small>

                    <label for="university">University</label>
                    <input type="text" id="university" name="university" value="<?php echo htmlspecialchars($edit_user["university"]); ?>">
                    <small class="error"><?php if (isset($errors["university"])) { echo $errors["university"]; } ?></small>

                    <label for="status">Account Status</label>

                    <select id="status" name="status">
                        <option value="active"    <?php if ($edit_user["status"] == "active")    { echo "selected"; } ?>>Active</option>
                        <option value="suspended" <?php if ($edit_user["status"] == "suspended") { echo "selected"; } ?>>Suspended</option>
                    </select>

                    <div class="buttons">
                        <button type="submit">Save Changes</button>
                        <button type="button" class="btn-gray" onclick="window.location.href='admin-users.php'">Cancel</button>
                    </div>

                </form>

            </div>

        <?php } ?>

        <div class="box">

            <form method="GET" action="admin-users.php">

                <div class="row">

                    <div class="col-half">
                        <label for="search">Search</label>
                        <input type="search" id="search" name="search" placeholder="Write a name, an email or a university"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value=""          <?php if ($status_filter == "")          { echo "selected"; } ?>>All users</option>
                            <option value="active"    <?php if ($status_filter == "active")    { echo "selected"; } ?>>Active only</option>
                            <option value="suspended" <?php if ($status_filter == "suspended") { echo "selected"; } ?>>Suspended only</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='admin-users.php'">Clear</button>
                </div>

            </form>

            <div style="margin-top:20px">

                <?php if ($result->num_rows == 0) { ?>

                    <div class="empty">No user found for this search.</div>

                <?php } else { ?>

                    <table>

                        <tr>
                            <th>Name</th>
                            <th>University</th>
                            <th>City</th>
                            <th>Skills</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php while ($user = $result->fetch_assoc()) {

                            $rating = getAverageRating($conn, $user["user_id"]);
                            $skills = getUserSkills($conn, $user["user_id"]);
                            ?>

                            <tr>
                                <td>
                                    <b><?php echo htmlspecialchars($user["name"]); ?></b><br>
                                    <span class="gray-text"><?php echo htmlspecialchars($user["email"]); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($user["university"]); ?></td>
                                <td><?php echo htmlspecialchars($user["city"]); ?></td>
                                <td><?php echo count($skills); ?></td>
                                <td>
                                    <?php echo showStars($rating); ?><br>
                                    <span class="gray-text"><?php echo number_format($rating, 1); ?>
                                        ( <?php echo countReviews($conn, $user["user_id"]); ?> )</span>
                                </td>
                                <td><?php echo showStatus($user["status"]); ?></td>
                                <td>
                                    <button class="btn-small btn-gray" onclick="window.location.href='student-profile.php?id=<?php echo $user["user_id"]; ?>'">View</button>
                                    <button class="btn-small" onclick="window.location.href='admin-users.php?action=edit&id=<?php echo $user["user_id"]; ?>'">Edit</button>

                                    <?php if ($user["status"] == "active") { ?>
                                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to suspend this user?')) { window.location.href='admin-users.php?action=suspend&id=<?php echo $user["user_id"]; ?>'; }">Suspend</button>
                                    <?php } else { ?>
                                        <button class="btn-small btn-green" onclick="window.location.href='admin-users.php?action=activate&id=<?php echo $user["user_id"]; ?>'">Activate</button>
                                    <?php } ?>

                                    <button class="btn-small btn-red" onclick="if (confirm('Remove this user permanently? All his jobs, posts and messages will be deleted.')) { window.location.href='admin-users.php?action=remove&id=<?php echo $user["user_id"]; ?>'; }">Remove</button>
                                </td>
                            </tr>

                        <?php } ?>

                    </table>

                <?php } ?>

            </div>

        </div>

    </div>

<?php
$stmt->close();
include "footer.php";
?>
