<?php
// content_production_statuses.php - مدیریت وضعیت‌های تولید محتوا
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// بررسی دسترسی ادمین یا مدیر محتوا
if (!isAdmin() && !hasPermission('manage_content')) {
    redirect('index.php');
}

$message = '';
$companyId = isAdmin() ? (isset($_GET['company']) ? clean($_GET['company']) : '') : $_SESSION['company_id'];

// افزودن وضعیت تولید جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_status'])) {
    $name = clean($_POST['name']);
    $selectedCompany = clean($_POST['company_id']);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($name)) {
        $message = showError('لطفا نام وضعیت تولید را وارد کنید.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO content_production_statuses (company_id, name, is_default, can_delete) VALUES (?, ?, ?, 1)");
            $stmt->execute([$selectedCompany, $name, $isDefault]);
            $message = showSuccess('وضعیت تولید با موفقیت اضافه شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در ثبت وضعیت تولید: ' . $e->getMessage());
        }
    }
}

// ویرایش وضعیت تولید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_status'])) {
    $statusId = clean($_POST['status_id']);
    $name = clean($_POST['name']);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($name)) {
        $message = showError('لطفا نام وضعیت تولید را وارد کنید.');
    } else {
        try {
            // بررسی اینکه آیا این وضعیت تولید متعلق به شرکت کاربر است
            $canEdit = false;
            if (isAdmin()) {
                $canEdit = true;
            } else {
                $stmt = $pdo->prepare("SELECT company_id, can_delete FROM content_production_statuses WHERE id = ?");
                $stmt->execute([$statusId]);
                $status = $stmt->fetch();
                if ($status && $status['company_id'] == $_SESSION['company_id']) {
                    $canEdit = true;
                }
            }
            
            if ($canEdit) {
                $stmt = $pdo->prepare("UPDATE content_production_statuses SET name = ?, is_default = ? WHERE id = ?");
                $stmt->execute([$name, $isDefault, $statusId]);
                $message = showSuccess('وضعیت تولید با موفقیت ویرایش شد.');
            } else {
                $message = showError('شما اجازه ویرایش این وضعیت تولید را ندارید.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در ویرایش وضعیت تولید: ' . $e->getMessage());
        }
    }
}

// حذف وضعیت تولید
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $statusId = $_GET['delete'];
    
    try {
        // بررسی اینکه آیا این وضعیت تولید متعلق به شرکت کاربر است و قابل حذف است
        $canDelete = false;
        if (isAdmin()) {
            $stmt = $pdo->prepare("SELECT can_delete FROM content_production_statuses WHERE id = ?");
            $stmt->execute([$statusId]);
            $status = $stmt->fetch();
            $canDelete = ($status && $status['can_delete'] == 1);
        } else {
            $stmt = $pdo->prepare("SELECT company_id, can_delete FROM content_production_statuses WHERE id = ?");
            $stmt->execute([$statusId]);
            $status = $stmt->fetch();
            $canDelete = ($status && $status['company_id'] == $_SESSION['company_id'] && $status['can_delete'] == 1);
        }
        
        if ($canDelete) {
            // بررسی اینکه آیا این وضعیت تولید در استفاده است
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM contents WHERE production_status_id = ?");
            $stmt->execute([$statusId]);
            $usageCount = $stmt->fetch()['count'];
            
            if ($usageCount > 0) {
                $message = showError('این وضعیت تولید قابل حذف نیست زیرا در ' . $usageCount . ' محتوا استفاده شده است.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM content_production_statuses WHERE id = ?");
                $stmt->execute([$statusId]);
                $message = showSuccess('وضعیت تولید با موفقیت حذف شد.');
            }
        } else {
            $message = showError('این وضعیت تولید قابل حذف نیست یا شما اجازه حذف آن را ندارید.');
        }
    } catch (PDOException $e) {
        $message = showError('خطا در حذف وضعیت تولید: ' . $e->getMessage());
    }
}

// اضافه کردن وضعیت‌های پیش‌فرض برای شرکت انتخاب شده اگر وجود نداشته باشند
if (!empty($companyId)) {
    try {
        // بررسی وجود وضعیت‌های پیش‌فرض
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM content_production_statuses WHERE company_id = ? AND name IN ('محتوا تولید نشده', 'محتوا در حال تولید است', 'محتوا تولید شده')");
        $stmt->execute([$companyId]);
        $defaultStatusesCount = $stmt->fetch()['count'];
        
        // اگر وضعیت‌های پیش‌فرض وجود ندارند، آنها را اضافه کن
        if ($defaultStatusesCount < 3) {
            $defaultStatuses = [
                ['محتوا تولید نشده', 1, 0], // name, is_default, can_delete
                ['محتوا در حال تولید است', 0, 0],
                ['محتوا تولید شده', 0, 0]
            ];
            
            $insertStmt = $pdo->prepare("INSERT INTO content_production_statuses (company_id, name, is_default, can_delete) VALUES (?, ?, ?, ?)");
            
            foreach ($defaultStatuses as $status) {
                // بررسی وجود وضعیت
                $checkStmt = $pdo->prepare("SELECT id FROM content_production_statuses WHERE company_id = ? AND name = ?");
                $checkStmt->execute([$companyId, $status[0]]);
                $exists = $checkStmt->fetch();
                
                if (!$exists) {
                    $insertStmt->execute([$companyId, $status[0], $status[1], $status[2]]);
                }
            }
        }
    } catch (PDOException $e) {
        // خطا در اضافه کردن وضعیت‌های پیش‌فرض
    }
}

