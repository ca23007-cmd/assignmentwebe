<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// hanya admin boleh access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name      = $_SESSION["Name"] ?? "Admin";
$initials  = strtoupper(substr($name, 0, 2));
$message   = "";

/* ===================== DELETE USER ===================== */
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    $del = $conn->prepare("DELETE FROM login WHERE UserID = ?");
    $del->bind_param("i", $deleteId);

    if ($del->execute()) {
        $message = "User ID {$deleteId} has been deleted.";
    } else {
        $message = "Error deleting user: " . $del->error;
    }
    $del->close();
}

/* ===================== ADD USER ===================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'add') {

    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $role     = trim($_POST["role"] ?? "");
    $fullName = trim($_POST["fullname"] ?? "");
    $matricID = trim($_POST["matricID"] ?? "");
    $accStat  = trim($_POST["account_status"] ?? ""); // belum guna dalam DB, optional text

    if ($username === "" || $password === "" || $role === "") {
        $message = "Please fill in username, password and role.";
    } else {

        // masukkan ke table login
        $stmt = $conn->prepare(
            "INSERT INTO login (Name, Password, Role) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $username, $password, $role);

        if ($stmt->execute()) {
            $newUserID = $stmt->insert_id;

            // ikut role, insert ke table lain
            if ($role === "admin") {

                $adminID = $fullName !== "" ? $fullName : ("ADM" . $newUserID);
                $yearSvc = 0;

                $stmt2 = $conn->prepare(
                    "INSERT INTO admin (UserID, adminID, year_of_service)
                     VALUES (?, ?, ?)"
                );
                $stmt2->bind_param("isi", $newUserID, $adminID, $yearSvc);
                $stmt2->execute();
                $stmt2->close();

            } elseif ($role === "student") {

                $studentYear = 1;
                $programme   = "";

                $stmt3 = $conn->prepare(
                    "INSERT INTO student (UserID, studentmatricnum, studentYear, programme)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt3->bind_param("isis", $newUserID, $matricID, $studentYear, $programme);
                $stmt3->execute();
                $stmt3->close();

            } elseif ($role === "safety_staff") {

                $department = "";
                $badge      = $matricID;

                $stmt4 = $conn->prepare(
                    "INSERT INTO safety_staff (UserID, department, badgeNumber)
                     VALUES (?, ?, ?)"
                );
                $stmt4->bind_param("iss", $newUserID, $department, $badge);
                $stmt4->execute();
                $stmt4->close();
            }

            $message = "New user added successfully. New UserID = {$newUserID}.";
        } else {
            $message = "Error adding user: " . $stmt->error;
        }
        $stmt->close();
    }
}

/* ===================== SEARCH & LIST USERS ===================== */
$search = trim($_GET['search'] ?? "");

$sql = "
    SELECT 
        l.UserID,
        l.Name,
        l.Role,
        s.studentmatricnum,
        st.badgeNumber,
        a.adminID
    FROM login l
    LEFT JOIN student s       ON l.UserID = s.UserID
    LEFT JOIN safety_staff st ON l.UserID = st.UserID
    LEFT JOIN admin a         ON l.UserID = a.UserID
";

$params = [];
$types  = "";

if ($search !== "") {
    $sql .= " WHERE l.Name LIKE ? OR s.studentmatricnum LIKE ? OR st.badgeNumber LIKE ? OR a.adminID LIKE ? ";
    $like   = "%".$search."%";
    $params = [$like, $like, $like, $like];
    $types  = "ssss";
}

$sql .= " ORDER BY l.UserID DESC";

