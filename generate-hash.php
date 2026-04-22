<?php
// generate-hash.php - Generate bcrypt hash for a password
$password1 = 'OES2026';
$hash1 = password_hash($password1, PASSWORD_DEFAULT);
$password2 = 'Student123';
$hash2 = password_hash($password2, PASSWORD_DEFAULT);

echo "Password1: " . $password1 . "<br>Hash1: " . $hash1 . "<br>Password2: " . $password2 . "<br>Hash2: " . $hash2 . "<br>";

echo "<br>SQL to update admin password:<br>";
echo "UPDATE users SET password = '" . $hash1 . "' WHERE email = 'missiontech.admin@gmail.com';";
?>