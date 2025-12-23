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

// Get userID
$userID = $_SESSION["UserID"] ?? ($_SESSION["userID"] ?? null);
if (!$userID) {
    die("Session expired. Please login again.");
}

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =======================
   LOAD LOGIN INFO
======================= */
$name = $_SESSION["Name"] ?? "Student";
$role = $_SESSION["Role"] ?? "student";

$stmt = $conn->prepare("SELECT Name, Role FROM login WHERE UserID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $name = $row["Name"];
    $role = $row["Role"];
}
$stmt->close();

$initials = strtoupper(substr($name, 0, 2));

/* =======================
   LOAD STUDENT INFO
======================= */
$programme = "";
$studentYear = "";

$stmt = $conn->prepare("SELECT programme, studentYear FROM student WHERE userID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($s = $res->fetch_assoc()) {
    $programme   = $s["programme"] ?? "";
    $studentYear = $s["studentYear"] ?? "";
}
$stmt->close();

/* =======================
   HANDLE SAVE
======================= */
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newName      = trim($_POST["username"] ?? "");
    $newProgramme = trim($_POST["programme"] ?? "");
    $newYear      = trim($_POST["studentYear"] ?? "");

    if ($newName === "") {
        $error = "Username cannot be empty.";
    } elseif ($newYear !== "" && (!ctype_digit($newYear) || $newYear < 1 || $newYear > 8)) {
        $error = "Year of study must be between 1 and 8.";
    } else {

        // Update username
        $updLogin = $conn->prepare("UPDATE login SET Name = ? WHERE UserID = ?");
        $updLogin->bind_param("ss", $newName, $userID);
        $updLogin->execute();
        $updLogin->close();

        // Update or insert student info
        $check = $conn->prepare("SELECT userID FROM student WHERE userID = ? LIMIT 1");
        $check->bind_param("s", $userID);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $upd = $conn->prepare("UPDATE student SET programme = ?, studentYear = ? WHERE userID = ?");
            $upd->bind_param("sss", $newProgramme, $newYear, $userID);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO student (userID, programme, studentYear) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $userID, $newProgramme, $newYear);
            $ins->execute();
            $ins->close();
        }

        // Update session immediately
        $_SESSION["Name"] = $newName;

        $name = $newName;
        $programme = $newProgramme;
        $studentYear = $newYear;

        $success = "Profile updated successfully.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Edit Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}
body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background: url("pic/red.avif") no-repeat center center fixed;
  background-size: cover;
}
.navbar{
  background:#fff;
  padding:12px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #fecaca;
  box-shadow:0 4px 14px rgba(248,113,113,0.25);
}
.nav-left{display:flex;align-items:center;gap:24px;}
.logo-box{border:1px solid #fecaca;border-radius:14px;padding:4px 8px;background:#fff;}
.logo-box img{height:40px;}
.nav-links{display:flex;gap:12px;}
.nav-link{text-decoration:none;color:#4b5563;padding:7px 16px;border-radius:999px;}
.nav-link:hover{background:#fee2e2;color:#b91c1c;}
.nav-right{display:flex;align-items:center;gap:10px;}
.avatar{
  width:38px;height:38px;border-radius:50%;
  border:2px solid #f87171;
  background:#fee2e2;
  display:flex;align-items:center;justify-content:center;
  color:#b91c1c;font-weight:700;
}
.logout-link{font-size:12px;color:#7f1d1d;text-decoration:none;}

.main{padding:30px;}

/* card */
.profile-card{
  max-width:980px;
  margin:auto;
  background:#fff;
  border-radius:20px;
  padding:24px;
  border:1px solid #fecaca;
  box-shadow:0 10px 24px rgba(248,113,113,0.18);
}

/* title */
.profile-title{font-size:20px;font-weight:800;color:#7f1d1d;}
.profile-sub{font-size:12px;color:#6b7280;margin-bottom:10px;}

/* icon on LEFT and form starts BELOW it */
.profile-icon{
  margin-top:10px;
  width:110px;
}
.profile-icon img{
  width:95px;
  display:block;
}

/* messages */
.msg{font-size:13px;font-weight:700;margin:12px 0 10px;}
.success{color:#15803d;}
.error{color:#b91c1c;}

/* form */
.form-view{margin-top:18px;max-width:520px;}
.row{display:grid;grid-template-columns:170px 1fr;gap:12px;margin-bottom:12px;align-items:center;}
.label{font-weight:800;font-size:13px;}
.input,.value-box{
  border:1px solid #e5e7eb;border-radius:10px;
  padding:10px 12px;font-size:13px;
}
.value-box{background:#f9fafb;}
.input:focus{border-color:#f87171;outline:none;}

.actions{display:flex;gap:12px;margin-top:18px;}
.btn-cancel{border:2px solid #111827;background:#fff;padding:10px 16px;border-radius:14px;font-weight:900;cursor:pointer;}
.btn-save{background:#dc2626;color:#fff;padding:10px 16px;border-radius:14px;border:none;font-weight:900;cursor:pointer;}
.btn-cancel:hover{background:#111827;color:#fff;}
.btn-save:hover{filter:brightness(0.95);}

@media(max-width:960px){
  .main{padding:20px 16px 28px;}
  .row{grid-template-columns:1fr;}
  .actions{flex-direction:column;align-items:stretch;}
}
</style>
</head>

<body>

<header class="navbar">
  <div class="nav-left">
    <div class="logo-box"><img src="pic/pentakom.png" alt="Logo"></div>
    <nav class="nav-links">
      <a href="student_board.php" class="nav-link">Dashboard</a>
      <a href="student_membership.php" class="nav-link">Membership</a>
      <a href="student_parking_booking.php" class="nav-link">Parking Booking</a>
      <a href="parking_summon.php" class="nav-link">Parking Summon</a>
    </nav>
  </div>
  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a class="logout-link" href="logout.php">Log out</a>
  </div>
</header>

<main class="main">
  <div class="profile-card">
    <div class="profile-title">Edit Profile</div>
    <div class="profile-sub">Welcome, <?php echo htmlspecialchars($name); ?></div>

    <!-- ICON ON LEFT, FORM BELOW -->
    <div class="profile-icon">
      <img src="pic/icon.svg" alt="Student Icon">
    </div>

    <?php if ($success): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" class="form-view">
      <div class="row">
        <div class="label">Username :</div>
        <input class="input" name="username" value="<?php echo htmlspecialchars($name); ?>">
      </div>

      <div class="row">
        <div class="label">Matric number :</div>
        <div class="value-box"><?php echo htmlspecialchars($userID); ?></div>
      </div>

      <div class="row">
        <div class="label">Role :</div>
        <div class="value-box"><?php echo htmlspecialchars($role); ?></div>
      </div>

      <div class="row">
        <div class="label">Year of study :</div>
        <input class="input" name="studentYear" value="<?php echo htmlspecialchars($studentYear); ?>">
      </div>

      <div class="row">
        <div class="label">Programme :</div>
        <input class="input" name="programme" value="<?php echo htmlspecialchars($programme); ?>">
      </div>

      <div class="actions">
        <button type="button" class="btn-cancel" onclick="location.href='student_profile.php'">CANCEL</button>
        <button type="submit" class="btn-save">SAVE</button>
      </div>
    </form>

  </div>
</main>

</body>
</html>
