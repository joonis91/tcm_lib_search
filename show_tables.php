<?php
// 모든 테이블 보여주기
// 데이터베이스 파일 경로 설정
$db_file =  __DIR__ . '/metadata.sqlite';

// SQLite 연결
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 데이터베이스 구조 가져오기
    echo "<h2>데이터베이스 구조</h2>";

    // 테이블 목록 가져오기
    $tables_query = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        foreach ($tables as $table) {
            echo "<h3>테이블: $table</h3>";

            // 테이블 필드 정보 가져오기
            $columns_query = $pdo->query("PRAGMA table_info($table);");
            $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);

            echo "<table border='1' cellspacing='0' cellpadding='5'>";
            echo "<tr><th>컬럼명</th><th>데이터 타입</th><th>기본값</th><th>PK 여부</th></tr>";

            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['name']}</td>";
                echo "<td>{$column['type']}</td>";
                echo "<td>" . ($column['dflt_value'] ?? "NULL") . "</td>";
                echo "<td>" . ($column['pk'] ? "Yes" : "No") . "</td>";
                echo "</tr>";
            }

            echo "</table><p>";
        }
    } else {
        echo "<p>데이터베이스에 테이블이 없습니다.</p>";
    }
} catch (PDOException $e) {
    echo "데이터베이스 연결 오류: " . $e->getMessage();
}

?>