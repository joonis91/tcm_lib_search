<?php
header('Content-Type: application/json; charset=UTF-8');

$database_path = __DIR__ . '/metadata.sqlite';
try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8';");
    error_log("fetch_snippet.php: 데이터베이스 연결 성공");
} catch (PDOException $e) {
    error_log("fetch_snippet.php: 데이터베이스 연결 실패: " . $e->getMessage());
    echo json_encode(['error' => '데이터베이스 연결 실패']);
    exit;
}

$fangzi_id = isset($_GET['fangzi_id']) ? (int)$_GET['fangzi_id'] : 0;
if ($fangzi_id <= 0) {
    error_log("fetch_snippet.php: 유효하지 않은 fangzi_id: $fangzi_id");
    echo json_encode(['error' => '유효하지 않은 처방 ID']);
    exit;
}

try {
    // 약재 정보 조회
    $stmt = $pdo->prepare("SELECT herb FROM fangzi WHERE id = ?");
    $stmt->execute([$fangzi_id]);
    $herb_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $herbs = $herb_row && $herb_row['herb'] ? explode(',', trim($herb_row['herb'])) : [];

    // 출처 정보 조회
    $stmt = $pdo->prepare("SELECT article FROM fangzi_article WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$fangzi_id]);
    $article_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $source = $article_row ? basename($article_row['article']) : '';

    // 원문 내용 조회
    $stmt = $pdo->prepare("SELECT snippet FROM fangzi_snippets WHERE fangzi_id = ? LIMIT 1");
    $stmt->execute([$fangzi_id]);
    $snippet_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $snippet = $snippet_row ? $snippet_row['snippet'] : '';

    error_log("fetch_snippet.php: fangzi_id $fangzi_id 데이터 조회 성공 - herbs: " . json_encode($herbs) . ", source: $source, snippet: $snippet");

    echo json_encode([
        'herbs' => $herbs,
        'source' => $source,
        'snippet' => $snippet
    ]);
} catch (PDOException $e) {
    error_log("fetch_snippet.php: 데이터 조회 실패, fangzi_id: $fangzi_id, 오류: " . $e->getMessage());
    echo json_encode(['error' => '데이터 조회 실패']);
}
?>