// دریافت لیست شرکت‌ها برای ادمین
if (isAdmin()) {
    $stmt = $pdo->query("SELECT * FROM companies WHERE is_active = 1 ORDER BY name");
    $companies = $stmt->fetchAll();
}

// دریافت لیست وضعیت‌های تولید
$query = "SELECT s.*, c.name as company_name,
          (SELECT COUNT(*) FROM contents WHERE production_status_id = s.id) as usage_count 
          FROM content_production_statuses s 
          JOIN companies c ON s.company_id = c.id ";

if (!isAdmin()) {
    $query .= " WHERE s.company_id = ? ";
    $params = [$_SESSION['company_id']];
} else if (!empty($companyId)) {
    $query .= " WHERE s.company_id = ? ";
    $params = [$companyId];
} else {
    $params = [];
}

$query .= " ORDER BY s.company_id, s.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$statuses = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت وضعیت‌های تولید محتوا</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStatusModal">
            <i class="fas fa-plus"></i> افزودن وضعیت تولید جدید
        </button>
        <a href="content_management.php<?php echo isAdmin() && !empty($companyId) ? '?company=' . $companyId : ''; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> بازگشت به مدیریت محتوا
        </a>
    </div>
</div>

<?php echo $message; ?>

<?php if (isAdmin()): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">فیلتر بر اساس شرکت</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-10">
                    <select class="form-select" name="company">
                        <option value="">همه شرکت‌ها</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $companyId == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo $company['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">فیلتر</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (count($statuses) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام وضعیت تولید</th>
                            <?php if (isAdmin()): ?>
                                <th>شرکت</th>
                            <?php endif; ?>
                            <th>پیش‌فرض</th>
                            <th>قابل حذف</th>
                            <th>تعداد استفاده</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statuses as $index => $status): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $status['name']; ?></td>
                                <?php if (isAdmin()): ?>
                                    <td><?php echo $status['company_name']; ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($status['is_default']): ?>
                                        <span class="badge bg-success">بله</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">خیر</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['can_delete']): ?>
                                        <span class="badge bg-success">بله</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">خیر</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $status['usage_count']; ?></td>
                                <td><?php echo $status['created_at']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editStatusModal" 
                                            data-id="<?php echo $status['id']; ?>"
                                            data-name="<?php echo $status['name']; ?>"
                                            data-default="<?php echo $status['is_default']; ?>"
                                            data-delete="<?php echo $status['can_delete']; ?>">
                                        <i class="fas fa-edit"></i> ویرایش
                                    </button>
                                    
                                    <?php if ($status['can_delete'] && $status['usage_count'] == 0): ?>
                                        <a href="?<?php echo isAdmin() && !empty($companyId) ? 'company=' . $companyId . '&' : ''; ?>delete=<?php echo $status['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('آیا از حذف این وضعیت تولید اطمینان دارید؟')">
                                            <i class="fas fa-trash"></i> حذف
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center">هیچ وضعیت تولیدی یافت نشد.</p>
        <?php endif; ?>
    </div>
</div>

<!-- افزودن وضعیت تولید جدید -->
<div class="modal fade" id="addStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن وضعیت تولید جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if (isAdmin()): ?>
                        <div class="mb-3">
                            <label for="company_id" class="form-label">شرکت</label>
                            <select class="form-select" id="company_id" name="company_id" required>
                                <option value="">انتخاب شرکت...</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" <?php echo $companyId == $company['id'] ? 'selected' : ''; ?>>
                                        <?php echo $company['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="company_id" value="<?php echo $_SESSION['company_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">نام وضعیت تولید</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                        <label class="form-check-label" for="is_default">پیش‌فرض</label>
                        <div class="form-text">وضعیت‌های پیش‌فرض به صورت خودکار در فرم‌های ثبت محتوا انتخاب می‌شوند.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_status" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ویرایش وضعیت تولید -->
<div class="modal fade" id="editStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ویرایش وضعیت تولید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_status_id" name="status_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">نام وضعیت تولید</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_default" name="is_default">
                        <label class="form-check-label" for="edit_is_default">پیش‌فرض</label>
                        <div class="form-text">وضعیت‌های پیش‌فرض به صورت خودکار در فرم‌های ثبت محتوا انتخاب می‌شوند.</div>
                    </div>

                    <div class="mb-3" id="can_delete_info">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            این وضعیت تولید پیش‌فرض سیستم است و قابل حذف نیست.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="edit_status" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ویرایش وضعیت تولید
    const editModal = document.getElementById('editStatusModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const isDefault = button.getAttribute('data-default') === '1';
            const canDelete = button.getAttribute('data-delete') === '1';
            
            document.getElementById('edit_status_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_is_default').checked = isDefault;
            
            // نمایش یا عدم نمایش هشدار برای وضعیت‌های غیرقابل حذف
            const canDeleteInfo = document.getElementById('can_delete_info');
            if (canDelete) {
                canDeleteInfo.style.display = 'none';
            } else {
                canDeleteInfo.style.display = 'block';
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>