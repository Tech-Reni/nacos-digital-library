<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT b.id, b.uuid, b.title, b.author, b.level, b.thumbnail_path, b.department
     FROM bookmarks bm
     JOIN books b ON bm.book_id = b.id
     WHERE bm.user_id = ?
     ORDER BY bm.created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookmarks = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php render_meta([
        'title'       => 'Saved Books | NACOS App',
        'description' => 'Your bookmarked books and saved academic resources, ready for quick access in NACOS App.',
    ]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #0b8f3a;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --surface: #fff;
            --bg: #f3f7fa;
            --border: 1px solid rgba(15, 23, 42, .06);
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text-dark);
            font-family: Inter, sans-serif;
        }

        .page-shell {
            max-width: 1280px;
            margin: 32px auto 56px;
            padding: 0 24px;
        }

        .hero {
            background: linear-gradient(135deg, #066b2a, #0b8f3a);
            color: #fff;
            border-radius: 20px;
            padding: 28px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 12px 30px rgba(11, 143, 58, .18);
            margin-bottom: 24px;
        }

        .hero h1 {
            margin: 0 0 6px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.7rem;
        }

        .hero p {
            margin: 0;
            opacity: .9;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .book-card {
            background: var(--surface);
            border: var(--border);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .04);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .book-card__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .book-card__title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
        }

        .bookmark-toggle {
            border: none;
            background: #f1f5f9;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: var(--primary-green);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }

        .bookmark-toggle.is-active {
            background: rgba(11, 143, 58, .12);
            color: #0b8f3a;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            align-self: flex-start;
            padding: 6px 10px;
            background: #f1f5f9;
            color: var(--text-secondary);
            font-size: .78rem;
            font-weight: 600;
            border-radius: 999px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .actions a,
        .actions button {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
        }

        .actions a {
            background: var(--primary-green);
            color: #fff;
        }

        .actions button {
            background: #f8fafc;
            color: var(--text-dark);
            border: 1px solid rgba(15, 23, 42, .06);
        }

        .empty-state {
            background: var(--surface);
            border: var(--border);
            border-radius: 16px;
            padding: 40px 24px;
            text-align: center;
            color: var(--text-secondary);
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <main class="page-shell">
        <section class="hero">
            <div>
                <h1>Saved Books</h1>
                <p>Keep your favorite books handy for easy reading later.</p>
            </div>
            <div class="pill"><i class="ri-bookmark-fill"></i> <?= $bookmarks->num_rows ?> saved</div>
        </section>

        <?php if ($bookmarks->num_rows > 0): ?>
            <div class="card-grid">
                <?php while ($book = $bookmarks->fetch_assoc()): ?>
                    <article class="book-card">
                        <div class="book-card__top">
                            <span class="pill"><i class="ri-book-open-line"></i> <?= safe_output($book['level'] ?: 'Level not set') ?></span>
                            <button class="bookmark-toggle is-active" type="button" data-book-id="<?= (int)$book['id'] ?>" data-bookmarked="1" aria-label="Remove bookmark">
                                <i class="ri-bookmark-fill"></i>
                            </button>
                        </div>
                        <h3 class="book-card__title"><?= safe_output($book['title']) ?></h3>
                        <p style="margin:0; color:var(--text-secondary); font-size:.9rem;">By <?= safe_output($book['author'] ?: 'Unknown author') ?></p>
                        <div class="actions">
                            <a href="<?= $BASE_URL ?>view/reader.php?uuid=<?= urlencode($book['uuid']) ?>"><i class="ri-book-read-line"></i> Open</a>
                            <button type="button" class="bookmark-toggle is-active" data-book-id="<?= (int)$book['id'] ?>" data-bookmarked="1" aria-label="Remove bookmark"><i class="ri-bookmark-fill"></i> Saved</button>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="ri-bookmark-line" style="font-size:2.4rem; color:var(--primary-green); display:block; margin-bottom:12px;"></i>
                <h3 style="margin:0 0 8px; color:var(--text-dark);">No saved books yet</h3>
                <p style="margin:0;">Bookmark any book from the library or dashboard to see it here instantly.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        document.addEventListener('click', function(event) {
            const toggle = event.target.closest('[data-book-id]');
            if (!toggle) return;
            const bookId = toggle.getAttribute('data-book-id');
            const formData = new FormData();
            formData.append('book_id', bookId);

            fetch('<?= $BASE_URL ?>bookmarks/toggle_bookmark.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function(response) {
                return response.json();
            }).then(function(data) {
                if (!data.success) {
                    return AppModal.open({
                        type: 'error',
                        title: 'Bookmark update failed',
                        message: data.message || 'Please try again.'
                    });
                }

                if (data.action === 'removed') {
                    const card = toggle.closest('.book-card');
                    if (card) {
                        card.remove();
                    }
                    AppModal.open({
                        type: 'warning',
                        title: 'Bookmark removed',
                        message: data.message || 'The book has been removed from your saved list.'
                    });
                } else {
                    AppModal.open({
                        type: 'success',
                        title: 'Bookmark saved',
                        message: data.message || 'The book is now in your saved list.'
                    });
                }
            }).catch(function() {
                AppModal.open({
                    type: 'error',
                    title: 'Bookmark update failed',
                    message: 'Please check your connection and try again.'
                });
            });
        });
    </script>
</body>

</html>