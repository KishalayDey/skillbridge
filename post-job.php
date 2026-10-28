<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

$errors = array();

// ---------- are we editing an old job? ----------

$edit_id = isset($_GET["edit"]) ? intval($_GET["edit"]) : 0;
$edit_job = null;

$title = "";
$description = "";
$budget = "";
$deadline = "";
$job_status = "open";
$picked_skills = array();

if ($edit_id > 0) {

    $edit_job = getJob($conn, $edit_id);

    // only the client of this job can edit it
    if ($edit_job == null || $edit_job["client_id"] != $my_id) {
        header("Location: my-jobs.php");
        exit();
    }

    $title       = $edit_job["title"];
    $description = $edit_job["description"];
    $budget      = $edit_job["budget"];
    $deadline    = $edit_job["deadline"];
    $job_status  = $edit_job["job_status"];

    // the skills of this job
    $stmt = $conn->prepare("SELECT skill_id FROM job_skills WHERE job_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $picked_skills[] = $row["skill_id"];
    }

    $stmt->close();
}

// ============================================
// The form was submitted
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title       = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $budget      = trim($_POST["budget"]);
    $deadline    = $_POST["deadline"];
    $picked_skills = isset($_POST["skill"]) ? $_POST["skill"] : array();

    if (isset($_POST["job_status"])) {
        $job_status = $_POST["job_status"];
    }

    // ---------- validation ----------

    if ($title == "") {
        $errors["title"] = "Job title is required.";
    }
    else if (strlen($title) < 10) {
        $errors["title"] = "Write a longer title, at least 10 characters.";
    }

    if ($description == "") {
        $errors["description"] = "Job description is required.";
    }
    else if (strlen($description) < 40) {
        $errors["description"] = "Write at least 40 characters.";
    }

    if (count($picked_skills) == 0) {
        $errors["skill"] = "Select at least one skill.";
    }

    if ($budget == "") {
        $errors["budget"] = "Budget is required.";
    }
    else if (intval($budget) < 200) {
        $errors["budget"] = "The budget must be at least Tk 200.";
    }

    if ($deadline == "") {
        $errors["deadline"] = "Deadline is required.";
    }
    else if ($edit_id == 0 && $deadline < date("Y-m-d")) {
        $errors["deadline"] = "The deadline cannot be a past date.";
    }

    // ---------- save ----------

    if (count($errors) == 0) {

        $budget_amount = intval($budget);

        if ($edit_id > 0) {

            // ---------- UPDATE the old job ----------

            $stmt = $conn->prepare("UPDATE jobs
                                    SET title = ?, description = ?, budget = ?, deadline = ?, job_status = ?
                                    WHERE job_id = ?");
            $stmt->bind_param("ssissi", $title, $description, $budget_amount, $deadline, $job_status, $edit_id);
            $stmt->execute();
            $stmt->close();

            // delete the old skills and save the new ones
            $stmt = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $stmt->close();

            foreach ($picked_skills as $skill_id) {
                $stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $edit_id, $skill_id);
                $stmt->execute();
                $stmt->close();
            }

            header("Location: my-jobs.php");
            exit();
        }
        else {

            // ---------- INSERT a new job ----------

            $today = date("Y-m-d");

            $stmt = $conn->prepare("INSERT INTO jobs
                (client_id, title, description, budget, deadline, job_status, hired_id, created_at)
                VALUES (?, ?, ?, ?, ?, 'open', 0, ?)");
            $stmt->bind_param("ississ", $my_id, $title, $description, $budget_amount, $deadline, $today);
            $stmt->execute();

            $new_job_id = $conn->insert_id;
            $stmt->close();

            foreach ($picked_skills as $skill_id) {
                $stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $new_job_id, $skill_id);
                $stmt->execute();
                $stmt->close();
            }

            addActivity($conn, $my_id, "Posted a new job : " . $title);

            // tell every student who has one of these skills
            $skill_list = implode(",", array_map("intval", $picked_skills));

            $result = $conn->query("SELECT DISTINCT user_id FROM user_skills
                                    WHERE skill_id IN (" . $skill_list . ")");

            while ($row = $result->fetch_assoc()) {

                if ($row["user_id"] != $my_id) {
                    addNotification($conn, $row["user_id"],
                        "A new job for your skills : " . $title, "job-details.php?id=" . $new_job_id);
                }
            }

            header("Location: job-details.php?id=" . $new_job_id);
            exit();
        }
    }
}

$all_skills = getAllSkills($conn);

$page_title = ($edit_id > 0) ? "Edit Job" : "Post a Job";
include "header.php";
?>

    <div class="medium-page">

        <h1><?php echo ($edit_id > 0) ? "Edit Job" : "Post a New Job"; ?></h1>
        <p class="page-info">Write the job clearly so that students can send you a correct proposal.</p>

        <div class="box">

            <form method="POST" action="post-job.php<?php if ($edit_id > 0) { echo "?edit=" . $edit_id; } ?>" id="jobForm">

                <label for="title">Job Title</label>
                <input type="text" id="title" name="title" placeholder="Example : Logo design for a clothing brand"
                       value="<?php echo htmlspecialchars($title); ?>">
                <small id="titleError" class="error"><?php if (isset($errors["title"])) { echo $errors["title"]; } ?></small>

                <label for="description">Job Description</label>
                <textarea id="description" name="description"
                          placeholder="What has to be done? What will you give? What do you want at the end?"><?php echo htmlspecialchars($description); ?></textarea>
                <small id="descriptionError" class="error"><?php if (isset($errors["description"])) { echo $errors["description"]; } ?></small>

                <label>Skills Needed</label>

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

                <label for="budget">Budget (Tk)</label>
                <input type="number" id="budget" name="budget" placeholder="Example : 3500"
                       value="<?php echo htmlspecialchars($budget); ?>">
                <small id="budgetError" class="error"><?php if (isset($errors["budget"])) { echo $errors["budget"]; } ?></small>

                <label for="deadline">Deadline</label>
                <input type="date" id="deadline" name="deadline" value="<?php echo htmlspecialchars($deadline); ?>">
                <small id="deadlineError" class="error"><?php if (isset($errors["deadline"])) { echo $errors["deadline"]; } ?></small>

                <?php if ($edit_id > 0) { ?>

                    <label for="job_status">Job Status</label>

                    <select id="job_status" name="job_status">
                        <option value="open"    <?php if ($job_status == "open")    { echo "selected"; } ?>>Open</option>
                        <option value="working" <?php if ($job_status == "working") { echo "selected"; } ?>>Working</option>
                        <option value="done"    <?php if ($job_status == "done")    { echo "selected"; } ?>>Completed</option>
                    </select>

                <?php } ?>

                <div class="result">
                    The payment is done directly between you and the student.
                    KaajKoro only keeps the record of the budget.
                </div>

                <div class="buttons">
                    <button type="submit"><?php echo ($edit_id > 0) ? "Save Changes" : "Publish Job"; ?></button>
                    <button type="button" class="btn-gray" onclick="window.location.href='my-jobs.php'">Cancel</button>
                </div>

            </form>

        </div>

    </div>

<?php include "footer.php"; ?>
