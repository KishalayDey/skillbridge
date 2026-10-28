<?php
session_start();
require_once "config.php";
require_once "functions.php";

// ---------- the numbers of the platform ----------

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $result->fetch_assoc();
$total_users = $row["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM jobs");
$row = $result->fetch_assoc();
$total_jobs = $row["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM posts");
$row = $result->fetch_assoc();
$total_posts = $row["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM skills");
$row = $result->fetch_assoc();
$total_skills = $row["total"];

$page_title = "Home";
include "header.php";
?>

    <!-- Banner -->

    <div class="banner">
        <div class="container">

            <h1>Share your skills. Earn from your skills.</h1>

            <p>KaajKoro is a platform for Bangladeshi students to exchange skills
                with each other and to find paid freelance work.</p>

            <button onclick="window.location.href='register.php'">Create Account</button>
            <button class="btn-gray" onclick="window.location.href='jobs.php'">Browse Jobs</button>

        </div>
    </div>

    <div class="page">

        <!-- Numbers -->

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-name">Students</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_jobs; ?></div>
                <div class="stat-name">Jobs Posted</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_posts; ?></div>
                <div class="stat-name">Skill Exchanges</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $total_skills; ?></div>
                <div class="stat-name">Skills</div>
            </div>

        </div>

        <!-- Two ways to use -->

        <div class="row">

            <div class="box col-half">
                <h2>Skill Sharing</h2>
                <p class="page-info">No money is needed here.</p>

                <p>You know Photoshop but you want to learn Python. Another student knows
                    Python and needs a poster. Post what you can teach, write what you want
                    to learn, and exchange with each other.</p>

                <div class="buttons">
                    <button onclick="window.location.href='exchange.php'">See Skill Exchanges</button>
                </div>
            </div>

            <div class="box col-half">
                <h2>Paid Work</h2>
                <p class="page-info">Real freelance jobs with a budget.</p>

                <p>Clients post jobs with a budget and a deadline. Students send proposals,
                    the client selects one student, and after the work is finished both sides
                    give a rating and a review.</p>

                <div class="buttons">
                    <button onclick="window.location.href='jobs.php'">See Open Jobs</button>
                </div>
            </div>

        </div>

        <!-- How it works -->

        <div class="box">

            <h2 class="box-title">How It Works</h2>

            <div class="row">

                <div class="home-box">
                    <h3>1. Create Profile</h3>
                    <p class="item-text">Add your university, your skills, education and portfolio link.</p>
                </div>

                <div class="home-box">
                    <h3>2. Find a Match</h3>
                    <p class="item-text">Search jobs or exchange posts, or let clients find you.</p>
                </div>

                <div class="home-box">
                    <h3>3. Talk and Agree</h3>
                    <p class="item-text">Send messages, arrange a meeting and agree on the terms.</p>
                </div>

                <div class="home-box">
                    <h3>4. Finish and Review</h3>
                    <p class="item-text">Deliver the work, then give and receive a rating.</p>
                </div>

            </div>

        </div>

        <!-- Latest jobs -->

        <div class="box">

            <h2 class="box-title">Latest Jobs</h2>

            <?php
            // the newest 3 open jobs, with the name of the client
            $result = $conn->query("SELECT j.*, u.name AS client_name
                                    FROM jobs j
                                    JOIN users u ON j.client_id = u.user_id
                                    WHERE j.job_status = 'open'
                                    ORDER BY j.job_id DESC
                                    LIMIT 3");

            while ($job = $result->fetch_assoc()) {
                ?>

                <div class="item">
                    <h3><a href="job-details.php?id=<?php echo $job["job_id"]; ?>"><?php echo htmlspecialchars($job["title"]); ?></a></h3>
                    <p class="item-info">Posted by <?php echo htmlspecialchars($job["client_name"]); ?>
                        &nbsp; | &nbsp; Deadline : <?php echo showDate($job["deadline"]); ?></p>
                    <p class="item-text"><?php echo htmlspecialchars($job["description"]); ?></p>
                    <p class="price"><?php echo showMoney($job["budget"]); ?></p>
                </div>

                <?php
            }
            ?>

            <a href="jobs.php">See all jobs</a>

        </div>

        <!-- Latest exchange posts -->

        <div class="box">

            <h2 class="box-title">Latest Skill Exchanges</h2>

            <?php
            // the newest 3 open exchange posts, with the student and the two skill names
            $result = $conn->query("SELECT p.*, u.name, u.university, u.city,
                                           s1.skill_name AS offer_skill,
                                           s2.skill_name AS want_skill
                                    FROM posts p
                                    JOIN users u  ON p.user_id = u.user_id
                                    JOIN skills s1 ON p.offer_skill_id = s1.skill_id
                                    JOIN skills s2 ON p.want_skill_id = s2.skill_id
                                    WHERE p.post_status = 'open'
                                    ORDER BY p.post_id DESC
                                    LIMIT 3");

            while ($post = $result->fetch_assoc()) {
                ?>

                <div class="item item-green">
                    <h3><?php echo htmlspecialchars($post["name"]); ?></h3>
                    <p class="item-info"><?php echo htmlspecialchars($post["university"]); ?>
                        &nbsp; | &nbsp; <?php echo htmlspecialchars($post["city"]); ?></p>
                    <p>
                        <span class="tag tag-blue">Can teach : <?php echo htmlspecialchars($post["offer_skill"]); ?></span>
                        <span class="tag tag-orange">Wants to learn : <?php echo htmlspecialchars($post["want_skill"]); ?></span>
                    </p>
                    <p class="item-text"><?php echo htmlspecialchars($post["description"]); ?></p>
                </div>

                <?php
            }
            ?>

            <a href="exchange.php">See all exchange posts</a>

        </div>

    </div>

<?php include "footer.php"; ?>
