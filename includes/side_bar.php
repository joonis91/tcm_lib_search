<?php
// 데이터베이스 연결
$database_path = __DIR__ . '/../metadata.sqlite';
try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8';");
    error_log("side_bar.php: 데이터베이스 연결 성공");
} catch (PDOException $e) {
    error_log("side_bar.php: 데이터베이스 연결 실패: " . $e->getMessage());
    die("데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()));
}

// 캐시 설정
$cache_file = __DIR__ . '/cache/sidebar_cache.json';
$cache_ttl = 10800; // 3시간 (초)
$sources = [];

// 캐시 확인 및 유효성 검사
$cache_valid = false;
if (file_exists($cache_file)) {
    $cache_content = file_get_contents($cache_file);
    if ($cache_content === false) {
        error_log("side_bar.php: 캐시 파일 읽기 실패: $cache_file");
    } else {
        $cache_data = json_decode($cache_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("side_bar.php: 캐시 파일 JSON 파싱 실패: " . json_last_error_msg());
        } elseif (!$cache_data || !isset($cache_data['timestamp']) || !isset($cache_data['sources'])) {
            error_log("side_bar.php: 캐시 파일 구조 오류: " . json_encode($cache_data));
        } elseif (time() - $cache_data['timestamp'] >= $cache_ttl) {
            error_log("side_bar.php: 캐시 파일 만료됨, timestamp: " . $cache_data['timestamp']);
        } else {
            $sources = $cache_data['sources'];
            if (!is_array($sources) || empty($sources)) {
                error_log("side_bar.php: 캐시 파일의 sources가 비어 있거나 유효하지 않음: " . json_encode($sources));
            } else {
                $cache_valid = true;
                error_log("side_bar.php: 캐시에서 출전 목록 로드 성공, 소스 개수: " . count($sources));
            }
        }
    }
}

// 캐시가 유효하지 않은 경우 DB에서 조회
if (!$cache_valid) {
    try {
        $sources = [];
        $stmt = $pdo->query("SELECT DISTINCT article FROM fangzi_article");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filename = basename($row['article']);
            $source_name = pathinfo($filename, PATHINFO_FILENAME);
            if (!in_array($source_name, $sources)) {
                $sources[] = $source_name;
            }
        }
        sort($sources);
        error_log("side_bar.php: 데이터베이스에서 출전 목록 조회, 소스 개수: " . count($sources));

        // 캐시 저장
        $cache_data = [
            'timestamp' => time(),
            'sources' => $sources
        ];
        $cache_dir = dirname($cache_file);
        if (!is_dir($cache_dir)) {
            if (!mkdir($cache_dir, 0755, true)) {
                error_log("side_bar.php: 캐시 디렉토리 생성 실패: $cache_dir");
            }
        }
        if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            error_log("side_bar.php: 캐시 파일 저장 실패: $cache_file");
        } else {
            error_log("side_bar.php: 캐시 파일 저장 성공: $cache_file");
        }
    } catch (PDOException $e) {
        error_log("side_bar.php: 출전 목록 조회 실패: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("side_bar.php: 캐시 저장 실패: " . $e->getMessage());
    }
}

// 선택된 출전
$selected_source = isset($_GET['source']) ? trim($_GET['source']) : '';
?>

<style>
/* 전체 레이아웃 설정 */
body {
    margin: 0;
    font-family: Arial, sans-serif;
}

/* 컨테이너: 사이드바와 메인 컨텐츠를 포함 */
.container {
    display: flex;
    min-height: 100vh;
}

/* 사이드바 스타일 */
#sidebar {
    width: 400px;
    background-color: #f4f4f4;
    border-right: 1px solid #ddd;
    padding: 20px;
    box-sizing: border-box;
}

#sidebar h5 {
    margin: 0 0 10px;
    font-size: 1.2em;
    color: #333;
}

#sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

#sidebar ul li {
    margin-bottom: 5px;
}

#sidebar ul li a {
    display: block;
    padding: 8px 10px;
    color: #555;
    text-decoration: none;
    border-radius: 4px;
	font-family: Arial, sans-serif;

}

#sidebar ul li a:hover {
    background-color: #e0e0e0;
}

#sidebar ul li a.active {
    background-color: #007bff;
    color: white;
}

#sidebar ul li .text-muted {
    color: #999;
    padding: 8px 10px;
    display: block;
}

/* 메인 컨텐츠 영역 (참고용) */
.main-content {
    flex: 1;
    padding: 20px;
    background-color: #fff;
}
</style>

<div class="container">
    <nav id="sidebar">
        <ul>
            <?php if (empty($sources)): ?>
                <li>
                    <span class="text-muted">출전 목록을 로드할 수 없습니다.</span>
                </li>
            <?php else: ?>
                <?php foreach ($sources as $source): ?>
                    <li>
                        <a class="<?php echo $selected_source === $source ? 'active' : ''; ?>" 
                           href="index.php?source=<?php echo urlencode($source); ?>">
                            <?php echo htmlspecialchars($source); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </nav>
    <!-- 메인 컨텐츠는 index.php에서 처리한다고 가정 -->
</div>