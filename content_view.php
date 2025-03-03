<?php
// content_view.php - View content details
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Check user access
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get content ID
$contentId = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

if ($contentId <= 0) {
    redirect('content_list.php');
}

// Fetch content details with all related information
$stmt = $pdo->prepare("SELECT c.*, 
    p.full_name as creator_name, 
    ps.name as production_status, 
    pbs.name as publish_status,
    comp.name as company_name
    FROM contents c
    JOIN personnel p ON c.created_by = p.id
    JOIN content_production_statuses ps ON c.production_status_id = ps.id
    JOIN content_publish_statuses pbs ON c.publish_status_id = pbs.id
    JOIN companies comp ON c.company_id = comp.id
    WHERE c.id = ?");
$stmt->execute([$contentId]);
$content = $stmt->fetch();

if (!$content) {
    redirect('content_list.php');
}

// Check access permissions
$canEdit = false;
if (isAdmin()) {
    $canEdit = true;
} elseif (isCEO() || $_SESSION['user_id'] == $content['created_by']) {
    $canEdit = true;
}

// Fetch related topics
$topicStmt = $pdo->prepare("SELECT t.name 
    FROM content_topics t
    JOIN content_topic_relations ctr ON t.id = ctr.topic_id
    WHERE ctr.content_id = ?");
$topicStmt->execute([$contentId]);
$topics = $topicStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch related audiences
$audienceStmt = $pdo->prepare("SELECT a.name 
    FROM content_target_audiences a
    JOIN content_audience_relations car ON a.id = car.audience_id
    WHERE car.content_id = ?");
$audienceStmt->execute([$contentId]);
$audiences = $audienceStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch content types
$typeStmt = $pdo->prepare("SELECT t.name 
    FROM content_types t
    JOIN content_type_relations ctr ON t.id = ctr.type_id
    WHERE ctr.content_id = ?");
$typeStmt->execute([$contentId]);
$types = $typeStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch platforms
$platformStmt = $pdo->prepare("SELECT p.name 
    FROM content_platforms p
    JOIN content_platform_relations cpr ON p.id = cpr.platform_id
    WHERE cpr.content_id = ?");
$platformStmt->execute([$contentId]);
$platforms = $platformStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch task assignments
$taskStmt = $pdo->prepare("SELECT 
    ct.name as task_name, 
    p.full_name as assigned_person,
    cta.is_completed,
    cta.completion_date
    FROM content_task_assignments cta
    JOIN content_tasks ct ON cta.task_id = ct.id
    JOIN personnel p ON cta.personnel_id = p.id
    WHERE cta.content_id = ?");
$taskStmt->execute([$contentId]);
$tasks = $taskStmt->fetchAll();

// Fetch post-publish process if exists
$postPublishStmt = $pdo->prepare("SELECT 
    cf.name as format_name,
    cpp.days_after,
    cpp.publish_time,
    GROUP_CONCAT(cp.name SEPARATOR ', ') as platforms
    FROM content_post_publish_processes cpp
    LEFT JOIN content_formats cf ON cpp.format_id = cf.id
    LEFT JOIN post_publish_platform_relations pppr ON cpp.id = pppr.process_id
    LEFT JOIN content_platforms cp ON pppr.platform_id = cp.id
    WHERE cpp.content_id = ?
    GROUP BY cpp.id");
$postPublishStmt->execute([$contentId]);
$postPublishProcess = $postPublishStmt->fetch();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>جزئیات محتوا</h1>
    <div>
        <?php if ($canEdit): ?>
            <a href="content_edit.php?id=<?php echo $contentId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> ویرایش محتوا
            </a>
        <?php endif; ?>
        <a href="content_list.php?company=<?php echo $content['company_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> بازگشت به لیست محتواها
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">اطلاعات اصلی محتوا</h5>
            </div>
            <div class="card-body">
                <h2 class="mb-4"><?php echo htmlspecialchars($content['title']); ?></h2>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>تاریخ انتشار:</strong> 
                        <?php echo $content['publish_date']; ?> 
                        ساعت 
                        <?php echo $content['publish_time']; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>ایجاد کننده:</strong> 
                        <?php echo $content['creator_name']; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>وضعیت تولید:</strong> 
                        <span class="badge bg-info"><?php echo $content['production_status']; ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>وضعیت انتشار:</strong> 
                        <span class="badge bg-secondary"><?php echo $content['publish_status']; ?></span>
                    </div>
                </div>
                
                <?php if (!empty($topics)): ?>
                    <div class="mb-3">
                        <strong>موضوعات کلی:</strong> 
                        <?php echo implode('، ', $topics); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($audiences)): ?>
                    <div class="mb-3">
                        <strong>مخاطبین هدف:</strong> 
                        <?php echo implode('، ', $audiences); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($types)): ?>
                    <div class="mb-3">
                        <strong>نوع محتوا:</strong> 
                        <?php echo implode('، ', $types); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($platforms)): ?>
                    <div class="mb-3">
                        <strong>پلتفرم‌های انتشار:</strong> 
                        <?php echo implode('، ', $platforms); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">سناریو و توضیحات</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($content['scenario'])): ?>
                    <div class="mb-3">
                        <strong>سناریو:</strong>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($content['scenario'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($content['description'])): ?>
                    <div class="mb-3">
                        <strong>توضیحات:</strong>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($content['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">وظایف محتوا</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($tasks)): ?>
                    <ul class="list-group">
                        <?php foreach ($tasks as $task): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo $task['task_name']; ?>
                                <div>
                                    <span class="badge <?php echo $task['is_completed'] ? 'bg-success' : 'bg-warning'; ?> ms-2">
                                        <?php echo $task['is_completed'] ? 'تکمیل شده' : 'در حال انجام'; ?>
                                    </span>
                                    <small class="text-muted">
                                        <?php echo $task['assigned_person']; ?>
                                    </small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center text-muted">وظیفه‌ای تعریف نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($postPublishProcess): ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">فرآیند پس از انتشار</h5>
                </div>
                <div class="card-body">
                    <p>
                        <strong>فرمت:</strong> <?php echo $postPublishProcess['format_name']; ?>
                    </p>
                    <p>
                        <strong>زمان انتشار:</strong> 
                        <?php 
                            echo $postPublishProcess['days_after'] == 0 
                                ? 'همان روز انتشار' 
                                : $postPublishProcess['days_after'] . ' روز بعد از انتشار'; 
                        ?>
                        
                        در ساعت 
                        <?php echo $postPublishProcess['publish_time']; ?>
                    </p>
                    
                    <?php if ($postPublishProcess['platforms']): ?>
                        <p>
                            <strong>پلتفرم‌های انتشار:</strong>
                            <?php echo $postPublishProcess['platforms']; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>