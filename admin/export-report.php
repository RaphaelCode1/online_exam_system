<?php
/**
 * Export Student Progress Report
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

if ($student_id <= 0) {
    header('Location: progress-report.php');
    exit();
}

// Get student details
$student = $conn->query("SELECT * FROM users WHERE id = $student_id AND role = 'student'")->fetch_assoc();
if (!$student) {
    header('Location: progress-report.php');
    exit();
}

// Get student stats
$stats = $conn->query("SELECT 
                        COUNT(DISTINCT exam_id) as total_exams,
                        COUNT(*) as total_attempts,
                        AVG(percentage) as avg_percentage,
                        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as exams_passed,
                        MAX(percentage) as best_score
                       FROM exam_attempts 
                       WHERE student_id = $student_id AND status = 'completed'")->fetch_assoc();

// Get exam history
$exam_history = $conn->query("SELECT ea.*, e.title as exam_title 
                              FROM exam_attempts ea 
                              JOIN exams e ON ea.exam_id = e.id 
                              WHERE ea.student_id = $student_id AND ea.status = 'completed' 
                              ORDER BY ea.created_at DESC");

// Get subject performance
$subject_performance = $conn->query("SELECT 
                                      s.name as subject_name,
                                      AVG(ea.percentage) as avg_score
                                     FROM exam_attempts ea
                                     JOIN exams e ON ea.exam_id = e.id
                                     JOIN subjects s ON e.subject_id = s.id
                                     WHERE ea.student_id = $student_id AND ea.status = 'completed'
                                     GROUP BY s.id");

// Get achievements
$achievements = $conn->query("SELECT a.* 
                              FROM student_achievements sa 
                              JOIN achievements a ON sa.achievement_id = a.id 
                              WHERE sa.student_id = $student_id");

if ($format === 'excel') {
    exportToExcel($student, $stats, $exam_history, $subject_performance, $achievements);
} else {
    exportToPDF($student, $stats, $exam_history, $subject_performance, $achievements);
}

function exportToExcel($student, $stats, $exam_history, $subject_performance, $achievements) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Title
    $sheet->setCellValue('A1', 'Student Progress Report');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Student Info
    $sheet->setCellValue('A3', 'Student Name:');
    $sheet->setCellValue('B3', $student['full_name']);
    $sheet->setCellValue('A4', 'Email:');
    $sheet->setCellValue('B4', $student['email']);
    $sheet->setCellValue('A5', 'Username:');
    $sheet->setCellValue('B5', $student['username']);
    $sheet->setCellValue('A6', 'Member Since:');
    $sheet->setCellValue('B6', date('F j, Y', strtotime($student['created_at'])));
    
    // Stats
    $sheet->setCellValue('D3', 'Total Exams:');
    $sheet->setCellValue('E3', $stats['total_exams'] ?? 0);
    $sheet->setCellValue('D4', 'Exams Passed:');
    $sheet->setCellValue('E4', $stats['exams_passed'] ?? 0);
    $sheet->setCellValue('D5', 'Average Score:');
    $sheet->setCellValue('E5', round($stats['avg_percentage'] ?? 0, 1) . '%');
    $sheet->setCellValue('D6', 'Best Score:');
    $sheet->setCellValue('E6', ($stats['best_score'] ?? 0) . '%');
    
    // Subject Performance
    $row = 9;
    $sheet->setCellValue('A' . $row, 'Subject-wise Performance');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row += 2;
    
    $sheet->setCellValue('A' . $row, 'Subject');
    $sheet->setCellValue('B' . $row, 'Average Score');
    $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
    $row++;
    
    while ($subj = $subject_performance->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $subj['subject_name']);
        $sheet->setCellValue('B' . $row, round($subj['avg_score'], 1) . '%');
        $row++;
    }
    
    // Exam History
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Exam History');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row += 2;
    
    $headers = ['Exam Title', 'Date', 'Score', 'Percentage', 'Correct/Wrong', 'Result'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $col++;
    }
    $row++;
    
    while ($exam = $exam_history->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $exam['exam_title']);
        $sheet->setCellValue('B' . $row, date('M j, Y', strtotime($exam['created_at'])));
        $sheet->setCellValue('C' . $row, $exam['score'] . '/' . $exam['total_questions']);
        $sheet->setCellValue('D' . $row, $exam['percentage'] . '%');
        $sheet->setCellValue('E' . $row, $exam['correct_answers'] . '/' . $exam['wrong_answers']);
        $sheet->setCellValue('F' . $row, $exam['passed'] ? 'Passed' : 'Failed');
        $row++;
    }
    
    // Achievements
    if ($achievements->num_rows > 0) {
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Achievements Earned');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row += 2;
        
        $sheet->setCellValue('A' . $row, 'Achievement');
        $sheet->setCellValue('B' . $row, 'Description');
        $sheet->setCellValue('C' . $row, 'Points');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        
        while ($ach = $achievements->fetch_assoc()) {
            $sheet->setCellValue('A' . $row, $ach['name']);
            $sheet->setCellValue('B' . $row, $ach['description']);
            $sheet->setCellValue('C' . $row, $ach['points']);
            $row++;
        }
    }
    
    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $filename = 'student_report_' . $student['username'] . '_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

function exportToPDF($student, $stats, $exam_history, $subject_performance, $achievements) {
    $dompdf = new Dompdf();
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf->setOptions($options);
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Student Progress Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #10b981; text-align: center; }
            .header { text-align: center; margin-bottom: 30px; }
            .student-info { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
            .stat-card { background: #f1f5f9; padding: 10px; text-align: center; border-radius: 8px; }
            .stat-value { font-size: 1.5rem; font-weight: bold; color: #10b981; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #10b981; color: white; }
            .passed { color: #10b981; font-weight: bold; }
            .failed { color: #ef4444; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
        </style>
    </head>
    <body>
        <h1>MissionTech College</h1>
        <div class="header">
            <h2>Student Progress Report</h2>
            <p>Generated on: ' . date('F j, Y H:i:s') . '</p>
        </div>
        
        <div class="student-info">
            <h3>Student Information</h3>
            <p><strong>Name:</strong> ' . htmlspecialchars($student['full_name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($student['email']) . '</p>
            <p><strong>Username:</strong> ' . htmlspecialchars($student['username']) . '</p>
            <p><strong>Member Since:</strong> ' . date('F j, Y', strtotime($student['created_at'])) . '</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value">' . ($stats['total_exams'] ?? 0) . '</div><div>Exams Taken</div></div>
            <div class="stat-card"><div class="stat-value">' . ($stats['exams_passed'] ?? 0) . '</div><div>Exams Passed</div></div>
            <div class="stat-card"><div class="stat-value">' . round($stats['avg_percentage'] ?? 0, 1) . '%</div><div>Average Score</div></div>
            <div class="stat-card"><div class="stat-value">' . ($stats['best_score'] ?? 0) . '%</div><div>Best Score</div></div>
        </div>
        
        <h3>Subject Performance</h3>
        <table>
            <thead><tr><th>Subject</th><th>Average Score</th></tr></thead>
            <tbody>';
    
    while ($subj = $subject_performance->fetch_assoc()) {
        $html .= '<tr><td>' . htmlspecialchars($subj['subject_name']) . '</td><td>' . round($subj['avg_score'], 1) . '%</td></tr>';
    }
    
    $html .= '</tbody></table>
        
        <h3>Exam History</h3>
        <table>
            <thead><tr><th>Exam Title</th><th>Date</th><th>Score</th><th>Percentage</th><th>Result</th></tr></thead>
            <tbody>';
    
    while ($exam = $exam_history->fetch_assoc()) {
        $status_class = $exam['passed'] ? 'passed' : 'failed';
        $status_text = $exam['passed'] ? 'Passed' : 'Failed';
        $html .= '<tr>
                    <td>' . htmlspecialchars($exam['exam_title']) . '</td>
                    <td>' . date('M j, Y', strtotime($exam['created_at'])) . '</td>
                    <td>' . $exam['score'] . '/' . $exam['total_questions'] . '</td>
                    <td>' . $exam['percentage'] . '%</td>
                    <td class="' . $status_class . '">' . $status_text . '</td>
                  </tr>';
    }
    
    $html .= '</tbody></table>';
    
    if ($achievements->num_rows > 0) {
        $html .= '<h3>Achievements Earned</h3><table><thead><tr><th>Achievement</th><th>Description</th><th>Points</th></tr></thead><tbody>';
        while ($ach = $achievements->fetch_assoc()) {
            $html .= '<tr><td>' . htmlspecialchars($ach['name']) . '</td><td>' . htmlspecialchars($ach['description']) . '</td><td>' . $ach['points'] . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    
    $html .= '<div class="footer">
            <p>This report was generated by the MissionTech College Online Examination System</p>
            <p>&copy; ' . date('Y') . ' MissionTech College. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('student_report_' . $student['username'] . '_' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
    exit();
}
?>