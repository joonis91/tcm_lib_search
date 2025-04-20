<?php
$database_path = __DIR__ . '/metadata.sqlite';
try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8';");
    error_log("search.php: 데이터베이스 연결 성공");
} catch (PDOException $e) {
    error_log("search.php: 데이터베이스 연결 실패: " . $e->getMessage());
    die("데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()));
}

include 'includes/header.php';

// 검색어 및 필터링 파라미터 처리
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_source = isset($_GET['source']) ? trim($_GET['source']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// 검색어 정규화 및 분리
$search_terms = [];
if ($search_query) {
    $search_query = preg_replace('/[\.\.\.:;,\d]/u', ' ', $search_query); // 특수문자 제거
    $search_query = preg_replace('/\s+/u', ' ', trim($search_query)); // 다중 공백 제거
    if (empty($search_query)) {
        echo '<div class="alert alert-info">유효한 검색어를 입력하세요.</div>';
        $search_query = '';
    } else {
        $search_terms = array_filter(explode(' ', $search_query));
    }
}
error_log("search.php: 정규화된 검색어: '$search_query', 검색 단어: " . json_encode($search_terms));

// 출전 목록 추출
$sources = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT article FROM fangzi_article");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $filename = basename($row['article']);
        $source_name = pathinfo($filename, PATHINFO_FILENAME);
        if (!in_array($source_name, $sources)) {
            $sources[] = $source_name;
        }
    }
    sort($sources);
    error_log("search.php: 추출된 출전 목록: " . json_encode($sources));
} catch (PDOException $e) {
    error_log("search.php: 출전 목록 추출 실패: " . $e->getMessage());
}

// 페이지네이션 설정
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// 검색 쿼리 처리
$results = [];
$total_items = 0;

