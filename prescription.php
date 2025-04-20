<?php
$database_path = __DIR__ . '/metadata.sqlite';
try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8';");
    error_log("prescription.php: 데이터베이스 연결 성공");
} catch (PDOException $e) {
    error_log("prescription.php: 데이터베이스 연결 실패: " . $e->getMessage());
    die("데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : 'fangzi';

if (!$id) {
    error_log("prescription.php: 잘못된 요청, id: $id");
    die("잘못된 요청입니다.");
}

// 데이터 조회
$fangzi = [];
$article = [];
$snippet = [];

try {
    $stmt = $pdo->prepare("SELECT herb FROM fangzi WHERE id = ?");
    $stmt->execute([$id]);
    $fangzi = $stmt->fetchAll(PDO::FETCH_ASSOC); // 모든 약재 조회

    $stmt = $pdo->prepare("SELECT article FROM fangzi_article WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT snippet FROM fangzi_snippets WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $snippet = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("prescription.php: 데이터 조회 성공, id: $id, fangzi: " . json_encode($fangzi) . ", article: " . json_encode($article) . ", snippet: " . json_encode($snippet));
} catch (PDOException $e) {
    error_log("prescription.php: 데이터 조회 실패, id: $id, 오류: " . $e->getMessage());
    echo '<div class="alert alert-danger">데이터 조회 중 오류가 발생했습니다. 관리자에게 문의하세요.</div>';
    exit;
}

if (empty($fangzi) && empty($article) && empty($snippet)) {
    error_log("prescription.php: 데이터 없음, id: $id");
    die("데이터를 찾을 수 없습니다.");
}

$title = !empty($fangzi) ? implode(', ', array_column($fangzi, 'herb')) : ($article['article'] ? basename($article['article'], '.txt') : '처방');
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - TCM 데이터베이스</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row g-0">
            <!-- 사이드바 -->
            <div class="col-md-3 col-lg-2">
                <?php include 'includes/side_bar.php'; ?>
            </div>
            
            <!-- 메인 콘텐츠 -->
            <main class="col-md-9 col-lg-10 main-content">
                <h2 class="mt-4"><?php echo htmlspecialchars($title); ?></h2>
                
                <div class="prescription-details">
                    <dl>
                        <dt>약재</dt>
                        <dd><?php echo htmlspecialchars(!empty($fangzi) ? implode(', ', array_column($fangzi, 'herb')) : '없음'); ?></dd>
                        
                        <dt>출전</dt>
                        <dd><?php echo htmlspecialchars($article['article'] ? basename($article['article'], '.txt') : '없음'); ?></dd>
                                 <dt>원문 내용</dt>
                <dd><ul class="list-group mb-4">
                            <li class="list-group-item" style="background-color: #efefef; padding: 20px; font-size : 1.2em;">
                                <?php echo htmlspecialchars($snippet['snippet'] ?? '없음'); ?>                                
                            </li>
							</dd>
                      
                </ul>               
                    </dl>
                </div>

             
                <a href="index.php" class="btn btn-secondary">목록으로</a>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>