$listStmt = $conn->prepare($sql);
if ($types !== "") {
    $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$result = $listStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MyPetakom – User Profile Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
    }
    body{
      min-height:100vh;
      display:flex;
      flex-direction:column;
      background:#fefce8;
    }
    .navbar{
      background:#ffffff;
      padding:12px 32px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      border-bottom:1px solid #facc15;
      box-shadow:0 4px 14px rgba(234,179,8,0.25);
    }
    .nav-left{
      display:flex;
      align-items:center;
      gap:24px;
    }
    .logo-box{
      padding:4px 8px;
      border-radius:14px;
      border:1px solid #facc15;
      background:#ffffff;
    }
    .logo-box img{
      height:40px;
      display:block;
    }
    .nav-links{
      display:flex;
      gap:12px;
      font-size:14px;
    }
    .nav-link{
      text-decoration:none;
      color:#4b5563;
      padding:7px 16px;
      border-radius:999px;
    }
    .nav-link:hover{
      background:#fef3c7;
      color:#92400e;
    }
    .nav-link.active{
      background:#eab308;
      color:#ffffff;
    }
    .nav-right{
      display:flex;
      align-items:center;
      gap:10px;
    }
    .avatar{
      width:38px;
      height:38px;
      border-radius:50%;
      border:2px solid #facc15;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#fef3c7;
      color:#92400e;
      font-weight:700;
      font-size:14px;
    }
    .logout-link{
      font-size:12px;
      color:#7f1d1d;
      text-decoration:none;
    }
    .main{
      flex:1;
      padding:26px 40px 40px;
      background:#fffbeb;
    }
    .page-header h2{
      font-size:22px;
      font-weight:700;
      color:#92400e;
      margin-bottom:4px;
    }
    .page-header p{
      font-size:13px;
      color:#6b7280;
      margin-bottom:22px;
    }
    .layout{
  display:flex;
  flex-direction:column;
  gap:24px;
}

    .panel{
      background:#ffffff;
      border-radius:16px;
      border:1px solid #fde68a;
      box-shadow:0 6px 18px rgba(234,179,8,0.2);
      padding:18px 20px;
    }
    .panel h3{
      font-size:15px;
      font-weight:600;
      color:#92400e;
      margin-bottom:8px;
    }
    .search-row{
      display:flex;
      gap:10px;
      margin-bottom:12px;
    }
    .search-row input{
      flex:1;
      padding:8px 10px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      font-size:13px;
    }
    .btn{
      padding:8px 16px;
      border-radius:999px;
      border:none;
      font-size:13px;
      cursor:pointer;
    }
    .btn-primary{
      background:#eab308;
      color:#fff;
    }
    .btn-secondary{
      background:#e5e7eb;
      color:#374151;
    }
    table{
      width:100%;
      border-collapse:collapse;
      font-size:13px;
    }
    th,td{
      padding:8px 8px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
    }
    thead{
      background:#fef3c7;
    }
    th{
      font-weight:600;
      color:#4b5563;
    }
    tbody tr:nth-child(even){
      background:#fffbeb;
    }
    .text-right{
      text-align:right;
    }
    .message{
      margin-bottom:12px;
      font-size:13px;
      color:#b45309;
    }
    .btn-sm{
  padding:4px 10px;
  font-size:12px;
}

    .form-group{
      margin-bottom:10px;
    }
    .form-group label{
      display:block;
      font-size:13px;
      margin-bottom:4px;
      color:#374151;
    }
    .form-group input,
    .form-group select{
      width:100%;
      padding:8px 10px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      font-size:13px;
    }
    @media(max-width:960px){
      .main{
        padding:20px 16px 28px;
      }
      
      }
    
  </style>

  <script>
    // sama macam dashboard: tekan back → logout
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
      <img src="pic/pentakom.png" alt="Logo UMP x PETAKOM">
    </div>

    <nav class="nav-links">
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="admin_user_profile.php" class="nav-link active">User Profile Management</a>
      <a href="#" class="nav-link">Parking Areas</a>
      <a href="#" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>User Profile Management</h2>
    <p>Search, create and remove user accounts for the parking system.</p>
  </div>

  <?php if ($message !== ""): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <div class="layout">
    <!-- LEFT: Senarai user + search -->
    <section class="panel">
      <h3>Existing Users</h3>

      <form method="get" class="search-row">
        <input type="text" name="search" placeholder="Search by name / matric / badge / adminID"
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
      </form>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Matric / Badge / AdminID</th>
              <th class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="5">No users found.</td></tr>
          <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['UserID']); ?></td>
                <td><?php echo htmlspecialchars($row['Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Role']); ?></td>
                <td>
                  <?php
                    if ($row['Role'] === 'student' && $row['studentmatricnum']) {
                        echo htmlspecialchars($row['studentmatricnum']);
                    } elseif ($row['Role'] === 'safety_staff' && $row['badgeNumber']) {
                        echo htmlspecialchars($row['badgeNumber']);
                    } elseif ($row['Role'] === 'admin' && $row['adminID']) {
                        echo htmlspecialchars($row['adminID']);
                    } else {
                        echo "-";
                    }
                  ?>
                </td>
                <td class="text-right">
                 <a href="admin_user_profile.php?delete=<?php echo (int)$row['UserID']; ?>"
   class="btn btn-secondary btn-sm"
   onclick="return confirm('Delete this user?');">Delete</a>

                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- RIGHT: Form add user -->
    <section class="panel">
      <h3>Create New User</h3>

      <form method="post">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
          <label for="fullname">Fullname (for adminID display, optional)</label>
          <input type="text" id="fullname" name="fullname">
        </div>

        <div class="form-group">
          <label for="username">Username (login Name)</label>
          <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
          <label for="matricID">Matric ID / Badge (student or safety staff)</label>
          <input type="text" id="matricID" name="matricID">
        </div>

        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required>
            <option value="" disabled selected>Select role</option>
            <option value="admin">Admin</option>
            <option value="student">Student</option>
            <option value="safety_staff">Safety Staff</option>
          </select>
        </div>

        <div class="form-group">
          <label for="account_status">Account status (optional text only)</label>
          <input type="text" id="account_status" name="account_status" placeholder="active / suspended ...">
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
          <button type="reset" class="btn btn-secondary">Clear</button>
          <button type="submit" class="btn btn-primary">Add new user</button>
        </div>
      </form>
    </section>
  </div>
</main>

</body>
</html>
<?php
$listStmt->close();
$conn->close();
?>
