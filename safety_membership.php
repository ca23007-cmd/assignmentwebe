<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only safety staff can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "safety_staff") {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$staffName = $_SESSION["Name"] ?? "Safety Staff";
$initials  = strtoupper(substr($staffName, 0, 2));

$error = "";
$success = "";

/**
 * This page supports two views:
 * 1) List view (default): show all pending membership requests
 * 2) Detail view (?userID=XXXX): show one request + Approve/Reject
 *
 * Table usage:
 * - grantapproval: userID (matric), vehicleID, grant_upload (blob), verification_status, approval_staff_name, date
 * - vehicle: VehicleID, userID, plateNum, vehicleType, vehicleBrand
 */

// ---- Helper: safe GET ----
$selectedUserID = trim($_GET["userID"] ?? "");

// ---- Handle POST action (Approve/Reject) ----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action  = $_POST["action"] ?? "";
    $userID  = trim($_POST["userID"] ?? "");
    $remarks = trim($_POST["remarks"] ?? ""); // UI only

    if ($userID === "") {
        $error = "Invalid request: userID missing.";
    } else {
        if ($action === "approve") {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT userID FROM grantapproval WHERE userID = ? LIMIT 1");
                $stmt->bind_param("s", $userID);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$exists) {
                    throw new Exception("Request not found for this matric (userID).");
                }

                $status = "approved";
                $stmt = $conn->prepare("
                    UPDATE grantapproval
                    SET verification_status = ?, approval_staff_name = ?, date = NOW()
                    WHERE userID = ?
                ");
                $stmt->bind_param("sss", $status, $staffName, $userID);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $success = "Request approved successfully for matric: " . htmlspecialchars($userID);
                $selectedUserID = $userID;

            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Approve failed: " . $e->getMessage();
            }
        } elseif ($action === "reject") {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT vehicleID FROM grantapproval WHERE userID = ? LIMIT 1");
                $stmt->bind_param("s", $userID);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    throw new Exception("Request not found for this matric (userID).");
                }

                $vehicleID = $row["vehicleID"] !== null ? (int)$row["vehicleID"] : null;

                $status = "rejected";
                $stmt = $conn->prepare("
                    UPDATE grantapproval
                    SET verification_status = ?, approval_staff_name = ?, date = NOW()
                    WHERE userID = ?
                ");
                $stmt->bind_param("sss", $status, $staffName, $userID);
                $stmt->execute();
                $stmt->close();

                if ($vehicleID) {
                    $stmt = $conn->prepare("DELETE FROM vehicle WHERE VehicleID = ? AND userID = ?");
                    $stmt->bind_param("is", $vehicleID, $userID);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                $success = "Request rejected. Vehicle data removed (if existed) for matric: " . htmlspecialchars($userID);
                $selectedUserID = $userID;

            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Reject failed: " . $e->getMessage();
            }
        } else {
            $error = "Invalid action.";
        }
    }
}

// ---- If detail view: load selected request + vehicle ----
$detail = null;
$vehicle = null;

