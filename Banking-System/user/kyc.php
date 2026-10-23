<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'user') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$flash = '';
$error = '';

// Handle KYC Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_kyc') {
    $doc_type = $_POST['doc_type'];
    $doc_num = trim($_POST['doc_num']);
    $expiry = $_POST['expiry'];
    
    // For this project, we assume the file upload is simulated or simple
    // In a real app, you would handle $_FILES['document_file'] here
    $file_path = "uploads/dummy_kyc.jpg"; // Placeholder

    if (empty($doc_num) || empty($expiry)) {
        $error = 'Please fill in all document details.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO kyc_documents (user_id, document_type, document_number, expiry_date, file_path, status) VALUES (?, ?, ?, ?, ?, 'pending')")
                ->execute([$user_id, $doc_type, $doc_num, $expiry, $file_path]);
            
            $pdo->prepare("UPDATE users SET kyc_status = 'pending' WHERE id = ?")->execute([$user_id]);
            $pdo->commit();
            $flash = 'KYC document submitted successfully! A manager will review it shortly.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to submit document.';
        }
    }
}

// Fetch latest KYC status
$stmt = $pdo->prepare("SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$latest_kyc = $stmt->fetch();

// Fetch user's current kyc_status
$stmt = $pdo->prepare("SELECT kyc_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_kyc_status = $stmt->fetchColumn();

$page_title = 'KYC Verification';
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Identity Verification (KYC)</h1>
    <p style="color: var(--text-muted);">Verify your identity to unlock all banking features.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= $flash ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">
    
    <!-- Left: Current Status -->
    <div class="card" style="text-align: center; padding: 40px 24px;">
        <?php if ($user_kyc_status === 'verified' || $user_kyc_status === 'approved'): ?>
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 20px;"></i>
            <h3 style="color: var(--success); font-size: 1.5rem;">Verified</h3>
            <p style="color: var(--text-muted);">Your identity has been verified. You have full access to all features.</p>
        <?php elseif ($user_kyc_status === 'pending'): ?>
            <i class="fas fa-clock" style="font-size: 4rem; color: var(--warning); margin-bottom: 20px;"></i>
            <h3 style="color: var(--warning); font-size: 1.5rem;">Under Review</h3>
            <p style="color: var(--text-muted);">Your documents are being reviewed by our team. This usually takes a few minutes.</p>
        <?php elseif ($user_kyc_status === 'rejected'): ?>
            <i class="fas fa-times-circle" style="font-size: 4rem; color: var(--danger); margin-bottom: 20px;"></i>
            <h3 style="color: var(--danger); font-size: 1.5rem;">Rejected</h3>
            <p style="color: var(--text-muted);">Your previous submission was rejected. Please try again with a clear document.</p>
        <?php else: ?>
            <i class="fas fa-id-card" style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
            <h3 style="font-size: 1.5rem;">Not Verified</h3>
            <p style="color: var(--text-muted);">Please upload a valid ID document to verify your account.</p>
        <?php endif; ?>
        
        <?php if ($latest_kyc): ?>
            <div style="margin-top: 32px; text-align: left; background: var(--bg-alt); padding: 16px; border-radius: 12px; border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Document</span>
                    <span style="font-weight: 600; text-transform: uppercase;"><?= e($latest_kyc['document_type']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span style="color: var(--text-muted);">Submitted</span>
                    <span style="font-weight: 600;"><?= date('M j, Y', strtotime($latest_kyc['uploaded_at'])) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Upload Form -->
    <div class="card">
        <h3><i class="fas fa-upload" style="color: #10b981;"></i> Submit Document</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Upload a government-issued ID.</p>
        
        <?php if ($user_kyc_status === 'verified' || $user_kyc_status === 'approved'): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <p>You are already verified.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="upload_kyc">
                
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="doc_type" required>
                        <option value="passport">Passport</option>
                        <option value="national_id">National ID Card</option>
                        <option value="drivers_license">Driver's License</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Document Number</label>
                    <input type="text" name="doc_num" required placeholder="AB1234567">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry" required>
                </div>
                
                <div class="form-group">
                    <label>Upload File (Optional for demo)</label>
                    <input type="file" name="document_file" style="padding: 10px; background: var(--bg-alt); border: 1px dashed var(--border); border-radius: 12px; width: 100%;">
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 6px;">Note: File upload is simulated in this demo. Only the document details are saved.</small>
                </div>
                
                <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-paper-plane"></i> Submit for Verification
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
