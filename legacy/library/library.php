<?php

/**
 * library/library.php
 * Premium Academic Catalog Search & Filter View
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$level_filter = isset($_GET['level']) ? trim((string)$_GET['level']) : '';

// Computer Science only library. Filter by class level instead of department.
$sql = "SELECT b.id, b.uuid, b.title, b.author, b.department, b.level, b.thumbnail_path
        FROM books b
        WHERE b.status = 'approved'
        AND b.department = 'computer-science'";

$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "ss";
}

if (!empty($level_filter) && $level_filter !== 'all') {
    $sql .= " AND b.level = ?";
    $params[] = $level_filter;
    $types .= "s";
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

$bookmarked_ids = [];
if ($user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0) {
    $bookmark_stmt = $conn->prepare("SELECT book_id FROM bookmarks WHERE user_id = ?");
    $bookmark_stmt->bind_param("i", $user_id);
    $bookmark_stmt->execute();
    $bookmark_result = $bookmark_stmt->get_result();
    while ($bookmark_row = $bookmark_result->fetch_assoc()) {
        $bookmarked_ids[(int)$bookmark_row['book_id']] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php render_meta([
        'title'       => 'Browse Books | NACOS App',
        'description' => 'Browse and search the NACOS App book catalog for academic books, study materials and resources for Computer Science students.',
        'og_type'     => 'website',
    ]); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/library.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0b8f3a;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --surface: #ffffff;
            --border-subtle: 1px solid rgba(15, 23, 42, 0.06);
            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-metrics: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            font-family: var(--font-body);
            color: var(--text-dark);
            margin: 0;
        }

        .library-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .search-hero {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            border: var(--border-subtle);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
            margin-bottom: 32px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr 300px auto;
            gap: 16px;
        }

        @media (max-width: 900px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .search-form input[type="text"],
        .search-form select {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            font-size: 0.92rem;
            outline: none;
            box-sizing: border-box;
            font-family: var(--font-body);
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .search-form select {
            padding-left: 20px;
        }

        .search-form input[type="text"]:focus,
        .search-form select:focus {
            border-color: var(--primary-green);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.1);
        }

        .btn-search {
            background: var(--primary-green);
            color: #ffffff;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-family: var(--font-metrics);
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-search:hover {
            background: #066b2a;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }

        .book-card {
            background: var(--surface);
            border-radius: 16px;
            border: var(--border-subtle);
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.15);
        }

        .thumbnail-frame {
            position: relative;
            height: 220px;
            background: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: var(--border-subtle);
        }

        .thumbnail-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .book-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .bookmark-action {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s ease, background 0.2s ease;
            z-index: 2;
        }

        .bookmark-action:hover {
            transform: scale(1.06);
        }

        .bookmark-action.is-active {
            background: rgba(11, 143, 58, 0.14);
            color: var(--primary-green);
        }

        .book-card-body h3 {
            font-family: var(--font-display);
            font-size: 1.05rem;
            margin: 0 0 6px;
            font-weight: 700;
            line-height: 1.4;
            color: var(--text-dark);
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8em;
        }

        .book-card-body p {
            margin: 0 0 14px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .pill {
            background: #f1f5f9;
            color: var(--text-secondary);
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 30px;
            font-family: var(--font-metrics);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            align-self: flex-start;
            margin-top: auto;
        }

        .btn-read {
            display: block;
            text-align: center;
            background: var(--primary-green);
            color: #ffffff;
            text-decoration: none;
            padding: 12px;
            border-radius: 10px;
            font-family: var(--font-metrics);
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 16px;
            transition: background 0.15s ease;
        }

        .btn-read:hover {
            background: #066b2a;
        }

        .no-results {
            text-align: center;
            padding: 80px 24px;
            background: #ffffff;
            border-radius: 16px;
            border: var(--border-subtle);
        }

        .floating-action-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            background: #ffcc00;
            color: var(--text-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 24px rgba(255, 204, 0, 0.4);
            text-decoration: none;
            transition: transform 0.2s ease;
            z-index: 999;
        }

        .floating-action-btn:hover {
            transform: scale(1.05) rotate(90deg);
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="library-container">

        <section class="search-hero">
            <h1 style="font-family: var(--font-display); font-weight: 800; font-size: 1.8rem; margin: 0 0 20px; letter-spacing: -0.02em;">Digital Catalog</h1>

            <form action="" method="GET" class="search-form">
                <div class="input-wrapper">
                    <i class="ri-search-line"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search books by title, author...">
                </div>

                <select name="level">
                    <option value="all">All Levels</option>
                    <option value="ND1" <?= $level_filter === 'ND1' ? 'selected' : '' ?>>ND1</option>
                    <option value="ND2" <?= $level_filter === 'ND2' ? 'selected' : '' ?>>ND2</option>
                    <option value="ND3" <?= $level_filter === 'ND3' ? 'selected' : '' ?>>ND3</option>
                    <option value="HND1" <?= $level_filter === 'HND1' ? 'selected' : '' ?>>HND1</option>
                    <option value="HND2" <?= $level_filter === 'HND2' ? 'selected' : '' ?>>HND2</option>
                    <option value="HND3" <?= $level_filter === 'HND3' ? 'selected' : '' ?>>HND3</option>
                </select>

                <button type="submit" class="btn-search">
                    <i class="ri-search-2-line"></i> Search Catalog
                </button>
            </form>
        </section>

        <?php if ($books->num_rows > 0): ?>
            <div class="grid-layout">
                <?php while ($book = $books->fetch_assoc()): ?>
                    <?php
                    $thumb_src = !empty($book['thumbnail_path'])
                        ? $BASE_URL . $book['thumbnail_path']
                        : $BASE_URL . 'assets/images/NACOS_LOGO.png';
                    ?>
                    <article class="book-card">
                        <div class="thumbnail-frame">
                            <button class="bookmark-action <?= isset($bookmarked_ids[$book['id']]) ? 'is-active' : '' ?>" type="button" data-book-id="<?= (int)$book['id'] ?>" data-bookmarked="<?= isset($bookmarked_ids[$book['id']]) ? '1' : '0' ?>" aria-label="Save book">
                                <i class="<?= isset($bookmarked_ids[$book['id']]) ? 'ri-bookmark-fill' : 'ri-bookmark-line' ?>"></i>
                            </button>
                            <img src="<?= htmlspecialchars($thumb_src) ?>" alt="Cover for <?= htmlspecialchars($book['title']) ?>">
                        </div>
                        <div class="book-card-body">
                            <h3><?= safe_output($book['title']) ?></h3>
                            <p>By <?= safe_output($book['author']) ?></p>

                            <span class="pill">
                                <i class="ri-building-line"></i>
                                <?= safe_output($book['level'] ?: 'Level not set') ?>
                            </span>

                            <a href="<?= $BASE_URL ?>view/reader.php?uuid=<?= $book['uuid'] ?>" class="btn-read">
                                Read Now
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="ri-book-open-line" style="font-size: 3rem; color: var(--text-secondary); display: block; margin-bottom: 16px;"></i>
                <h3 style="font-family: var(--font-display); font-weight: 700; margin: 0 0 8px;">No Books Found</h3>
                <p style="color: var(--text-secondary); margin: 0;">We couldn't find any resources matching your parameters.</p>
            </div>
        <?php endif; ?>

    </main>

    <!-- Floating Upload Access Button -->
    <a href="<?= $BASE_URL ?>upload/upload.php" class="floating-action-btn" aria-label="Upload document">
        <i class="ri-add-line"></i>
    </a>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-book-id]');
            if (!toggle) return;
            const bookId = toggle.getAttribute('data-book-id');
            const formData = new FormData();
            formData.append('book_id', bookId);

            fetch('<?= $BASE_URL ?>bookmarks/toggle_bookmark.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    return AppModal.open({ type: 'error', title: 'Bookmark failed', message: data.message || 'Please try again.' });
                }

                toggle.classList.toggle('is-active', data.is_bookmarked);
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.className = data.is_bookmarked ? 'ri-bookmark-fill' : 'ri-bookmark-line';
                }
                toggle.setAttribute('data-bookmarked', data.is_bookmarked ? '1' : '0');
                toggle.setAttribute('aria-label', data.is_bookmarked ? 'Remove bookmark' : 'Save book');
                AppModal.open({ type: data.type, title: data.is_bookmarked ? 'Book bookmarked' : 'Bookmark removed', message: data.message || 'Your bookmark was updated.' });
            }).catch(function () {
                AppModal.open({ type: 'error', title: 'Bookmark failed', message: 'Please check your connection and try again.' });
            });
        });
    </script>

</body>

</html>