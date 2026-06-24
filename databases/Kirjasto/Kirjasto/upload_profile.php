<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/profiles/';

// Create directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Add .htaccess to prevent execution in uploads directory
$htaccess_file = $upload_dir . '.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file,
        "Order deny,allow\n" .
        "Deny from all\n" .
        "<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">\n" .
        "    Allow from all\n" .
        "</FilesMatch>\n" .
        "php_flag engine off\n"
    );
}

// ========== HANDLE FILE UPLOAD ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    $error = $_FILES['profile_image']['error'];
    
    // Check if file was actually uploaded
    if ($error == UPLOAD_ERR_NO_FILE) {
        $_SESSION['upload_error'] = "Please select a file to upload.";
        header("Location: upload_profile.php");
        exit();
    }
    
    // Get user's current profile image for cleanup
    $stmt = $conn->prepare("SELECT profile_image FROM jasenet WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_image);
    $stmt->fetch();
    $stmt->close();

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $file_type = $_FILES['profile_image']['type'];
    $file_size = $_FILES['profile_image']['size'];
    $tmp_name = $_FILES['profile_image']['tmp_name'];
    
    // Check for other upload errors
    if ($error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
            UPLOAD_ERR_CANT_WRITE => 'Failed to save file',
            UPLOAD_ERR_EXTENSION => 'File type not allowed'
        ];
        $_SESSION['upload_error'] = "Upload error: " . ($error_messages[$error] ?? "Unknown error");
        header("Location: upload_profile.php");
        exit();
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    if (!in_array($detected_type, $allowed_types)) {
        $_SESSION['upload_error'] = "Only JPG, PNG, GIF, and WebP images are allowed.";
        header("Location: upload_profile.php");
        exit();
    }

    // Validate file size
    if ($file_size > $max_size) {
        $_SESSION['upload_error'] = "File size must be less than 5MB.";
        header("Location: upload_profile.php");
        exit();
    }

    // Check if image file is valid
    $check = getimagesize($tmp_name);
    if ($check === false) {
        $_SESSION['upload_error'] = "File is not a valid image.";
        header("Location: upload_profile.php");
        exit();
    }

    // Generate secure filename
    $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $file_name = $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;

    // Move uploaded file
    if (move_uploaded_file($tmp_name, $target_file)) {
        // Delete old profile image if it exists
        if ($current_image && file_exists($upload_dir . $current_image) && $current_image !== $file_name) {
            @unlink($upload_dir . $current_image);
        }

        // Update database
        $stmt = $conn->prepare("UPDATE jasenet SET profile_image = ? WHERE id = ?");
        $stmt->bind_param("si", $file_name, $user_id);

        if ($stmt->execute()) {
            // Update session
            $_SESSION['profile_image'] = $file_name;
            $_SESSION['upload_success'] = 'Profile picture updated successfully!';
            $stmt->close();
            header("Location: manager_dashboard.php");
            exit();
        } else {
            // Delete uploaded file if database update fails
            if (file_exists($target_file)) {
                unlink($target_file);
            }
            $_SESSION['upload_error'] = "Database error.";
            header("Location: upload_profile.php");
            exit();
        }
    } else {
        $_SESSION['upload_error'] = "Failed to save the uploaded file.";
        header("Location: upload_profile.php");
        exit();
    }
}

// ========== SHOW UPLOAD FORM ==========
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
        }
        
        .upload-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .file-input-container {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-input-container:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .file-input-container.dragover {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .file-input {
            display: none;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .file-label {
            display: block;
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .file-hint {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .file-name {
            color: #667eea;
            font-weight: 500;
            margin-top: 10px;
            word-break: break-all;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .requirements h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .requirements li {
            color: #666;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .requirements li:before {
            content: "•";
            color: #667eea;
            position: absolute;
            left: 0;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 15px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #eff;
            color: #0a6;
            border: 1px solid #cef;
        }
        
        .preview-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            object-fit: cover;
            border: 3px solid #f0f0f0;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="upload-card">
            <h1>📷 Upload Profile Picture</h1>
            <p class="subtitle">Choose an image to update your profile</p>
            
            <?php if (isset($_SESSION['upload_error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo htmlspecialchars($_SESSION['upload_error']);
                    unset($_SESSION['upload_error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['upload_success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['upload_success']);
                    unset($_SESSION['upload_success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <div class="file-input-container" id="dropArea">
                        <div class="upload-icon">📁</div>
                        <label for="profile_image" class="file-label">
                            <strong>Click to browse</strong> or drag & drop
                        </label>
                        <p class="file-hint">PNG, JPG, GIF, WebP up to 5MB</p>
                        <div class="file-name" id="fileName">No file selected</div>
                        <input type="file" name="profile_image" id="profile_image" 
                               class="file-input" accept="image/*" required>
                    </div>
                </div>
                
                <div class="preview-container">
                    <img id="imagePreview" class="image-preview" alt="Preview">
                </div>
                
                <div class="requirements">
                    <h3>📋 Requirements:</h3>
                    <ul>
                        <li>Maximum file size: 5MB</li>
                        <li>Allowed formats: JPG, PNG, GIF, WebP</li>
                        <li>Recommended: Square image (1:1 ratio)</li>
                        <li>Optimal size: 500x500 pixels</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    📤 Upload Picture
                </button>
                <a href="manager_dashboard.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('profile_image');
            const fileName = document.getElementById('fileName');
            const dropArea = document.getElementById('dropArea');
            const imagePreview = document.getElementById('imagePreview');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('uploadForm');
            
            // Handle file selection
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    fileName.textContent = file.name;
                    previewImage(file);
                } else {
                    fileName.textContent = 'No file selected';
                    imagePreview.style.display = 'none';
                }
            });
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropArea.classList.add('dragover');
            }
            
            function unhighlight() {
                dropArea.classList.remove('dragover');
            }
            
            dropArea.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    fileName.textContent = files[0].name;
                    previewImage(files[0]);
                    
                    // Trigger change event
                    const event = new Event('change', { bubbles: true });
                    fileInput.dispatchEvent(event);
                }
            });
            
            // Preview image
            function previewImage(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            }
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const file = fileInput.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!file) {
                    e.preventDefault();
                    alert('Please select a file to upload.');
                    return;
                }
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File size must be less than 5MB. Your file is ' + 
                          Math.round(file.size / 1024 / 1024 * 100) / 100 + 'MB');
                    return;
                }
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Uploading...';
            });
            
            // Click anywhere on drop area to trigger file input
            dropArea.addEventListener('click', function() {
                fileInput.click();
            });
        });
    </script>
</body>
</html>
