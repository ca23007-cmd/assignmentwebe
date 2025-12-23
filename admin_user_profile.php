<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// only admin can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name     = $_SESSION["Name"] ?? "Admin";
$initials = strtoupper(substr($name, 0, 2));
$message  = "";

/* ===================== DELETE USER ===================== */
if (isset($_GET['delete'])) {
    $deleteId = trim($_GET['delete']); // VARCHAR userID

    $del = $conn->prepare("DELETE FROM login WHERE UserID = ?");
    $del->bind_param("s", $deleteId);

    if ($del->execute()) {
        $message = "User ID {$deleteId} has been deleted.";
    } else {
        $message = "Error deleting user: " . $del->error;
    }
    $del->close();
}

/* ===================== ADD USER (HASHING ENABLED) ===================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'add') {

    $userID       = trim($_POST["userid"] ?? "");
    $username     = trim($_POST["username"] ?? "");
    $password     = trim($_POST["password"] ?? "");
    $role         = trim($_POST["role"] ?? "");

    // dynamic fields
    $programme    = trim($_POST["programme"] ?? "");
    $yearSvcInput = trim($_POST["year_of_service"] ?? "");
    $department   = trim($_POST["department"] ?? "");

    if ($userID === "" || $username === "" || $password === "" || $role === "") {
        $message = "Please fill in UserID, Username, Password and Role.";
    } else {

        // normalize role
        $role = strtolower(trim($role));
        $role = str_replace(" ", "_", $role);

        // validate role-specific required fields
        if ($role === "student" && $programme === "") {
            $message = "Programme is required for Student.";
        } elseif ($role === "admin" && $yearSvcInput === "") {
            $message = "Year of service is required for Admin.";
        } elseif ($role === "safety_staff" && $department === "") {
            $message = "Department is required for Safety Staff.";
        } else {

            $year_of_service = 0;
            if ($role === "admin") {
                $year_of_service = (int)$yearSvcInput;
                if ($year_of_service < 0) $year_of_service = 0;
            }

            // hashing enabled
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // insert into login
            $stmt = $conn->prepare("INSERT INTO login (UserID, Name, Password, Role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $userID, $username, $hashedPassword, $role);

            if ($stmt->execute()) {

                // insert into role tables (PK=FK=userID)
                if ($role === "admin") {
                    $stmt2 = $conn->prepare("INSERT INTO admin (UserID, year_of_service) VALUES (?, ?)");
                    $stmt2->bind_param("si", $userID, $year_of_service);
                    $stmt2->execute();
                    $stmt2->close();

                } elseif ($role === "student") {
                    $studentYear = 1;
                    $stmt3 = $conn->prepare("INSERT INTO student (UserID, studentYear, programme) VALUES (?, ?, ?)");
                    $stmt3->bind_param("sis", $userID, $studentYear, $programme);
                    $stmt3->execute();
                    $stmt3->close();

                } elseif ($role === "safety_staff") {
                    $stmt4 = $conn->prepare("INSERT INTO safety_staff (UserID, department) VALUES (?, ?)");
                    $stmt4->bind_param("ss", $userID, $department);
                    $stmt4->execute();
                    $stmt4->close();
                }

                $message = "New user added successfully. UserID = {$userID}.";
            } else {
                $message = "Error adding user: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

/* ===================== SEARCH & LIST USERS ===================== */
$search = trim($_GET['search'] ?? "");

$sql = "
    SELECT
        l.UserID,
        l.Name,
        l.Role,
        s.programme,
        a.year_of_service,
        st.department
    FROM login l
    LEFT JOIN student s      ON l.UserID = s.UserID
    LEFT JOIN admin a        ON l.UserID = a.UserID
    LEFT JOIN safety_staff st ON l.UserID = st.UserID
";

$params = [];
$types  = "";

