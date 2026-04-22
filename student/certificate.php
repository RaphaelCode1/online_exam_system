<?php
/**
 * Certificate Page - Fixed Version
 * List all certificates and allow viewing/downloading
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Helper function for grade
function getGrade($percentage) {
    if ($percentage >= 80) return 'A (Excellent)';
    if ($percentage >= 70) return 'B (Very Good)';
    if ($percentage >= 60) return 'C (Good)';
    if ($percentage >= 50) return 'D (Satisfactory)';
    return 'F (Needs Improvement)';
}

// If exam_id is provided, show single certificate view
if ($exam_id > 0) {
    // Get student details
    $student = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

    // Get exam attempt details - only passed exams
    $attempt = $conn->query("SELECT ea.*, e.title as exam_title, e.passing_score 
                             FROM exam_attempts ea 
                             JOIN exams e ON ea.exam_id = e.id 
                             WHERE ea.student_id = $user_id AND ea.exam_id = $exam_id AND ea.passed = 1 AND ea.status = 'completed'
                             ORDER BY ea.created_at DESC LIMIT 1")->fetch_assoc();

    // If no passed attempt found, redirect to certificate list
    if (!$attempt) {
        $_SESSION['error'] = "Certificate not available. You need to pass the exam to get a certificate.";
        header('Location: certificate.php');
        exit();
    }

    // Check if certificate already exists
    $certificate = $conn->query("SELECT * FROM certificates WHERE student_id = $user_id AND exam_id = $exam_id")->fetch_assoc();

    if (!$certificate) {
        // Generate new certificate
        $certificate_no = 'MTC-' . strtoupper(uniqid()) . '-' . date('Ymd');
        $grade = getGrade($attempt['percentage']);
        
        $stmt = $conn->prepare("INSERT INTO certificates (certificate_no, student_id, exam_id, attempt_id, score, percentage, grade, issue_date, verification_code) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $verification_code = md5($certificate_no . $user_id . $exam_id);
        $stmt->bind_param("siiidiss", $certificate_no, $user_id, $exam_id, $attempt['id'], $attempt['score'], $attempt['percentage'], $grade, $verification_code);
        $stmt->execute();
        $certificate_id = $stmt->insert_id;
        $stmt->close();
        
        // Get the new certificate
        $certificate = $conn->query("SELECT * FROM certificates WHERE id = $certificate_id")->fetch_assoc();
    }

    $page_title = 'Certificate of Completion';
    include '../includes/header.php';
    ?>
    
    <div class="container">
        <div class="certificate-wrapper">
            <div class="certificate" id="certificate">
                <div class="certificate-border">
                    <div class="certificate-content">
                        <div class="certificate-header">
                            <div class="college-logo">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h1>MissionTech College</h1>
                            <p>Online Examination System</p>
                        </div>
                        
                        <div class="certificate-title">
                            <h2>CERTIFICATE OF COMPLETION</h2>
                        </div>
                        
                        <div class="certificate-body">
                            <p>This certificate is proudly presented to</p>
                            <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                            <p>for successfully completing the examination</p>
                            <h4><?php echo htmlspecialchars($attempt['exam_title']); ?></h4>
                            
                            <div class="certificate-details">
                                <div class="detail-item">
                                    <span class="detail-label">Score:</span>
                                    <span class="detail-value"><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Percentage:</span>
                                    <span class="detail-value"><?php echo $attempt['percentage']; ?>%</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Grade:</span>
                                    <span class="detail-value"><?php echo getGrade($attempt['percentage']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value"><?php echo date('F j, Y', strtotime($attempt['created_at'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Certificate No:</span>
                                    <span class="detail-value"><?php echo $certificate['certificate_no']; ?></span>
                                </div>
                            </div>
                            
                            <div class="signatures">
                                <div class="signature">
                                    <div class="signature-line"></div>
                                    <p>Exam Coordinator</p>
                                </div>
                                <div class="signature">
                                    <div class="signature-line"></div>
                                    <p>Dean of Academics</p>
                                </div>
                            </div>
                            
                            <div class="qr-code">
                                <?php
                                $qr_data = "https://localhost/online_exam_system/verify-certificate.php?code=" . $certificate['verification_code'];
                                $qr_code_url = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($qr_data) . "&choe=UTF-8";
                                ?>
                                <img src="<?php echo $qr_code_url; ?>" alt="Verification QR Code">
                                <p>Scan to verify</p>
                            </div>
                        </div>
                        
                        <div class="certificate-footer">
                            <p>This certificate is issued to recognize the achievement and can be verified online.</p>
                            <p>© <?php echo date('Y'); ?> MissionTech College</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="certificate-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="certificate.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Certificates
                </a>
            </div>
        </div>
    </div>

<?php } else { 
    // Show all certificates list
    $certificates = $conn->query("
        SELECT c.*, e.title as exam_title, ea.percentage, ea.score, ea.total_questions, ea.created_at as exam_date
        FROM certificates c
        JOIN exams e ON c.exam_id = e.id
        JOIN exam_attempts ea ON c.attempt_id = ea.id
        WHERE c.student_id = $user_id
        ORDER BY c.issue_date DESC
    ");
    
    $page_title = 'My Certificates';
    include '../includes/header.php';
    ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-certificate"></i> My Certificates</h1>
            <p>View and download your achievement certificates</p>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($certificates->num_rows > 0): ?>
            <div class="certificates-grid">
                <?php while ($cert = $certificates->fetch_assoc()): 
                    $grade = getGrade($cert['percentage']);
                ?>
                    <div class="certificate-card">
                        <div class="certificate-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="certificate-info">
                            <h3><?php echo htmlspecialchars($cert['exam_title']); ?></h3>
                            <div class="certificate-details">
                                <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($cert['issue_date'])); ?></span>
                                <span><i class="fas fa-percent"></i> Score: <?php echo $cert['percentage']; ?>%</span>
                                <span><i class="fas fa-star"></i> Grade: <?php echo $grade; ?></span>
                                <span><i class="fas fa-hashtag"></i> No: <?php echo $cert['certificate_no']; ?></span>
                            </div>
                            <div class="certificate-actions">
                                <a href="certificate.php?exam_id=<?php echo $cert['exam_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button onclick="shareCertificate('<?php echo $cert['certificate_no']; ?>')" class="btn-share">
                                    <i class="fas fa-share-alt"></i> Share
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-certificate"></i>
                <h3>No Certificates Yet</h3>
                <p>You haven't earned any certificates yet. Pass an exam to receive a certificate!</p>
                <a href="exams.php" class="btn-primary">Take an Exam</a>
            </div>
        <?php endif; ?>
    </div>
<?php } ?>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

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

.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.certificate-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    display: flex;
    gap: 1rem;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.certificate-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10b981, #f59e0b);
}

.certificate-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.certificate-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #f59e0b;
}

.certificate-info {
    flex: 1;
}

.certificate-info h3 {
    font-size: 1.1rem;
    color: #1e293b;
    margin-bottom: 0.75rem;
}

.certificate-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.certificate-details span {
    font-size: 0.7rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f8fafc;
    padding: 4px 8px;
    border-radius: 30px;
}

.certificate-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-view, .btn-share {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.btn-view {
    background: #f1f5f9;
    color: #1e293b;
}

.btn-view:hover {
    background: #e2e8f0;
}

.btn-share {
    background: #3b82f6;
    color: white;
}

.btn-share:hover {
    background: #2563eb;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 1.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-error {
    background: #fef2f2;
    color: #ef4444;
    border-left: 4px solid #ef4444;
}

/* Certificate View Styles */
.certificate-wrapper {
    max-width: 900px;
    margin: 2rem auto;
}