try {
    if (!empty($search_terms)) {
        // 검색 조건 동적 생성
        $conditions = [];
        $params = [];
        foreach ($search_terms as $term) {
            $conditions[] = "(f.herb LIKE ? OR fs.snippet LIKE ? OR fs.fangzi_name LIKE ?)";
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
        $where_clause = implode(' AND ', $conditions);

        if ($selected_source) {
            // 특정 출전 내 검색
            $sql_count = "
                SELECT COUNT(DISTINCT f.id)
                FROM fangzi f
                LEFT JOIN fangzi_snippets fs ON f.id = fs.fangzi_id
                JOIN fangzi_article fa ON f.id = fa.fangzi_id
                WHERE $where_clause AND fa.article LIKE ?
            ";
            $sql_select = "
                SELECT DISTINCT f.id
                FROM fangzi f
                LEFT JOIN fangzi_snippets fs ON f.id = fs.fangzi_id
                JOIN fangzi_article fa ON f.id = fa.fangzi_id
                WHERE $where_clause AND fa.article LIKE ?
                ORDER BY f.id
                LIMIT ? OFFSET ?
            ";
            $params[] = '%' . $selected_source . '%';
            $params_select = array_merge($params, [$items_per_page, $offset]);

            $stmt = $pdo->prepare($sql_count);
            $stmt->execute($params);
            $total_items = $stmt->fetchColumn();
            error_log("search.php: 특정 출전 '$selected_source' 내 검색어 '$search_query'로 검색, 결과 개수: $total_items, 쿼리: $sql_count, 파라미터: " . json_encode($params));

            $stmt = $pdo->prepare($sql_select);
            $stmt->execute($params_select);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("search.php: 특정 출전 검색 결과: " . json_encode($results));
        } else {
            // 전체 검색
            $sql_count = "
                SELECT COUNT(DISTINCT f.id)
                FROM fangzi f
                LEFT JOIN fangzi_snippets fs ON f.id = fs.fangzi_id
                WHERE $where_clause
            ";
            $sql_select = "
                SELECT DISTINCT f.id
                FROM fangzi f
                LEFT JOIN fangzi_snippets fs ON f.id = fs.fangzi_id
                WHERE $where_clause
                ORDER BY f.id
                LIMIT ? OFFSET ?
            ";
            $params_select = array_merge($params, [$items_per_page, $offset]);

            $stmt = $pdo->prepare($sql_count);
            $stmt->execute($params);
            $total_items = $stmt->fetchColumn();
            error_log("search.php: 전체 검색, 검색어 '$search_query', 결과 개수: $total_items, 쿼리: $sql_count, 파라미터: " . json_encode($params));

            $stmt = $pdo->prepare($sql_select);
            $stmt->execute($params_select);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("search.php: 전체 검색 결과: " . json_encode($results));
        }
    } elseif ($selected_source) {
        // 출전만 선택된 경우
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT fa.fangzi_id)
            FROM fangzi_article fa
            WHERE fa.article LIKE ?
        ");
        $stmt->execute(['%' . $selected_source . '%']);
        $total_items = $stmt->fetchColumn();
        error_log("search.php: 출전 '$selected_source'만 선택, 결과 개수: $total_items");

        $stmt = $pdo->prepare("
            SELECT DISTINCT fa.fangzi_id AS id
            FROM fangzi_article fa
            WHERE fa.article LIKE ?
            ORDER BY fa.fangzi_id
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(['%' . $selected_source . '%', $items_per_page, $offset]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 기본 목록
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM fangzi");
        $stmt->execute();
        $total_items = $stmt->fetchColumn();
        error_log("search.php: 기본 목록, 결과 개수: $total_items");

        $stmt = $pdo->prepare("
            SELECT DISTINCT id
            FROM fangzi
            ORDER BY id
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$items_per_page, $offset]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("search.php: 데이터 조회 실패: " . $e->getMessage() . ", 쿼리: " . ($stmt ? $stmt->queryString : 'N/A') . ", 검색어: '$search_query', 파라미터: " . json_encode($params ?? []));
    echo '<div class="alert alert-danger">검색 중 오류가 발생했습니다. 검색어를 확인하거나 관리자에게 문의하세요.</div>';
    $results = [];
    $total_items = 0;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCM 검색</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- 사이드바 -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="position-sticky pt-3 sidebar-content">
                <h5 class="sidebar-heading px-3 mt-4 mb-1">출전</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo !$selected_source ? 'active' : ''; ?>" href="search.php">
                            전체
                        </a>
                    </li>
                    <?php foreach ($sources as $source): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $selected_source === $source ? 'active' : ''; ?>" 
                               href="search.php?source=<?php echo urlencode($source); ?>">
                                <?php echo htmlspecialchars($source); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>

        <!-- 메인 컨텐츠 -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <!-- 검색 폼 -->
            <div class="row mb-4">
                <div class="col-md-6 offset-md-3">
                    <form method="GET" action="search.php">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="검색어 입력..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary" type="submit">검색</button>
                            <?php if ($selected_source): ?>
                                <input type="hidden" name="source" value="<?php echo htmlspecialchars($selected_source); ?>">
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($selected_source): ?>
                        <p class="mt-2">현재 선택된 출전: <?php echo htmlspecialchars($selected_source); ?>에서 검색 중입니다.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 검색 결과 -->
            <div id="results">
                <?php if (empty($results)): ?>
                    <p>검색 결과가 없습니다. 다른 검색어나 출전을 시도해 보세요.</p>
                    <?php if (!empty($search_terms)): ?>
                        <p>제안: 다음 단어로 검색해 보세요: <?php echo htmlspecialchars(implode(', ', $search_terms)); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <h3>검색 결과 (총 <?php echo $total_items; ?>개)</h3>
                    <div class="row">
                        <?php foreach ($results as $row): ?>
                            <?php 
                            $fangzi_id = isset($row['id']) && $row['id'] > 0 ? htmlspecialchars($row['id']) : 'N/A'; 
                            if ($fangzi_id === 'N/A') continue;
                            ?>
                            <div class="col-md-4 mb-4">
                                <div class="card prescription" data-fangzi-id="<?php echo $fangzi_id; ?>">
                                    <div class="card-body">
                                        <h5 class="card-title">처방 ID: <?php echo $fangzi_id; ?></h5>
                                        <p class="card-text"><strong>처방 내용:</strong></p>
                                        <div class="herbs">로딩 중...</div>
                                        <p class="card-text"><strong>출처:</strong></p>
                                        <div class="source">로딩 중...</div>
                                        <p class="card-text"><strong>원문 내용:</strong></p>
                                        <div class="original-text">로딩 중...</div>
                                        <div class="mt-2">
                                            <a href="prescription.php?id=<?php echo $fangzi_id; ?>&source=fangzi" class="btn btn-primary btn-sm">자세히 보기</a>
                                            <div class="dropdown d-inline-block">
                                                <button class="btn btn-warning btn-sm dropdown-toggle" type="button" id="editDropdown<?php echo $fangzi_id; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    수정
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="editDropdown<?php echo $fangzi_id; ?>">
                                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $fangzi_id; ?>&referer=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">모두 편집</a></li>
                                                    <li><a class="dropdown-item disabled" href="#" onclick="alert('약재 정보는 편집할 수 없습니다. 관리자에게 문의하세요.');">약재 편집</a></li>
                                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $fangzi_id; ?>&source=fangzi_article&referer=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">문서 편집</a></li>
                                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $fangzi_id; ?>&source=fangzi_snippets&referer=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">원문 편집</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 페이지네이션 -->
                    <?php
                    $total_pages = ceil($total_items / $items_per_page);
                    if ($total_pages > 1):
                    ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="search.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">이전</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="search.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="search.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">다음</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const prescriptions = document.querySelectorAll('.prescription');
    if (prescriptions.length === 0) {
        console.log('검색 결과가 없어 드롭다운 요소를 초기화하지 않습니다.');
        return;
    }

    // 드롭다운 초기화
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    if (dropdowns.length === 0) {
        console.warn('드롭다운 요소를 찾을 수 없습니다.');
    }
    dropdowns.forEach(dropdown => {
        try {
            new bootstrap.Dropdown(dropdown);
            console.log('드롭다운 초기화 성공:', dropdown.id);
        } catch (e) {
            console.error('드롭다운 초기화 실패:', dropdown.id, e);
        }
    });

    const searchTerms = <?php echo json_encode($search_terms); ?>;
    prescriptions.forEach(prescription => {
        const fangziId = prescription.getAttribute('data-fangzi-id');
        if (!fangziId || fangziId === 'N/A') return;

        fetch(`fetch_snippet.php?fangzi_id=${fangziId}`)
            .then(response => {
                if (!response.ok) throw new Error('네트워크 응답이 실패했습니다.');
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error(`fangzi_id ${fangziId}: ${data.error}`);
                    prescription.querySelector('.herbs').innerHTML = '<p class="text-danger">약재 로드 실패</p>';
                    prescription.querySelector('.source').innerHTML = '<p class="text-danger">출전 로드 실패</p>';
                    prescription.querySelector('.original-text').innerHTML = '<p class="text-danger">원문 로드 실패</p>';
                    return;
                }

                const herbsContainer = prescription.querySelector('.herbs');
                herbsContainer.innerHTML = data.herbs.length ? data.herbs.join(', ') : '약재 정보 없음';

                const sourceContainer = prescription.querySelector('.source');
                sourceContainer.innerHTML = data.source || '출전 정보 없음';

                const snippetContainer = prescription.querySelector('.original-text');
                let snippetText = data.snippet || '원문 내용 없음';
                if (searchTerms.length && snippetText !== '원문 내용 없음') {
                    const regex = new RegExp(`(${searchTerms.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'giu');
                    snippetText = snippetText.replace(regex, '<span class="highlight">$1</span>');
                }
                snippetContainer.innerHTML = snippetText;
            })
            .catch(error => {
                console.error(`fangzi_id ${fangziId} 데이터 로드 실패:`, error);
                prescription.querySelector('.herbs').innerHTML = '<p class="text-danger">약재 로드 실패</p>';
                prescription.querySelector('.source').innerHTML = '<p class="text-danger">출전 로드 실패</p>';
                prescription.querySelector('.original-text').innerHTML = '<p class="text-danger">원문 로드 실패</p>';
            });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
$footer_file = __DIR__ . '/includes/footer.php';
if (file_exists($footer_file)) {
    include $footer_file;
} else {
    error_log("search.php: 푸터 파일 포함 실패 - 파일 경로: $footer_file");
    die("푸터 파일을 찾을 수 없습니다. 관리자에게 문의하세요.");
}
?>
</body>
</html>