if ($selectedUserID !== "") {
    try {
        $stmt = $conn->prepare("
            SELECT userID, vehicleID, verification_status, approval_staff_name, date
            FROM grantapproval
            WHERE userID = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $selectedUserID);
        $stmt->execute();
        $res = $stmt->get_result();
        $detail = $res->fetch_assoc();
        $stmt->close();

        if ($detail) {
            $vid = $detail["vehicleID"] !== null ? (int)$detail["vehicleID"] : null;
            if ($vid) {
                $stmt = $conn->prepare("
                    SELECT VehicleID, plateNum, vehicleType, vehicleBrand
                    FROM vehicle
                    WHERE VehicleID = ? AND userID = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $vid, $selectedUserID);
                $stmt->execute();
                $vehicle = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        } else {
            $error = $error ?: "Request not found.";
        }
    } catch (Throwable $e) {
        $error = "Load detail error: " . $e->getMessage();
    }
}

// ---- List view: load all pending requests ----
$pendingList = [];
try {
    $stmt = $conn->prepare("
        SELECT ga.userID, ga.date, v.plateNum, v.vehicleType, v.vehicleBrand
        FROM grantapproval ga
        LEFT JOIN vehicle v ON v.VehicleID = ga.vehicleID AND v.userID = ga.userID
        WHERE ga.verification_status = 'pending'
        ORDER BY ga.date DESC
    ");
    $stmt->execute();
    $pendingList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    if ($error === "") {
        $error = "Load pending list error: " . $e->getMessage();
    }
}

// ---- Download grant blob handler (serve as file) ----
if (isset($_GET["download_grant"]) && $_GET["download_grant"] === "1") {
    $uid = trim($_GET["userID"] ?? "");
    if ($uid !== "") {
        $stmt = $conn->prepare("SELECT grant_upload FROM grantapproval WHERE userID = ? LIMIT 1");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && $row["grant_upload"] !== null) {
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"grant_$uid\"");
            echo $row["grant_upload"];
            exit;
        }
    }
    die("Grant file not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>MyPetakom – Membership Approval</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}

/* ✅ Background: use your pic/blue.webp + soft overlay */
body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background:
    linear-gradient(180deg, rgba(224,242,254,0.78), rgba(219,234,254,0.78)),
    url("pic/blue.webp") no-repeat center center fixed;
  background-size: cover;
}


/* navbar */
.navbar{
  background:#fff;
  padding:12px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #bfdbfe;
  box-shadow:0 8px 18px rgba(59,130,246,0.15);
}
.nav-left{display:flex;align-items:center;gap:24px;}
.logo-box{
  padding:4px 8px;
  border-radius:14px;
  border:1px solid #bfdbfe;
  background:#fff;
}
.logo-box img{height:40px;}

.nav-links{display:flex;gap:12px;flex-wrap:wrap;}
.nav-link{
  text-decoration:none;
  color:#334155;
  padding:7px 16px;
  border-radius:999px;
}
.nav-link:hover{background:#dbeafe;color:#1d4ed8;}
.nav-link.active{background:#2563eb;color:#fff;}

.nav-right{display:flex;align-items:center;gap:10px;}

/* ✅ clickable avatar */
.avatar-link{ text-decoration:none; display:inline-block; }
.avatar{
  width:38px;height:38px;border-radius:50%;
  border:2px solid #60a5fa;
  display:flex;align-items:center;justify-content:center;
  background:#dbeafe;color:#1d4ed8;font-weight:800;
  cursor:pointer;
}

.logout-link{font-size:12px;color:#0f172a;text-decoration:none;opacity:0.8;}
.logout-link:hover{opacity:1}

/* main */
.main{flex:1;padding:26px 40px 40px;}
.page-header h2{font-size:22px;font-weight:800;color:#0f172a;}
.page-header p{font-size:13px;color:#475569;margin-top:4px;margin-bottom:16px;}

.grid{
  display:grid;
  grid-template-columns: 360px 1fr;
  gap:18px;
  align-items:start;
}

/* card */
.card{
  background:#fff;
  border-radius:18px;
  border:1px solid #bfdbfe;
  box-shadow:0 10px 26px rgba(59,130,246,0.12);
  padding:18px 18px 20px;
}
.card h3{font-size:14px;font-weight:900;color:#0f172a;margin-bottom:12px;}
.small{font-size:12px;color:#64748b;margin-top:4px;}

/* list */
.list{display:flex;flex-direction:column;gap:10px;}
.item{
  border:1px solid #e2e8f0;
  border-radius:14px;
  padding:10px 12px;
  display:flex;
  flex-direction:column;
  gap:6px;
  background:#f8fafc;
}
.item-top{
  display:flex;
  justify-content:space-between;
  gap:10px;
  align-items:flex-start;
}
.badge{
  font-size:11px;
  font-weight:900;
  letter-spacing:0.2px;
  padding:6px 10px;
  border-radius:999px;
  background:#dbeafe;
  color:#1d4ed8;
}
.kv{font-size:12px;color:#0f172a;}
.kv b{color:#1e293b;}
.item a{
  align-self:flex-start;
  font-size:12px;
  text-decoration:none;
  color:#2563eb;
  font-weight:800;
}
.item a:hover{text-decoration:underline;}

/* detail */
.detail-top{
  display:flex;
  justify-content:space-between;
  gap:16px;
  margin-bottom:12px;
}
.detail-top .title{
  font-size:18px;
  font-weight:900;
  color:#0f172a;
}
.meta{font-size:13px;color:#0f172a;}
.meta b{color:#1d4ed8;}

.form-grid{
  margin-top:10px;
  display:grid;
  grid-template-columns: 160px 1fr;
  gap:10px 12px;
  max-width:680px;
}
.label{
  font-size:12px;
  font-weight:900;
  color:#0f172a;
  padding-top:8px;
}
.input{
  width:100%;
  padding:10px 12px;
  border:1px solid #e2e8f0;
  border-radius:10px;
  outline:none;
  background:#fff;
}
.input.readonly{background:#f1f5f9;}

.actions{
  margin-top:14px;
  display:flex;
  gap:12px;
  align-items:center;
}
.btn{
  border:none;
  cursor:pointer;
  padding:12px 18px;
  border-radius:14px;
  font-weight:900;
  font-size:12px;
}
.btn.approve{background:#2563eb;color:#fff;}
.btn.reject{background:#fff;color:#0f172a;border:2px solid #0f172a;}
.btn.approve:hover{filter:brightness(0.95);}
.btn.reject:hover{background:#0f172a;color:#fff;}

.filebox{display:flex;gap:10px;align-items:center;}
.linkbtn{
  display:inline-block;
  padding:10px 12px;
  border-radius:12px;
  background:#eff6ff;
  border:1px solid #bfdbfe;
  color:#1d4ed8;
  font-weight:900;
  font-size:12px;
  text-decoration:none;
}
.linkbtn:hover{filter:brightness(0.98);}

.alert{
  margin:0 0 12px;
  padding:10px 12px;
  border-radius:12px;
  font-size:13px;
}
.alert.error{background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;}
.alert.ok{background:#dcfce7;border:1px solid #bbf7d0;color:#166534;}

@media(max-width:1020px){
  .main{padding:20px 16px 28px;}
  .grid{grid-template-columns:1fr;}
}
</style>

<script>
window.addEventListener("load", function () {
  history.pushState(null, "", location.href);
  window.onpopstate = function () {
    window.location.href = "logout.php";
  };
});
</script>
</head>
<body>

<header class="navbar">
  <div class="nav-left">
    <div class="logo-box">
      <img src="pic/pentakom.png" alt="Logo">
    </div>

    <nav class="nav-links">
      <a href="staff_dashboard.php" class="nav-link">Dashboard</a>
      <a href="safety_membership.php" class="nav-link active">Membership</a>
      <a href="#" class="nav-link">Issue summon</a>
      <a href="#" class="nav-link">Summon history</a>
      <a href="#" class="nav-link">Demerit points</a>
    </nav>
  </div>

  <div class="nav-right">
    <!-- ✅ Avatar clickable to staff profile -->
    <a class="avatar-link" href="staff_profile.php" title="Staff Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">LOG OUT</a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>Membership approval</h2>
    <p>Approve or reject student membership requests based on uploaded grant proof.</p>
  </div>

  <?php if ($error !== ""): ?>
    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($success !== ""): ?>
    <div class="alert ok"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="grid">

    <!-- LEFT: Pending list -->
    <section class="card">
      <h3>Pending requests</h3>
      <div class="small">Click “Review” to open request details.</div>

      <div style="height:12px"></div>

      <div class="list">
        <?php if (count($pendingList) === 0): ?>
          <div class="small">No pending requests.</div>
        <?php else: ?>
          <?php foreach ($pendingList as $r): ?>
            <div class="item">
              <div class="item-top">
                <div class="kv"><b>Matric (userID):</b> <?php echo htmlspecialchars($r["userID"]); ?></div>
                <div class="badge">PENDING</div>
              </div>
              <div class="kv"><b>Plate:</b> <?php echo htmlspecialchars($r["plateNum"] ?? "-"); ?></div>
              <div class="kv"><b>Type:</b> <?php echo htmlspecialchars($r["vehicleType"] ?? "-"); ?> | <b>Brand:</b> <?php echo htmlspecialchars($r["vehicleBrand"] ?? "-"); ?></div>
              <div class="kv"><b>Date:</b> <?php echo htmlspecialchars($r["date"] ?? "-"); ?></div>
              <a href="safety_membership.php?userID=<?php echo urlencode($r["userID"]); ?>">Review</a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <!-- RIGHT: Detail + Approve/Reject -->
    <section class="card">
      <div class="detail-top">
        <div>
          <div class="title">Membership approval</div>
          <div class="small">Request detail</div>
        </div>
        <div class="meta" style="text-align:right;">
          <div><b>DATE</b></div>
          <div><?php echo htmlspecialchars($detail["date"] ?? date("Y-m-d H:i:s")); ?></div>
        </div>
      </div>

      <?php if (!$detail): ?>
        <div class="small">Select a pending request on the left to review.</div>
      <?php else: ?>
        <div class="small"><b>Status:</b> <?php echo htmlspecialchars(strtoupper($detail["verification_status"] ?? "pending")); ?></div>
        <div class="small"><b>Approved by:</b> <?php echo htmlspecialchars($detail["approval_staff_name"] ?? "-"); ?></div>

        <form method="POST" style="margin-top:12px;">
          <input type="hidden" name="userID" value="<?php echo htmlspecialchars($detail["userID"]); ?>">

          <div class="form-grid">
            <div class="label">Matric number:</div>
            <input class="input readonly" type="text" value="<?php echo htmlspecialchars($detail["userID"]); ?>" readonly>

            <div class="label">Number Plate:</div>
            <input class="input readonly" type="text" value="<?php echo htmlspecialchars($vehicle["plateNum"] ?? "-"); ?>" readonly>

            <div class="label">Vehicle type:</div>
            <input class="input readonly" type="text" value="<?php echo htmlspecialchars($vehicle["vehicleType"] ?? "-"); ?>" readonly>

            <div class="label">Vehicle brand:</div>
            <input class="input readonly" type="text" value="<?php echo htmlspecialchars($vehicle["vehicleBrand"] ?? "-"); ?>" readonly>

            <div class="label">GRANT:</div>
            <div class="filebox">
              <a class="linkbtn" href="safety_membership.php?download_grant=1&userID=<?php echo urlencode($detail["userID"]); ?>">Download grant upload</a>
              <div class="small">Stored in <b>grantapproval.grant_upload</b> (BLOB)</div>
            </div>

            <div class="label">Remarks of student:</div>
            <textarea class="input" name="remarks" rows="4" placeholder="(Optional) Remarks..."><?php echo htmlspecialchars($_POST["remarks"] ?? ""); ?></textarea>
          </div>

          <div class="actions">
            <button type="submit" name="action" value="approve" class="btn approve">Approve</button>
            <button type="submit" name="action" value="reject" class="btn reject"
              onclick="return confirm('Reject this request? Vehicle record will be deleted.');">Reject</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

  </div>
</main>

</body>
</html>