.certificate {
    background: white;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.certificate-border {
    border: 15px double #10b981;
    padding: 2rem;
    position: relative;
}

.certificate-border::before {
    content: '';
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    bottom: 10px;
    border: 1px solid #e2e8f0;
    pointer-events: none;
}

.certificate-content {
    text-align: center;
}

.certificate-header {
    margin-bottom: 2rem;
}

.college-logo i {
    font-size: 4rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.certificate-header h1 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.certificate-header p {
    color: #64748b;
}

.certificate-title h2 {
    font-size: 1.8rem;
    color: #10b981;
    letter-spacing: 4px;
    margin-bottom: 2rem;
    border-bottom: 2px solid #10b981;
    display: inline-block;
    padding-bottom: 0.5rem;
}

.certificate-body p {
    color: #475569;
    font-size: 1.1rem;
    margin: 0.5rem 0;
}

.certificate-body h3 {
    font-size: 2rem;
    color: #1e293b;
    margin: 1rem 0;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.certificate-body h4 {
    font-size: 1.3rem;
    color: #10b981;
    margin-bottom: 2rem;
}

.certificate-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
}

.detail-item {
    text-align: center;
}

.detail-label {
    font-weight: 600;
    color: #64748b;
    display: block;
    margin-bottom: 0.25rem;
}

.detail-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
}

.signatures {
    display: flex;
    justify-content: center;
    gap: 4rem;
    margin: 2rem 0;
}

.signature {
    text-align: center;
}

.signature-line {
    width: 150px;
    height: 2px;
    background: #1e293b;
    margin-bottom: 0.5rem;
}

.qr-code {
    margin-top: 2rem;
    text-align: center;
}

.qr-code img {
    width: 100px;
    height: 100px;
    margin-bottom: 0.5rem;
}

.qr-code p {
    font-size: 0.75rem;
    color: #94a3b8;
}

.certificate-footer {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
    font-size: 0.8rem;
    color: #94a3b8;
}

.certificate-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s;
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

.btn-secondary {
    background: white;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.btn-secondary:hover {
    border-color: #10b981;
    color: #10b981;
}

@media print {
    .certificate-actions {
        display: none;
    }
    
    .certificate {
        box-shadow: none;
        padding: 0;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .certificates-grid {
        grid-template-columns: 1fr;
    }
    
    .certificate-card {
        flex-direction: column;
        text-align: center;
    }
    
    .certificate-icon {
        margin: 0 auto;
    }
    
    .certificate-details {
        justify-content: center;
    }
    
    .certificate-actions {
        justify-content: center;
    }
    
    .certificate-wrapper {
        margin: 1rem auto;
    }
    
    .certificate-border {
        padding: 1rem;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.certificate-card {
    animation: fadeIn 0.3s ease;
}
</style>

<script>
function shareCertificate(certificateNo) {
    if (navigator.share) {
        navigator.share({
            title: 'MissionTech College Certificate',
            text: `I earned a certificate!`,
            url: window.location.href
        }).catch(() => {
            copyToClipboard(certificateNo);
        });
    } else {
        copyToClipboard(certificateNo);
    }
}

function copyToClipboard(certificateNo) {
    const text = `Check out my certificate from MissionTech College!`;
    navigator.clipboard.writeText(text);
    alert('Certificate link copied to clipboard!');
}
</script>

<?php include '../includes/footer.php'; ?>