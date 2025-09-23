<?php
require 'conf.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, fullname, password FROM tbl_users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['fullname'];
            header("Location: verify.php");
            exit;
        } else {
            $msg = "Invalid password.";
        }
    } else {
        $msg = "No account found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Sign In</title></head>
<body>
<h2>Recipe App – Sign In</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Login</button>
</form>
<p style="color:red;"><?= $msg ?></p>
</body>
</html>
<?php
session_start();
