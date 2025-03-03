<?php
// content_add.php - ثبت محتوای جدید
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// بررسی دسترسی کاربر
if (!isLoggedIn()) {
    redirect('login.php');
}

// دریافت شناسه شرکت
$companyId = isAdmin() ? 
    (isset($_GET['company']) && is_numeric($_GET['company']) ? clean($_GET['company']) : null) : 
    $_SESSION['company_id'];

if (!$companyId) {
    redirect('content_management.php');
}

$message = '';

// افزودن محتوای جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    try {
        // شروع تراکنش
        $pdo->beginTransaction();
        
        // دریافت اطلاعات فرم
        $topicIds = isset($_POST['topics']) ? $_POST['topics'] : [];
        $title = clean($_POST['title']);
        $audienceIds = isset($_POST['audiences']) ? $_POST['audiences'] : [];
        $typeIds = isset($_POST['types']) ? $_POST['types'] : [];
        $platformIds = isset($_POST['platforms']) ? $_POST['platforms'] : [];
        $publishDate = clean($_POST['publish_date']);
        $publishTime = clean($_POST['publish_time']);
        $taskAssignments = isset($_POST['tasks']) ? $_POST['tasks'] : [];
        $scenario = clean($_POST['scenario']);
        $productionStatusId = clean($_POST['production_status']);
        $publishStatusId = clean($_POST['publish_status']);
        $description = clean($_POST['description']);

        // اعتبارسنجی داده‌ها
        if (empty($title)) {
            throw new Exception('عنوان محتوا الزامی است.');
        }

        // درج محتوا
        $stmt = $pdo->prepare("INSERT INTO contents 
            (company_id, title, scenario, description, 
            production_status_id, publish_status_id, created_by, 
            publish_date, publish_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $companyId, 
            $title, 
            $scenario, 
            $description, 
            $productionStatusId, 
            $publishStatusId, 
            $_SESSION['user_id'], 
            $publishDate,
            $publishTime
        ]);
        
        $contentId = $pdo->lastInsertId();

        // درج موضوعات
        if (!empty($topicIds)) {
            $topicStmt = $pdo->prepare("INSERT INTO content_topic_relations (content_id, topic_id) VALUES (?, ?)");
            foreach ($topicIds as $topicId) {
                $topicStmt->execute([$contentId, $topicId]);
            }
        }

        // درج مخاطبین هدف
        if (!empty($audienceIds)) {
            $audienceStmt = $pdo->prepare("INSERT INTO content_audience_relations (content_id, audience_id) VALUES (?, ?)");
            foreach ($audienceIds as $audienceId) {
                $audienceStmt->execute([$contentId, $audienceId]);
            }
        }

        // درج انواع محتوا
        if (!empty($typeIds)) {
            $typeStmt = $pdo->prepare("INSERT INTO content_type_relations (content_id, type_id) VALUES (?, ?)");
            foreach ($typeIds as $typeId) {
                $typeStmt->execute([$contentId, $typeId]);
            }
        }

        // درج پلتفرم‌های انتشار
        if (!empty($platformIds)) {
            $platformStmt = $pdo->prepare("INSERT INTO content_platform_relations (content_id, platform_id) VALUES (?, ?)");
            foreach ($platformIds as $platformId) {
                $platformStmt->execute([$contentId, $platformId]);
            }
        }

        // درج وظایف و مسئولان آن‌ها
        foreach ($taskAssignments as $taskId => $personnelId) {
            $taskStmt = $pdo->prepare("INSERT INTO content_task_assignments 
                (content_id, task_id, personnel_id) VALUES (?, ?, ?)");
            $taskStmt->execute([$contentId, $taskId, $personnelId]);
        }

        // درج فرآیند پس از انتشار (اختیاری)
        if (isset($_POST['post_publish_process']) && $_POST['post_publish_process'] == 1) {
            $postPublishFormat = clean($_POST['post_publish_format']);
            $postPublishPlatforms = isset($_POST['post_publish_platforms']) ? $_POST['post_publish_platforms'] : [];
            $postPublishDays = clean($_POST['post_publish_days']);
            $postPublishTime = clean($_POST['post_publish_time']) ?: '10:00';

            $postPublishStmt = $pdo->prepare("INSERT INTO content_post_publish_processes 
                (content_id, format_id, days_after, publish_time) VALUES (?, ?, ?, ?)");
            $postPublishStmt->execute([
                $contentId, 
                $postPublishFormat, 
                $postPublishDays ?: 0, 
                $postPublishTime
            ]);

            // درج پلتفرم‌های فرآیند پس از انتشار
            if (!empty($postPublishPlatforms)) {
                $publishPlatformStmt = $pdo->prepare("INSERT INTO post_publish_platform_relations 
                    (process_id, platform_id) VALUES (?, ?)");
                
                $processId = $pdo->lastInsertId();
                foreach ($postPublishPlatforms as $platformId) {
                    $publishPlatformStmt->execute([$processId, $platformId]);
                }
            }
        }

        $pdo->commit();
        
        // هدایت به صفحه مشاهده محتوا یا لیست محتواها
        redirect('content_management.php?company=' . $companyId);
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = showError('خطا: ' . $e->getMessage());
    }
}

// دریافت اطلاعات برای فرم‌ها
$topics = $pdo->prepare("SELECT * FROM content_topics WHERE company_id = ? ORDER BY name");
$topics->execute([$companyId]);
$topics = $topics->fetchAll();

$audiences = $pdo->prepare("SELECT * FROM content_target_audiences WHERE company_id = ? ORDER BY name");
$audiences->execute([$companyId]);
$audiences = $audiences->fetchAll();

$types = $pdo->prepare("SELECT * FROM content_types WHERE company_id = ? ORDER BY name");
$types->execute([$companyId]);
$types = $types->fetchAll();

$platforms = $pdo->prepare("SELECT * FROM content_platforms WHERE company_id = ? ORDER BY name");
$platforms->execute([$companyId]);
$platforms = $platforms->fetchAll();

$tasks = $pdo->prepare("SELECT * FROM content_tasks WHERE company_id = ? ORDER BY name");
$tasks->execute([$companyId]);
$tasks = $tasks->fetchAll();

$productionStatuses = $pdo->prepare("SELECT * FROM content_production_statuses WHERE company_id = ? ORDER BY name");
$productionStatuses->execute([$companyId]);
$productionStatuses = $productionStatuses->fetchAll();

$publishStatuses = $pdo->prepare("SELECT * FROM content_publish_statuses WHERE company_id = ? ORDER BY name");
$publishStatuses->execute([$companyId]);
$publishStatuses = $publishStatuses->fetchAll();

$formats = $pdo->prepare("SELECT * FROM content_formats WHERE company_id = ? ORDER BY name");
$formats->execute([$companyId]);
$formats = $formats->fetchAll();

// دریافت پرسنل برای انتصاب وظایف
$personnel = $pdo->prepare("SELECT id, full_name FROM personnel WHERE company_id = ? AND is_active = 1 ORDER BY full_name");
$personnel->execute([$companyId]);
$personnel = $personnel->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>ثبت محتوای جدید</h1>
    <a href="content_management.php?company=<?php echo $companyId; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به مدیریت محتوا
    </a>
</div>

<?php echo $message; ?>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">اطلاعات اصلی محتوا</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="title" class="form-label">عنوان محتوا *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">موضوعات کلی</label>
                            <div class="row">
                                <?php foreach ($topics as $topic): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="topics[]" 
                                                   id="topic_<?php echo $topic['id']; ?>" 
                                                   value="<?php echo $topic['id']; ?>">
                                            <label class="form-check-label" for="topic_<?php echo $topic['id']; ?>">
                                                <?php echo $topic['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">مخاطبین هدف</label>
                            <div class="row">
                                <?php foreach ($audiences as $audience): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="audiences[]" 
                                                   id="audience_<?php echo $audience['id']; ?>" 
                                                   value="<?php echo $audience['id']; ?>">
                                            <label class="form-check-label" for="audience_<?php echo $audience['id']; ?>">
                                                <?php echo $audience['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">نوع محتوا</label>
                            <div class="row">
                                <?php foreach ($types as $type): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="types[]" 
                                                   id="type_<?php echo $type['id']; ?>" 
                                                   value="<?php echo $type['id']; ?>">
                                            <label class="form-check-label" for="type_<?php echo $type['id']; ?>">
                                                <?php echo $type['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">پلتفرم‌های انتشار</label>
                            <div class="row">
                                <?php foreach ($platforms as $platform): ?>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">پلتفرم‌های انتشار</label>
                                    <div class="row">
                                        <?php foreach ($platforms as $platform): ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="platforms[]" 
                                                           id="platform_<?php echo $platform['id']; ?>" 
                                                           value="<?php echo $platform['id']; ?>">
                                                    <label class="form-check-label" for="platform_<?php echo $platform['id']; ?>">
                                                        <?php echo $platform['name']; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="publish_date" class="form-label">تاریخ انتشار *</label>
                                    <input type="date" class="form-control" id="publish_date" name="publish_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="publish_time" class="form-label">ساعت انتشار *</label>
                                    <input type="time" class="form-control" id="publish_time" name="publish_time" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">وظایف</label>
                                    <?php foreach ($tasks as $task): ?>
                                        <div class="card mb-2">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <strong><?php echo $task['name']; ?></strong>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <select name="tasks[<?php echo $task['id']; ?>]" class="form-select">
                                                            <option value="">انتخاب مسئول</option>
                                                            <?php foreach ($personnel as $person): ?>
                                                                <option value="<?php echo $person['id']; ?>">
                                                                    <?php echo $person['full_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="scenario" class="form-label">سناریو</label>
                                    <textarea class="form-control" id="scenario" name="scenario" rows="5"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">وضعیت محتوا</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">وضعیت تولید *</label>
                            <?php foreach ($productionStatuses as $status): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="production_status" 
                                           id="production_status_<?php echo $status['id']; ?>" 
                                           value="<?php echo $status['id']; ?>"
                                           <?php echo $status['is_default'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="production_status_<?php echo $status['id']; ?>">
                                        <?php echo $status['name']; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">وضعیت انتشار *</label>
                            <?php foreach ($publishStatuses as $status): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="publish_status" 
                                           id="publish_status_<?php echo $status['id']; ?>" 
                                           value="<?php echo $status['id']; ?>"
                                           <?php echo $status['is_default'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="publish_status_<?php echo $status['id']; ?>">
                                        <?php echo $status['name']; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">فرآیند پس از انتشار</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="post_publish_process" name="post_publish_process" value="1">
                        <label class="form-check-label" for="post_publish_process">
                            فعال‌سازی فرآیند پس از انتشار
                        </label>
                    </div>

                    <div id="post_publish_details" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="post_publish_format" class="form-label">فرمت محتوا</label>
                                <select class="form-select" id="post_publish_format" name="post_publish_format">
                                    <?php foreach ($formats as $format): ?>
                                        <option value="<?php echo $format['id']; ?>">
                                            <?php echo $format['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="post_publish_days" class="form-label">روزهای پس از انتشار</label>
                                <input type="number" class="form-control" id="post_publish_days" name="post_publish_days" min="0" value="0">
                                <small class="form-text text-muted">0 به معنای همان روز انتشار است</small>
                            </div>
                            <div class="col-md-4">
                                <label for="post_publish_time" class="form-label">ساعت انتشار</label>
                                <input type="time" class="form-control" id="post_publish_time" name="post_publish_time" value="10:00">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">پلتفرم‌های انتشار</label>
                                <div class="row">
                                    <?php foreach ($platforms as $platform): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="post_publish_platforms[]" 
                                                       id="post_publish_platform_<?php echo $platform['id']; ?>" 
                                                       value="<?php echo $platform['id']; ?>">
                                                <label class="form-check-label" for="post_publish_platform_<?php echo $platform['id']; ?>">
                                                    <?php echo $platform['name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" name="add_content" class="btn btn-primary px-5">
                    <i class="fas fa-save"></i> ذخیره محتوا
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const postPublishCheckbox = document.getElementById('post_publish_process');
    const postPublishDetails = document.getElementById('post_publish_details');

    postPublishCheckbox.addEventListener('change', function() {
        postPublishDetails.style.display = this.checked ? 'block' : 'none';
    });
});
</script>
<?php include 'footer.php'; ?>