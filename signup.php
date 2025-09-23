<?php
require 'conf.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name  = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM tbl_users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $msg = "Email already registered.";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_users(fullname,email,password) VALUES(?,?,?)");
        $stmt->bind_param("sss", $name, $email, $pass);
        if ($stmt->execute()) {
            $msg = "Account created. <a href='signin.php'>Sign in</a>";
        } else {
            $msg = "Error creating account.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up</title>
</head>
<body>
<h2>Recipe App – Sign Up</h2>
<form method="POST">
    <input type="text" name="fullname" placeholder="Full Name" required><br>
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Register</button>
</form>
<p style="color:red;"><?= $msg ?></p>
</body>
</html>