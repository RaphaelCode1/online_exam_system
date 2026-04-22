<?php
require_once '../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

$topics = [];
if ($subject_id > 0) {
    $result = $conn->query("SELECT id, name FROM topics WHERE subject_id = $subject_id AND status = 1 ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($topics);
?>