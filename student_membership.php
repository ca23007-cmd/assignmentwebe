<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only student can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "student") {
    header("Location: login.php");
    exit;
}

// Get userID from session (support both common keys)
$userID = $_SESSION["UserID"] ?? ($_SESSION["userID"] ?? null);
if (!$userID) {
    die("Session UserID not found. Please login again.");
}

$name = $_SESSION["Name"] ?? "Student";
$initials = strtoupper(substr($name, 0, 2));

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

// Defaults from grantapproval
$statusText = "pending";
$approveByText = "-";
$dateText = date("Y-m-d H:i:s");

$form = [
    "NumberPlate"  => "",
    "VehicleType"  => "",
    "VehicleBrand" => ""
];

$vehicleID = null;

// Load existing vehicle + grantapproval
try {
    // vehicle: load latest by VehicleID
    $stmt = $conn->prepare("SELECT VehicleID, plateNum, vehicleType, vehicleBrand FROM vehicle WHERE userID = ? ORDER BY VehicleID DESC LIMIT 1");
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $vehicleID = (int)$row["VehicleID"];
        $form["NumberPlate"]  = $row["plateNum"] ?? "";
        $form["VehicleType"]  = $row["vehicleType"] ?? "";
        $form["VehicleBrand"] = $row["vehicleBrand"] ?? "";
    }
    $stmt->close();

    // grantapproval (NOW includes approval_staff_name)
    $stmt = $conn->prepare("
        SELECT verification_status, approval_staff_name, date
        FROM grantapproval
        WHERE userID = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $statusText = $row["verification_status"] ?? "pending";
        $approveByText = $row["approval_staff_name"] ?? "-";
        $dateText = $row["date"] ?? $dateText;
    }
    $stmt->close();

} catch (Throwable $e) {
    $error = "Load error: " . $e->getMessage();
}

// Handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (($_POST["action"] ?? "") === "cancel") {
        header("Location: student_board.php");
        exit;
    }

    $NumberPlate  = trim($_POST["NumberPlate"] ?? "");
    $VehicleType  = trim($_POST["VehicleType"] ?? "");
    $VehicleBrand = trim($_POST["VehicleBrand"] ?? "");

    if ($NumberPlate === "" || $VehicleType === "" || $VehicleBrand === "") {
        $error = "Please fill in all vehicle fields.";
    }

    // File
    $hasFile = isset($_FILES["GrantFile"]) && $_FILES["GrantFile"]["error"] === UPLOAD_ERR_OK;
    $grantBlob = null;

    if (!$error) {
        if (!$hasFile) {
            $error = "Please upload Grant file.";
        } else {
            $size = (int)($_FILES["GrantFile"]["size"] ?? 0);
            if ($size <= 0) {
                $error = "Uploaded file is empty.";
            } elseif ($size > 5 * 1024 * 1024) {
                $error = "File too large. Max 5MB.";
            } else {
                $tmp = $_FILES["GrantFile"]["tmp_name"];
                $grantBlob = file_get_contents($tmp);
                if ($grantBlob === false) {
                    $error = "Failed to read uploaded file.";
                }
            }
        }
    }

    if (!$error) {
        $conn->begin_transaction();

        try {
            // 1) Vehicle upsert for this user
            $stmt = $conn->prepare("SELECT VehicleID FROM vehicle WHERE userID = ? ORDER BY VehicleID DESC LIMIT 1");
            $stmt->bind_param("s", $userID);
            $stmt->execute();
            $res = $stmt->get_result();
            $vehicleID = null;
            if ($row = $res->fetch_assoc()) {
                $vehicleID = (int)$row["VehicleID"];
            }
            $stmt->close();

            if ($vehicleID) {
                $stmt = $conn->prepare("UPDATE vehicle SET plateNum = ?, vehicleType = ?, vehicleBrand = ? WHERE VehicleID = ? AND userID = ?");
                $stmt->bind_param("sssis", $NumberPlate, $VehicleType, $VehicleBrand, $vehicleID, $userID);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO vehicle (userID, plateNum, vehicleType, vehicleBrand) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $userID, $NumberPlate, $VehicleType, $VehicleBrand);
                $stmt->execute();
                $vehicleID = $conn->insert_id;
                $stmt->close();
            }

            // 2) grantapproval upsert (PK = userID)
            // IMPORTANT: don't overwrite approval_staff_name when student re-submits.
            $now = date("Y-m-d H:i:s");
            $newStatus = "pending";

            $stmt = $conn->prepare("SELECT approval_staff_name FROM grantapproval WHERE userID = ? LIMIT 1");
            $stmt->bind_param("s", $userID);
            $stmt->execute();
            $res = $stmt->get_result();
            $existsGrant = $res->num_rows > 0;
            $existingApproveBy = null;
            if ($row = $res->fetch_assoc()) {
                $existingApproveBy = $row["approval_staff_name"];
            }
            $stmt->close();

            if ($existsGrant) {
                $stmt = $conn->prepare("
                    UPDATE grantapproval
                    SET vehicleID = ?, grant_upload = ?, verification_status = ?, date = ?
                    WHERE userID = ?
                ");
                $stmt->bind_param("issss", $vehicleID, $grantBlob, $newStatus, $now, $userID);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO grantapproval (userID, vehicleID, grant_upload, verification_status, approval_staff_name, date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $emptyName = null; // NULL until staff approves
                $stmt->bind_param("sissss", $userID, $vehicleID, $grantBlob, $newStatus, $emptyName, $now);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            $success = "Membership submitted successfully. Status: pending";
            $statusText = $newStatus;
            $dateText = $now;

            // keep approveBy as current DB value (usually "-" / NULL)
            $approveByText = $existingApproveBy ?? "-";

            $form["NumberPlate"]  = $NumberPlate;
            $form["VehicleType"]  = $VehicleType;
            $form["VehicleBrand"] = $VehicleBrand;

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Submit failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Membership</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:system-ui,-apple-system,"Segoe UI",sans-serif; }

body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background: url("pic/red.avif") no-repeat center center fixed;
  background-size: cover;
}

.navbar{
  background:#ffffff;
  padding:12px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #fecaca;
  box-shadow:0 4px 14px rgba(248,113,113,0.25);
}

.nav-left{ display:flex; align-items:center; gap:24px; }

.logo-box{
  padding:4px 8px;
  border-radius:14px;
  border:1px solid #fecaca;
  background:#ffffff;
}
.logo-box img{ height:40px; }

.nav-links{ display:flex; gap:12px; }

.nav-link{
  text-decoration:none;
  color:#4b5563;
  padding:7px 16px;
  border-radius:999px;
}
.nav-link:hover{ background:#fee2e2; color:#b91c1c; }
.nav-link.active{ background:#dc2626; color:#ffffff; }

.nav-right{ display:flex; align-items:center; gap:10px; }

.avatar{
  width:38px; height:38px;
  border-radius:50%;
  border:2px solid #f87171;
  display:flex; align-items:center; justify-content:center;
  background:#fee2e2; color:#b91c1c;
  font-weight:700;
}

.logout-link{ font-size:12px; color:#7f1d1d; text-decoration:none; }

.main{ flex:1; padding:26px 40px 40px; }

.card{
  max-width:980px;
  background:#ffffff;
  border-radius:18px;
  border:1px solid #fecaca;
  box-shadow:0 10px 26px rgba(248,113,113,0.18);
  padding:22px 22px 26px;
}

.top-meta{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:18px;
  margin-bottom:18px;
}

.meta-left h2{
  font-size:20px;
  font-weight:800;
  color:#7f1d1d;
  margin-bottom:6px;
}
.meta-left .line{
  font-size:13px;
  color:#111827;
  margin:4px 0;
}
.meta-left .line b{ color:#7f1d1d; }

.meta-right{
  text-align:right;
  font-size:13px;
  color:#111827;
}
.meta-right b{ color:#7f1d1d; }

.alert{
  margin:0 0 12px;
  padding:10px 12px;
  border-radius:12px;
  font-size:13px;
}
.alert.error{ background:#fee2e2; border:1px solid #fecaca; color:#7f1d1d; }
.alert.ok{ background:#dcfce7; border:1px solid #bbf7d0; color:#166534; }

.form-grid{
  display:grid;
  grid-template-columns: 1fr;
  gap:10px;
  max-width:420px;
}

.label{
  font-size:12px;
  color:#111827;
  font-weight:800;
  margin-top:6px;
}

.input{
  width:100%;
  padding:10px 12px;
  border:1px solid #e5e7eb;
  border-radius:10px;
  outline:none;
}
.input:focus{
  border-color:#f87171;
  box-shadow:0 0 0 3px rgba(248,113,113,0.18);
}

.readonly{
  background:#f9fafb;
  cursor:not-allowed;
}

.grant-box{ margin-top:14px; max-width:420px; }

.file-input{
  width:100%;
  padding:10px 12px;
  border:1px dashed #fca5a5;
  border-radius:10px;
  background:#fff;
}

.actions{
  margin-top:18px;
  display:flex;
  gap:14px;
  align-items:center;
}

.btn{
  border:none;
  cursor:pointer;
  padding:12px 18px;
  border-radius:14px;
  font-weight:800;
  font-size:12px;
  letter-spacing:0.3px;
}
.btn.cancel{
  background:#ffffff;
  border:2px solid #111827;
  color:#111827;
}
.btn.submit{
  background:#dc2626;
  color:#ffffff;
  border:2px solid #dc2626;
}
.btn.cancel:hover{ background:#111827; color:#fff; }
.btn.submit:hover{ filter:brightness(0.95); }

@media(max-width:960px){
  .main{ padding:20px 16px 28px; }
  .top-meta{ flex-direction:column; align-items:flex-start; }
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
      <a href="student_board.php" class="nav-link">Dashboard</a>
      <a href="student_membership.php" class="nav-link active">Membership</a>
      <a href="student_parking_booking.php" class="nav-link">Parking Booking</a>
      <a href="#" class="nav-link">Parking Summon</a>
    </nav>
  </div>

  <div class="nav-right">
  <a href="student_profile.php" style="text-decoration:none;" title="User Profile">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
  </a>
  <a href="logout.php" class="logout-link">Log out</a>
</div>

</header>

<main class="main">
  <div class="card">

    <div class="top-meta">
      <div class="meta-left">
        
        <h2>Membership page</h2>

        <div class="line"><b>STATUS</b> : <?php echo htmlspecialchars(strtoupper($statusText)); ?></div>
        <div class="line"><b>APPROVE BY</b> : <?php echo htmlspecialchars($approveByText); ?></div>
      </div>

      <div class="meta-right">
        <div><b>DATE</b></div>
        <div><?php echo htmlspecialchars($dateText); ?></div>
      </div>
    </div>

    <?php if ($error !== ""): ?>
      <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
      <div class="alert ok"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="label">Matric number (UserID):</div>
        <input class="input readonly" type="text" value="<?php echo htmlspecialchars($userID); ?>" readonly>

        <div class="label">Number Plate :</div>
        <input class="input" type="text" name="NumberPlate"
               value="<?php echo htmlspecialchars($form["NumberPlate"]); ?>" required>

        <div class="label">Vehicle type :</div>
        <input class="input" type="text" name="VehicleType"
               value="<?php echo htmlspecialchars($form["VehicleType"]); ?>" required>

        <div class="label">Vehicle brand :</div>
        <input class="input" type="text" name="VehicleBrand"
               value="<?php echo htmlspecialchars($form["VehicleBrand"]); ?>" required>
      </div>

      <div class="grant-box">
        <div class="label" style="margin-bottom:8px;">GRANT :</div>
        <input class="file-input" type="file" name="GrantFile" accept="image/*,application/pdf" required>
        <div style="margin-top:6px;font-size:12px;color:#6b7280;">
          Upload grant proof (image/pdf). 
        </div>
      </div>

      <div class="actions">
        <button type="submit" name="action" value="cancel" class="btn cancel">CANCEL</button>
        <button type="submit" name="action" value="submit" class="btn submit">SUBMIT</button>
      </div>
    </form>

  </div>
</main>

</body>
</html>
