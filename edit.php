<?php
$database_path = __DIR__ . '/metadata.sqlite';
try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8';");
    error_log("edit.php: 데이터베이스 연결 성공");
} catch (PDOException $e) {
    error_log("edit.php: 데이터베이스 연결 실패: " . $e->getMessage());
    die("데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()));
}

$fangzi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($fangzi_id <= 0) {
    error_log("edit.php: 유효하지 않은 fangzi_id: $fangzi_id");
    die("유효하지 않은 처방 ID");
}

// 데이터 조회
try {
    // fangzi 테이블: 모든 약재 조회
    $stmt = $pdo->prepare("SELECT herb FROM fangzi WHERE id = ?");
    $stmt->execute([$fangzi_id]);
    $herbs = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $herbs_text = $herbs ? implode(', ', $herbs) : '약재 정보 없음';

    // fangzi_article 테이블: 출전 조회
    $stmt = $pdo->prepare("SELECT article FROM fangzi_article WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$fangzi_id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    $article_name = $article ? $article['article'] : '출전 정보 없음';

    // fangzi_snippets 테이블: 원문 내용 조회
    $stmt = $pdo->prepare("SELECT snippet FROM fangzi_snippets WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$fangzi_id]);
    $snippet = $stmt->fetch(PDO::FETCH_ASSOC);
    $snippet_text = $snippet ? $snippet['snippet'] : '';

    error_log("edit.php: 데이터 조회 성공, fangzi_id: $fangzi_id, herbs: " . json_encode($herbs) . ", article: $article_name, snippet: $snippet_text");
} catch (PDOException $e) {
    error_log("edit.php: 데이터 조회 실패, fangzi_id: $fangzi_id, 오류: " . $e->getMessage());
    die("데이터 조회 실패: " . htmlspecialchars($e->getMessage()));
}

// 데이터 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $snippet_text = isset($_POST['snippet']) ? trim($_POST['snippet']) : '';

    try {
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO fangzi_snippets (fangzi_id, snippet)
            VALUES (?, ?)
        ");
        $stmt->execute([$fangzi_id, $snippet_text]);

        error_log("edit.php: 데이터 저장 성공, fangzi_id: $fangzi_id, snippet: $snippet_text");
        header("Location: index.php?message=수정 완료");
        exit;
    } catch (PDOException $e) {
        error_log("edit.php: 데이터 저장 실패, fangzi_id: $fangzi_id, 오류: " . $e->getMessage());
        $error = "데이터 저장 실패: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>처방 수정 - TCM 데이터베이스</title>
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
                <h2 class="mt-4">처방 수정 (ID: <?php echo htmlspecialchars($fangzi_id); ?>)</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="edit.php?id=<?php echo htmlspecialchars($fangzi_id); ?>">
                    <div class="mb-3">
                        <label class="form-label">약재</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($herbs_text); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">출전</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($article_name); ?></p>
                    </div>
                    <div class="mb-3">
                        <label for="snippet" class="form-label">원문 내용</label>
                        <textarea class="form-control" id="snippet" name="snippet" rows="5"><?php echo htmlspecialchars($snippet_text); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">저장</button>
                    <a href="index.php" class="btn btn-secondary">취소</a>
                </form>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>