if ($search !== "") {
    $sql .= " WHERE l.UserID LIKE ? OR l.Name LIKE ? OR l.Role LIKE ? OR s.programme LIKE ? OR st.department LIKE ? ";
    $like   = "%".$search."%";
    $params = [$like, $like, $like, $like, $like];
    $types  = "sssss";
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
    *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}
   body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background: url("pic/yellow.avif") no-repeat center center fixed;
  background-size: cover;
}
    .navbar{background:#ffffff;padding:12px 32px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #facc15;box-shadow:0 4px 14px rgba(234,179,8,0.25);}
    .nav-left{display:flex;align-items:center;gap:24px;}
    .logo-box{padding:4px 8px;border-radius:14px;border:1px solid #facc15;background:#ffffff;}
    .logo-box img{height:40px;display:block;}
    .nav-links{display:flex;gap:12px;font-size:14px;}
    .nav-link{text-decoration:none;color:#4b5563;padding:7px 16px;border-radius:999px;}
    .nav-link:hover{background:#fef3c7;color:#92400e;}
    .nav-link.active{background:#eab308;color:#ffffff;}
    .nav-right{display:flex;align-items:center;gap:10px;}
    .avatar{width:38px;height:38px;border-radius:50%;border:2px solid #facc15;display:flex;align-items:center;justify-content:center;background:#fef3c7;color:#92400e;font-weight:700;font-size:14px;}
    .logout-link{font-size:12px;color:#7f1d1d;text-decoration:none;}
    .avatar-link{
  text-decoration:none;
  display:inline-block;
}


    .main{
  flex:1;
  padding:26px 40px 40px;
  background: transparent;
}
    .page-header h2{font-size:22px;font-weight:700;color:#92400e;margin-bottom:4px;}
    .page-header p{font-size:13px;color:#6b7280;margin-bottom:22px;}
    .layout{display:flex;flex-direction:column;gap:24px;}
    .panel{background:#ffffff;border-radius:16px;border:1px solid #fde68a;box-shadow:0 6px 18px rgba(234,179,8,0.2);padding:18px 20px;}
    .panel h3{font-size:15px;font-weight:600;color:#92400e;margin-bottom:8px;}

    .search-row{display:flex;gap:10px;margin-bottom:12px;}
    .search-row input{flex:1;padding:8px 10px;border-radius:10px;border:1px solid #e5e7eb;font-size:13px;}
    .btn{padding:8px 16px;border-radius:999px;border:none;font-size:13px;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-primary{background:#eab308;color:#fff;}
    .btn-secondary{background:#e5e7eb;color:#374151;}

    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{padding:8px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;}
    thead{background:#fef3c7;}
    th{font-weight:600;color:#4b5563;}
    tbody tr:nth-child(even){background:#fffbeb;}
    .text-right{text-align:right;}
    .message{margin-bottom:12px;font-size:13px;color:#b45309;}
    .btn-sm{padding:4px 10px;font-size:12px;}

    .form-group{margin-bottom:10px;}
    .form-group label{display:block;font-size:13px;margin-bottom:4px;color:#374151;}
    .form-group input,.form-group select{width:100%;padding:8px 10px;border-radius:10px;border:1px solid #e5e7eb;font-size:13px;}

    .hint{font-size:12px;color:#6b7280;margin-top:6px;}
    .hidden{display:none;}

    @media(max-width:960px){.main{padding:20px 16px 28px;}}
  </style>

  <script>
    // back button -> logout
    window.addEventListener("load", function () {
      history.pushState(null, "", location.href);
      window.onpopstate = function () {
        window.location.href = "logout.php";
      };

      // role-based dynamic fields
      const roleSelect = document.getElementById("role");
      const studentBox = document.getElementById("studentFields");
      const adminBox   = document.getElementById("adminFields");
      const staffBox   = document.getElementById("staffFields");

      const programme  = document.getElementById("programme");
      const yearSvc    = document.getElementById("year_of_service");
      const department = document.getElementById("department");

      function setRequired(el, required) {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
      }

      function updateFields() {
        const v = roleSelect.value;

        studentBox.classList.add("hidden");
        adminBox.classList.add("hidden");
        staffBox.classList.add("hidden");

        setRequired(programme, false);
        setRequired(yearSvc, false);
        setRequired(department, false);

        if (v === "student") {
          studentBox.classList.remove("hidden");
          setRequired(programme, true);
        } else if (v === "admin") {
          adminBox.classList.remove("hidden");
          setRequired(yearSvc, true);
        } else if (v === "safety_staff") {
          staffBox.classList.remove("hidden");
          setRequired(department, true);
        }
      }

      roleSelect.addEventListener("change", updateFields);
      updateFields();
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
      <a href="parking_area.php" class="nav-link">Parking Areas</a>
      <a href="parking_space.php" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

 <div class="nav-right">
  <a href="admin_profile.php" class="avatar-link" title="Admin Profile">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
  </a>
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
    <!-- LIST -->
    <section class="panel">
      <h3>Existing Users</h3>

      <form method="get" class="search-row">
        <input type="text" name="search" placeholder="Search by userID / name / role / programme / department"
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
      </form>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>UserID (Matric/Badge/Admin Code)</th>
              <th>Username</th>
              <th>Role</th>
              <th>Details</th>
              <th class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="5">No users found.</td></tr>
          <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $r = strtolower(trim($row['Role']));
                $r = str_replace(" ", "_", $r);

                $details = "-";
                if ($r === "student") {
                    $details = ($row['programme'] !== null && $row['programme'] !== "") ? ("Programme: ".$row['programme']) : "Programme: -";
                } elseif ($r === "admin") {
                    $details = "Year of service: ".(string)($row['year_of_service'] ?? 0);
                } elseif ($r === "safety_staff") {
                    $details = ($row['department'] !== null && $row['department'] !== "") ? ("Department: ".$row['department']) : "Department: -";
                }
              ?>
              <tr>
                <td><?php echo htmlspecialchars($row['UserID']); ?></td>
                <td><?php echo htmlspecialchars($row['Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Role']); ?></td>
                <td><?php echo htmlspecialchars($details); ?></td>
                <td class="text-right">
                  <a href="admin_user_profile.php?delete=<?php echo urlencode($row['UserID']); ?>"
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

    <!-- CREATE -->
    <section class="panel">
      <h3>Create New User</h3>

      <form method="post">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
          <label for="userid">UserID (Matric / Badge / AdminID)</label>
          <input type="text" id="userid" name="userid" placeholder="e.g. CA24005 / ADM01 / SS001" required>
        </div>

        <div class="form-group">
          <label for="username">Username (login Name)</label>
          <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
          <label for="password">Password </label>
          <input type="password" id="password" name="password" required>
        </div>

        <!-- dynamic area -->
        <div id="studentFields" class="hidden">
          <div class="form-group">
            <label for="programme">Programme (student)</label>
            <input type="text" id="programme" name="programme" placeholder="e.g. Software Engineering">
            <div class="hint">This will be stored in Student table.</div>
          </div>
        </div>

        <div id="adminFields" class="hidden">
          <div class="form-group">
            <label for="year_of_service">Year of service (admin)</label>
            <input type="number" id="year_of_service" name="year_of_service" min="0" placeholder="e.g. 3">
            <div class="hint">This will be stored in Admin table.</div>
          </div>
        </div>

        <div id="staffFields" class="hidden">
          <div class="form-group">
            <label for="department">Department (safety staff)</label>
            <input type="text" id="department" name="department" placeholder="e.g. Security Unit">
            <div class="hint">This will be stored in Safety Staff table.</div>
          </div>
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
