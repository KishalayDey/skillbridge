<?php
session_start();
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";

$my_id = $me["user_id"];

// ---------- withdraw a proposal ----------

if (isset($_GET["action"]) && $_GET["action"] == "withdraw") {

    $proposal_id = intval($_GET["id"]);

    // the WHERE also checks user_id, so nobody can delete another student's proposal
    $stmt = $conn->prepare("DELETE FROM proposals WHERE proposal_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $proposal_id, $my_id);
    $stmt->execute();
    $stmt->close();

    header("Location: proposals.php");
    exit();
}

// ---------- the filter ----------

$filter = isset($_GET["filter"]) ? $_GET["filter"] : "all";

$allowed = array("all", "pending", "accepted", "rejected");

if (!in_array($filter, $allowed)) {
    $filter = "all";
}

// ---------- the numbers ----------

$total    = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ?", $my_id);
$pending  = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ? AND status = 'pending'", $my_id);
$accepted = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ? AND status = 'accepted'", $my_id);
$rejected = countRows($conn, "SELECT COUNT(*) AS total FROM proposals WHERE user_id = ? AND status = 'rejected'", $my_id);

$page_title = "My Proposals";
include "header.php";
?>

    <div class="page">

        <h1>My Proposals</h1>
        <p class="page-info">All the proposals you sent and their present status.</p>

        <div class="stats">

            <div class="stat">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-name">Total Sent</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-name">Pending</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $accepted; ?></div>
                <div class="stat-name">Accepted</div>
            </div>

            <div class="stat">
                <div class="stat-number"><?php echo $rejected; ?></div>
                <div class="stat-name">Rejected</div>
            </div>

        </div>

        <div class="box">

            <form method="GET" action="proposals.php">

                <label for="filter">Show</label>

                <select id="filter" name="filter" style="width:250px" onchange="this.form.submit()">
                    <option value="all"      <?php if ($filter == "all")      { echo "selected"; } ?>>All proposals</option>
                    <option value="pending"  <?php if ($filter == "pending")  { echo "selected"; } ?>>Pending only</option>
                    <option value="accepted" <?php if ($filter == "accepted") { echo "selected"; } ?>>Accepted only</option>
                    <option value="rejected" <?php if ($filter == "rejected") { echo "selected"; } ?>>Rejected only</option>
                </select>

            </form>

            <div style="margin-top:20px">

                <?php
                $sql = "SELECT p.*, j.title, j.budget, j.job_status, j.client_id
                        FROM proposals p
                        JOIN jobs j ON p.job_id = j.job_id
                        WHERE p.user_id = ?";

                if ($filter != "all") {
                    $sql = $sql . " AND p.status = '" . $filter . "'";
                }

                $sql = $sql . " ORDER BY p.proposal_id DESC";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $my_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 0) {
                    echo "<div class='empty'>No proposal in this list. <a href='jobs.php'>Find work</a></div>";
                }
                else {
                    ?>

                    <table>

                        <tr>
                            <th>Job</th>
                            <th>Client</th>
                            <th>My Bid</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php while ($proposal = $result->fetch_assoc()) { ?>

                            <tr>
                                <td>
                                    <a href="job-details.php?id=<?php echo $proposal["job_id"]; ?>"><?php echo htmlspecialchars($proposal["title"]); ?></a><br>
                                    <span class="gray-text">Budget : <?php echo showMoney($proposal["budget"]); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(getUserName($conn, $proposal["client_id"])); ?></td>
                                <td><?php echo showMoney($proposal["bid"]); ?></td>
                                <td><?php echo showDate($proposal["created_at"]); ?></td>
                                <td><?php echo showStatus($proposal["status"]); ?></td>
                                <td>
                                    <button class="btn-small btn-gray" onclick="window.location.href='messages.php?to=<?php echo $proposal["client_id"]; ?>'">Message</button>

                                    <?php if ($proposal["status"] == "pending") { ?>
                                        <button class="btn-small btn-red" onclick="if (confirm('Do you want to withdraw this proposal?')) { window.location.href='proposals.php?action=withdraw&id=<?php echo $proposal["proposal_id"]; ?>'; }">Withdraw</button>
                                    <?php } ?>
                                </td>
                            </tr>

                        <?php } ?>

                    </table>

                    <?php
                }

                $stmt->close();
                ?>

            </div>

        </div>

    </div>

<?php include "footer.php"; ?>
