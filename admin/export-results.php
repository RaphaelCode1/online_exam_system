<?php
/**
 * Export Results Page
 * Download exam results to PDF or Excel
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canExportResults()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


// Load Composer autoloader
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Dompdf\Dompdf;
use Dompdf\Options;

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Get all exams for filter
$exams = $conn->query("SELECT id, title FROM exams ORDER BY created_at DESC");

// Get all students for filter
$students = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name");

// Get results data
$results = [];

if ($action === 'export') {
    if ($exam_id > 0 && $student_id > 0) {
        // Specific student, specific exam
        $stmt = $conn->prepare("SELECT ea.*, e.title as exam_title, e.passing_score, u.full_name, u.email, u.username 
                                FROM exam_attempts ea 
                                JOIN exams e ON ea.exam_id = e.id 
                                JOIN users u ON ea.student_id = u.id 
                                WHERE ea.exam_id = ? AND ea.student_id = ? AND ea.status = 'completed'");
        $stmt->bind_param("ii", $exam_id, $student_id);
    } elseif ($exam_id > 0) {
        // All students for specific exam
        $stmt = $conn->prepare("SELECT ea.*, e.title as exam_title, e.passing_score, u.full_name, u.email, u.username 
                                FROM exam_attempts ea 
                                JOIN exams e ON ea.exam_id = e.id 
                                JOIN users u ON ea.student_id = u.id 
                                WHERE ea.exam_id = ? AND ea.status = 'completed'");
        $stmt->bind_param("i", $exam_id);
    } elseif ($student_id > 0) {
        // Specific student, all exams
        $stmt = $conn->prepare("SELECT ea.*, e.title as exam_title, e.passing_score, u.full_name, u.email, u.username 
                                FROM exam_attempts ea 
                                JOIN exams e ON ea.exam_id = e.id 
                                JOIN users u ON ea.student_id = u.id 
                                WHERE ea.student_id = ? AND ea.status = 'completed'");
        $stmt->bind_param("i", $student_id);
    } else {
        // All results
        $stmt = $conn->prepare("SELECT ea.*, e.title as exam_title, e.passing_score, u.full_name, u.email, u.username 
                                FROM exam_attempts ea 
                                JOIN exams e ON ea.exam_id = e.id 
                                JOIN users u ON ea.student_id = u.id 
                                WHERE ea.status = 'completed' 
                                ORDER BY ea.created_at DESC");
    }
    
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['status_text'] = $row['passed'] ? 'Passed' : 'Failed';
            $row['grade'] = getGrade($row['percentage']);
            $results[] = $row;
        }
        $stmt->close();
    }
    
    if ($format === 'excel') {
        exportToExcel($results);
    } elseif ($format === 'pdf') {
        exportToPDF($results);
    }
}

function getGrade($percentage) {
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

function exportToExcel($results) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MissionTech College')
        ->setLastModifiedBy('MissionTech College')
        ->setTitle('Exam Results Report')
        ->setSubject('Exam Results')
        ->setDescription('Generated exam results report');
    
    // Set headers
    $headers = ['S/N', 'Student Name', 'Email', 'Username', 'Exam Title', 'Score', 'Total Questions', 'Percentage', 'Grade', 'Status', 'Date'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF10b981');
        $sheet->getStyle($col . '1')->getFont()->setColor(new Color(Color::COLOR_WHITE));
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    
    // Add data
    $row = 2;
    $sn = 1;
    foreach ($results as $result) {
        $sheet->setCellValue('A' . $row, $sn);
        $sheet->setCellValue('B' . $row, $result['full_name']);
        $sheet->setCellValue('C' . $row, $result['email']);
        $sheet->setCellValue('D' . $row, $result['username']);
        $sheet->setCellValue('E' . $row, $result['exam_title']);
        $sheet->setCellValue('F' . $row, $result['score']);
        $sheet->setCellValue('G' . $row, $result['total_questions']);
        $sheet->setCellValue('H' . $row, $result['percentage'] . '%');
        $sheet->setCellValue('I' . $row, $result['grade']);
        $sheet->setCellValue('J' . $row, $result['status_text']);
        $sheet->setCellValue('K' . $row, date('Y-m-d', strtotime($result['created_at'])));
        
        // Color coding for status
        if ($result['passed']) {
            $sheet->getStyle('J' . $row)->getFont()->getColor()->setARGB('FF10b981');
        } else {
            $sheet->getStyle('J' . $row)->getFont()->getColor()->setARGB('FFef4444');
        }
        
        $row++;
        $sn++;
    }
    
    // Add totals row
    $total_row = $row + 1;
    $sheet->setCellValue('A' . $total_row, 'TOTAL:');
    $sheet->setCellValue('B' . $total_row, count($results) . ' Records');
    $sheet->getStyle('A' . $total_row . ':B' . $total_row)->getFont()->setBold(true);
    
    // Set filename
    $filename = 'exam_results_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

function exportToPDF($results) {
    $dompdf = new Dompdf();
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf->setOptions($options);
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Exam Results Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #10b981; text-align: center; margin-bottom: 10px; }
            .subtitle { text-align: center; color: #64748b; margin-bottom: 30px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #10b981; color: white; }
            tr:nth-child(even) { background: #f9f9f9; }
            .passed { color: #10b981; font-weight: bold; }
            .failed { color: #ef4444; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
            .summary { margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; }
            .summary h3 { margin-bottom: 10px; color: #1e293b; }
        </style>
    </head>
    <body>
        <h1>MissionTech College</h1>
        <div class="subtitle">Exam Results Report</div>
        <div class="summary">
            <h3>Report Summary</h3>
            <p><strong>Generated on:</strong> ' . date('F j, Y H:i:s') . '</p>
            <p><strong>Total Records:</strong> ' . count($results) . '</p>
            <p><strong>Passed:</strong> ' . count(array_filter($results, function($r) { return $r['passed']; })) . '</p>
            <p><strong>Failed:</strong> ' . count(array_filter($results, function($r) { return !$r['passed']; })) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Exam Title</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Status</th>
                    <th>Date</th>
                </thead>
                <tbody>';
    
    $sn = 1;
    foreach ($results as $result) {
        $status_class = $result['passed'] ? 'passed' : 'failed';
        $status_text = $result['passed'] ? 'PASSED' : 'FAILED';
        $html .= '<tr>
                    <td>' . $sn . '</td>
                    <td>' . htmlspecialchars($result['full_name']) . '</td>
                    <td>' . htmlspecialchars($result['email']) . '</td>
                    <td>' . htmlspecialchars($result['exam_title']) . '</td>
                    <td>' . $result['score'] . '/' . $result['total_questions'] . '</td>
                    <td>' . $result['percentage'] . '%</td>
                    <td>' . $result['grade'] . '</td>
                    <td class="' . $status_class . '">' . $status_text . '</td>
                    <td>' . date('Y-m-d', strtotime($result['created_at'])) . '</td>
                </tr>';
        $sn++;
    }
    
    $html .= '</tbody>
        </table>
        <div class="footer">
            <p>This report was generated by the MissionTech College Online Examination System</p>
            <p>&copy; ' . date('Y') . ' MissionTech College. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('exam_results_' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
    exit();
}

$page_title = 'Export Results';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-download"></i> Export Results</h1>
        <p>Download exam results in Excel or PDF format</p>
    </div>
    
    <div class="card">
        <form method="GET" action="export-results.php">
            <input type="hidden" name="action" value="export">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Select Exam</label>
                    <select name="exam_id" class="form-control">
                        <option value="0">All Exams</option>
                        <?php while ($exam = $exams->fetch_assoc()): ?>
                        <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" class="form-control">
                        <option value="0">All Students</option>
                        <?php while ($student = $students->fetch_assoc()): ?>
                        <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Export Format</label>
                    <select name="format" class="form-control">
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="pdf">PDF (.pdf)</option>
                    </select>
                </div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Export Options:</strong><br>
                    - Excel format: Downloadable spreadsheet with all results<br>
                    - PDF format: Printable report with professional layout
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-download"></i> Export Results
            </button>
        </form>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}
.card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid #eef2f6;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
}
.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.info-box {
    background: #e0f2fe;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 12px;
    color: #0284c7;
}
.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(16,185,129,0.4);
}
</style>

<?php include '../includes/footer.php'; ?>