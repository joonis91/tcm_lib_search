<?php
// 헤더 파일 로드 로그
error_log("header.php: 헤더 파일 로드");
?>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">고전문헌 처방검색 데이터베이스</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">홈</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?search=">검색</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>