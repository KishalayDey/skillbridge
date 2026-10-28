<?php
session_start();
require_once "config.php";
require_once "functions.php";

$my_id = 0;

if (isset($_SESSION["user_id"])) {
    $my_id = $_SESSION["user_id"];
}

// ---------- the filters ----------

$search   = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$skill_id = isset($_GET["skill"])  ? intval($_GET["skill"]) : 0;
$city     = isset($_GET["city"])   ? trim($_GET["city"]) : "";
$sort     = isset($_GET["sort"])   ? $_GET["sort"] : "rating";

$sql = "SELECT DISTINCT u.* FROM users u";

if ($skill_id > 0) {
    $sql = $sql . " JOIN user_skills us ON u.user_id = us.user_id";
}

$sql = $sql . " WHERE u.status = 'active' AND u.user_id != " . intval($my_id);

if ($skill_id > 0) {
    $sql = $sql . " AND us.skill_id = " . $skill_id;
}

if ($city != "") {
    $sql = $sql . " AND u.city = ?";
}

if ($search != "") {
    $sql = $sql . " AND (u.name LIKE ? OR u.university LIKE ? OR u.bio LIKE ?)";
}

if ($sort == "name") {
    $sql = $sql . " ORDER BY u.name";
}
else if ($sort == "new") {
    $sql = $sql . " ORDER BY u.user_id DESC";
}
else {
    $sql = $sql . " ORDER BY u.user_id";
}

$stmt = $conn->prepare($sql);

// bind the text values in the right order
if ($city != "" && $search != "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("ssss", $city, $like, $like, $like);
}
else if ($city != "") {
    $stmt->bind_param("s", $city);
}
else if ($search != "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("sss", $like, $like, $like);
}

$stmt->execute();
$result = $stmt->get_result();

// put the students in an array first, so we can sort them by rating
$student_list = array();

while ($row = $result->fetch_assoc()) {
    $row["rating"] = getAverageRating($conn, $row["user_id"]);
    $student_list[] = $row;
}

$stmt->close();

// the rating is calculated, not saved, so we sort it here
if ($sort == "rating") {

    for ($i = 0; $i < count($student_list); $i++) {

        for ($j = $i + 1; $j < count($student_list); $j++) {

            if ($student_list[$j]["rating"] > $student_list[$i]["rating"]) {
                $temp = $student_list[$i];
                $student_list[$i] = $student_list[$j];
                $student_list[$j] = $temp;
            }
        }
    }
}

$all_skills = getAllSkills($conn);

// the city list for the dropdown
$city_result = $conn->query("SELECT DISTINCT city FROM users ORDER BY city");

$page_title = "Students";
include "header.php";
?>

    <div class="page">

        <h1>Find Students</h1>
        <p class="page-info">See the profile, the skills and the rating of every student.</p>

        <div class="box">

            <form method="GET" action="students.php">

                <div class="row">

                    <div class="col-half">
                        <label for="search">Search</label>
                        <input type="search" id="search" name="search" placeholder="Write a name, a university or a word"
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
                        <label for="city">City</label>
                        <select id="city" name="city">
                            <option value="">All cities</option>
                            <?php
                            while ($one = $city_result->fetch_assoc()) {
                                if ($city == $one["city"]) {
                                    echo "<option value='" . htmlspecialchars($one["city"]) . "' selected>" . htmlspecialchars($one["city"]) . "</option>";
                                }
                                else {
                                    echo "<option value='" . htmlspecialchars($one["city"]) . "'>" . htmlspecialchars($one["city"]) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-half">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort">
                            <option value="rating" <?php if ($sort == "rating") { echo "selected"; } ?>>Best rating</option>
                            <option value="name"   <?php if ($sort == "name")   { echo "selected"; } ?>>Name (A to Z)</option>
                            <option value="new"    <?php if ($sort == "new")    { echo "selected"; } ?>>Newest member</option>
                        </select>
                    </div>

                </div>

                <div class="buttons">
                    <button type="submit">Search</button>
                    <button type="button" class="btn-gray" onclick="window.location.href='students.php'">Clear</button>
                </div>

            </form>

        </div>

        <p class="page-info"><?php echo count($student_list); ?> student(s) found</p>

        <?php
        if (count($student_list) == 0) {
            echo "<div class='empty'>No student found. Please change the search or the filter.</div>";
        }

        foreach ($student_list as $student) {

            $skills = getUserSkills($conn, $student["user_id"]);

            $done_count = countRows($conn,
                "SELECT COUNT(*) AS total FROM jobs WHERE hired_id = ? AND job_status = 'done'",
                $student["user_id"]);
            ?>

            <div class="item">

                <h3><a href="student-profile.php?id=<?php echo $student["user_id"]; ?>"><?php echo htmlspecialchars($student["name"]); ?></a></h3>

                <p class="item-info"><?php echo htmlspecialchars($student["university"]); ?>
                    &nbsp; | &nbsp; <?php echo htmlspecialchars($student["city"]); ?>
                    &nbsp; | &nbsp; Member since <?php echo showDate($student["joined"]); ?></p>

                <p><?php echo showStars($student["rating"]); ?> <?php echo number_format($student["rating"], 1); ?>
                    ( <?php echo countReviews($conn, $student["user_id"]); ?> reviews )
                    &nbsp; | &nbsp; <?php echo $done_count; ?> job(s) completed</p>

                <p class="item-text"><?php echo htmlspecialchars($student["bio"]); ?></p>

                <p>
                    <?php
                    foreach ($skills as $skill) {
                        echo "<span class='tag tag-blue'>" . htmlspecialchars($skill) . "</span>";
                    }
                    ?>
                </p>

                <div class="buttons">
                    <button class="btn-small" onclick="window.location.href='student-profile.php?id=<?php echo $student["user_id"]; ?>'">View Profile</button>

                    <?php if ($my_id > 0) { ?>
                        <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $student["user_id"]; ?>'">Message</button>
                    <?php } ?>
                </div>

            </div>

            <?php
        }
        ?>

    </div>

<?php include "footer.php"; ?>
