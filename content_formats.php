<?php
// content_formats.php - مدیریت فرمت‌های محتوایی
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// بررسی دسترسی ادمین یا مدیر محتوا
if (!isAdmin() && !hasPermission('manage_content')) {
    redirect('index.php');
}

$message = '';
$companyId = isAdmin() ? (isset($_GET['company']) ? clean($_GET['company']) : '') : $_SESSION['company_id'];

// افزودن فرمت محتوا جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_format'])) {
    $name = clean($_POST['name']);
    $selectedCompany = clean($_POST['company_id']);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($name)) {
        $message = showError('لطفا نام فرمت محتوا را وارد کنید.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO content_formats (company_id, name, is_default, can_delete) VALUES (?, ?, ?, 1)");
            $stmt->execute([$selectedCompany, $name, $isDefault]);
            $message = showSuccess('فرمت محتوا با موفقیت اضافه شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در ثبت فرمت محتوا: ' . $e->getMessage());
        }
    }
}

// ویرایش فرمت محتوا
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_format'])) {
    $formatId = clean($_POST['format_id']);
    $name = clean($_POST['name']);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($name)) {
        $message = showError('لطفا نام فرمت محتوا را وارد کنید.');
    } else {
        try {
            // بررسی اینکه آیا این فرمت محتوا متعلق به شرکت کاربر است
            $canEdit = false;
            if (isAdmin()) {
                $canEdit = true;
            } else {
                $stmt = $pdo->prepare("SELECT company_id, can_delete FROM content_formats WHERE id = ?");
                $stmt->execute([$formatId]);
                $format = $stmt->fetch();
                if ($format && $format['company_id'] == $_SESSION['company_id']) {
                    $canEdit = true;
                }
            }
            
            if ($canEdit) {
                $stmt = $pdo->prepare("UPDATE content_formats SET name = ?, is_default = ? WHERE id = ?");
                $stmt->execute([$name, $isDefault, $formatId]);
                $message = showSuccess('فرمت محتوا با موفقیت ویرایش شد.');
            } else {
                $message = showError('شما اجازه ویرایش این فرمت محتوا را ندارید.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در ویرایش فرمت محتوا: ' . $e->getMessage());
        }
    }
}

// حذف فرمت محتوا
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $formatId = $_GET['delete'];
    
    try {
        // بررسی اینکه آیا این فرمت محتوا متعلق به شرکت کاربر است و قابل حذف است
        $canDelete = false;
        if (isAdmin()) {
            $stmt = $pdo->prepare("SELECT can_delete FROM content_formats WHERE id = ?");
            $stmt->execute([$formatId]);
            $format = $stmt->fetch();
            $canDelete = ($format && $format['can_delete'] == 1);
        } else {
            $stmt = $pdo->prepare("SELECT company_id, can_delete FROM content_formats WHERE id = ?");
            $stmt->execute([$formatId]);
            $format = $stmt->fetch();
            $canDelete = ($format && $format['company_id'] == $_SESSION['company_id'] && $format['can_delete'] == 1);
        }
        
        if ($canDelete) {
            // بررسی اینکه آیا این فرمت محتوا در استفاده است
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM content_post_publish_processes WHERE format_id = ?");
            $stmt->execute([$formatId]);
            $usageCount = $stmt->fetch()['count'];
            
            if ($usageCount > 0) {
                $message = showError('این فرمت محتوا قابل حذف نیست زیرا در ' . $usageCount . ' فرآیند پس از انتشار استفاده شده است.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM content_formats WHERE id = ?");
                $stmt->execute([$formatId]);
                $message = showSuccess('فرمت محتوا با موفقیت حذف شد.');
            }
        } else {
            $message = showError('این فرمت محتوا قابل حذف نیست یا شما اجازه حذف آن را ندارید.');
        }
    } catch (PDOException $e) {
        $message = showError('خطا در حذف فرمت محتوا: ' . $e->getMessage());
    }
}