<?php
session_start();
require_once "config.php";
require_once "functions.php";

// this page is open for everybody, but a visitor cannot apply
$my_id = 0;

if (isset($_SESSION["user_id"])) {
    $my_id = $_SESSION["user_id"];
}

// ---------- read the search boxes from the link (GET) ----------

$search   = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$skill_id = isset($_GET["skill"])  ? intval($_GET["skill"]) : 0;
$status   = isset($_GET["status"]) ? $_GET["status"] : "open";
$sort     = isset($_GET["sort"])   ? $_GET["sort"]   : "new";

// only these words are allowed, so nobody can write his own SQL here
$allowed_status = array("open", "working", "done", "all");

if (!in_array($status, $allowed_status)) {
    $status = "open";
}

if ($sort == "high") {
    $order_by = "j.budget DESC";
}
else if ($sort == "low") {
    $order_by = "j.budget ASC";
}
else {
    $order_by = "j.job_id DESC";
}

// ---------- build the query ----------

$sql = "SELECT DISTINCT j.*, u.name AS client_name
        FROM jobs j
        JOIN users u ON j.client_id = u.user_id";

if ($skill_id > 0) {
    $sql = $sql . " JOIN job_skills js ON j.job_id = js.job_id";
}

$sql = $sql . " WHERE 1 = 1";

if ($status != "all") {
    $sql = $sql . " AND j.job_status = '" . $status . "'";
}

if ($skill_id > 0) {
    $sql = $sql . " AND js.skill_id = " . $skill_id;
}

// the text written by the user always goes through a prepared statement
if ($search != "") {
    $sql = $sql . " AND (j.title LIKE ? OR j.description LIKE ?)";
}

$sql = $sql . " ORDER BY " . $order_by;

$stmt = $conn->prepare($sql);

if ($search != "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("ss", $like, $like);
}

$stmt->execute();
$result = $stmt->get_result();
$total_found = $result->num_rows;

// ---------- the jobs I already applied for ----------

$applied_jobs = array();

if ($my_id > 0) {

    $stmt2 = $conn->prepare("SELECT job_id FROM proposals WHERE user_id = ?");
    $stmt2->bind_param("i", $my_id);
    $stmt2->execute();
    $applied_result = $stmt2->get_result();

    while ($row = $applied_result->fetch_assoc()) {
        $applied_jobs[] = $row["job_id"];
    }

    $stmt2->close();
}

$all_skills = getAllSkills($conn);

$page_title = "Find Work";
include "header.php";
?>

    <div class="page">

        <h1>Find Paid Work</h1>
        <p class="page-info">Browse the jobs posted by clients and send your proposal.</p>

        <!-- Search and filter : this form sends everything with GET -->

        <div class="box">

            <form method="GET" action="jobs.php">

                <div class="row">

                    <div class="col-half">
                        <label for="search">Search</label>
                        <input type="search" id="search" name="search" placeholder="Write a job title or a word"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-half">
                        <label for="skill">Skill</label>
                        <select id="skill" name="skill">
                            <option value="0">All skills</option>
                            <?php
                            foreach ($all_skills as $skill) {

                                if ($skill_id == $skill["skill_id"]) {
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
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="open"    <?php if ($status == "open")    { echo "selected"; } ?>>Open jobs</option>
                            <option value="working" <?php if ($status == "working") { echo "selected"; } ?>>Working</option>
                            <option value="done"    <?php if ($status == "done")    { echo "selected"; } ?>>Completed</option>
                            <option value="all"     <?php if ($status == "all")     { echo "selected"; } ?>>All jobs</option>
                        </select>
                    </div>

                    <div class="col-half">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort">
                            <option value="new"  <?php if ($sort == "new")  { echo "selected"; } ?>>Newest first</option>
                            <option value="high" <?php if ($sort == "high") { echo "selected"; } ?>>Budget : high to low</option>
                            <option value="low"  <?php if ($sort == "low")  { echo "selected"; } ?>>Budget : low to high</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='jobs.php'">Clear</button>
                    <button type="button" class="btn-green" onclick="window.location.href='post-job.php'">Post a Job</button>
                </div>

            </form>

        </div>

        <p class="page-info"><?php echo $total_found; ?> job(s) found</p>

        <?php
        if ($total_found == 0) {
            echo "<div class='empty'>No job found. Please change the search or the filter.</div>";
        }

        while ($job = $result->fetch_assoc()) {

            $skills = getJobSkills($conn, $job["job_id"]);

            // how many proposals this job received
            $proposal_count = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE job_id = ?", $job["job_id"]);
            ?>

            <div class="item">

                <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>

                <p class="item-info">
                    Client : <?php echo htmlspecialchars($job["client_name"]); ?>
                    &nbsp; | &nbsp; Deadline : <?php echo showDate($job["deadline"]); ?>
                    &nbsp; | &nbsp; Proposals : <?php echo $proposal_count; ?>
                    &nbsp; | &nbsp; Posted : <?php echo showDate($job["created_at"]); ?>
                </p>

                <p class="item-text"><?php echo htmlspecialchars($job["description"]); ?></p>

                <p>
                    <?php
                    foreach ($skills as $skill) {
                        echo "<span class='tag tag-blue'>" . htmlspecialchars($skill) . "</span>";
                    }
                    ?>
                </p>

                <p class="price"><?php echo showMoney($job["budget"]); ?> &nbsp; <?php echo showStatus($job["job_status"]); ?></p>

                <?php if (in_array($job["job_id"], $applied_jobs)) { ?>
                    <p class="gray-text">You already sent a proposal for this job.</p>
                <?php } ?>

                <div class="buttons">
                    <button class="btn-small" onclick="window.location.href='job-details.php?id=<?php echo $job["job_id"]; ?>'">View Details</button>
                </div>

            </div>

            <?php
        }

        $stmt->close();
        ?>

    </div>

<?php include "footer.php"; ?>
