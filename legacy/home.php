<?php

/**
 * home.php
 * Phase 17: Premium Academic Dashboard - Dynamic Database Bulletins & Announcements
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

check_auth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch detailed user info
$user_stmt = $conn->prepare("SELECT fullname, matric_number, department, level, programme, last_login 
                             FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

// Dashboard Data
$stats = get_user_stats($conn, $user_id);
$recommendations = get_recommendations($conn, $user_data['department'], $user_data['level']);
$trending = get_trending_books($conn);
$activities = get_recent_activity($conn, $user_id);

// Continue Reading (Phase 17)
$history_stmt = $conn->prepare("SELECT b.uuid, b.title, b.author, rh.progress_percent, rh.last_opened 
                                FROM reading_history rh 
                                JOIN books b ON rh.book_id = b.id 
                                WHERE rh.user_id = ? 
                                ORDER BY rh.last_opened DESC LIMIT 2");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history = $history_stmt->get_result();

// Fetch live active announcements from the SQL database (Requirement 4)
$ann_stmt = $conn->query("SELECT title, message, created_at 
                          FROM announcements 
                          WHERE is_active = 1 
                          AND (start_at IS NULL OR start_at <= NOW()) 
                          AND (end_at IS NULL OR end_at >= NOW()) 
                          ORDER BY created_at DESC LIMIT 3");
$announcements = [];
if ($ann_stmt) {
    while ($ann = $ann_stmt->fetch_assoc()) {
        $announcements[] = $ann;
    }
}

$bookmarked_ids = [];
$bookmark_stmt = $conn->prepare("SELECT book_id FROM bookmarks WHERE user_id = ?");
$bookmark_stmt->bind_param("i", $user_id);
$bookmark_stmt->execute();
$bookmark_result = $bookmark_stmt->get_result();
while ($bookmark_row = $bookmark_result->fetch_assoc()) {
    $bookmarked_ids[(int)$bookmark_row['book_id']] = true;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php render_meta([
        'title'       => 'Dashboard | NACOS App',
        'description' => 'Your academic hub for accessing books, tracking reading progress, managing bookmarks and using NACOS App services.',
        'og_type'     => 'website',
    ]); ?>

    <!-- Google Fonts Theme Integration -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/animation.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0b8f3a;
            --primary-yellow: #ffcc00;
            --dark-green: #066b2a;
            --light-green: #1fb04e;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --surface: #ffffff;

            --card-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            --border-subtle: 1px solid rgba(15, 23, 42, 0.06);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-metrics: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-dark);
            font-family: var(--font-body);
            -webkit-font-smoothing: antialiased;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 300px 1fr 340px;
            gap: 28px;
            max-width: 1600px;
            margin: 24px auto;
            padding: 0 24px 48px;
        }

        .hero-section {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #066b2a 0%, #0b8f3a 50%, #1fb04e 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(11, 143, 58, 0.3);
        }

        .hero-section::after {
            content: '';
            position: absolute;
            right: -10px;
            top: -50px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-text h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 3vw, 2.75rem);
            margin: 0 0 10px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .hero-text p {
            max-width: 600px;
            opacity: 0.9;
            font-size: 1.05rem;
            margin: 0 0 24px;
            font-weight: 400;
            line-height: 1.5;
        }

        .hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-family: var(--font-metrics);
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-avatar {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            z-index: 2;
        }

        .hero-avatar p {
            margin: 12px 0 0;
            font-size: 0.8rem;
            opacity: 0.8;
            font-family: var(--font-metrics);
            letter-spacing: 0.02em;
        }

        .sidebar-left {
            grid-column: 1 / 2;
        }

        .main-content {
            grid-column: 2 / 3;
        }

        .side-content {
            grid-column: 3 / 4;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-header h2 {
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-all {
            font-size: 0.85rem;
            color: var(--primary-green);
            font-weight: 700;
            font-family: var(--font-metrics);
            transition: var(--transition);
        }

        .view-all:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }

        .card-container {
            background: var(--surface);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            border: var(--border-subtle);
            margin-bottom: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .icon-green {
            background: #f0fdf4;
            color: #16a34a;
        }

        .icon-yellow {
            background: #fefce8;
            color: #ca8a04;
        }

        .icon-purple {
            background: #faf5ff;
            color: #9333ea;
        }

        .stat-info h3 {
            font-family: var(--font-metrics);
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .stat-info p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 2px 0 0;
            font-weight: 500;
        }

        .horizontal-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }

        .book-mini-card {
            border-radius: 12px;
            overflow: hidden;
            border: var(--border-subtle);
            background: var(--surface);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .book-mini-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.15);
        }

        .book-mini-card img {
            width: 100%;
            height: 170px;
            object-fit: cover;
            border-bottom: var(--border-subtle);
        }

        .book-mini-card h4 {
            margin: 12px 12px 4px;
            font-size: 0.88rem;
            color: var(--text-dark);
            line-height: 1.3;
            font-weight: 600;
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.6em;
            font-family: var(--font-display);
        }

        .book-mini-card p {
            margin: 0 12px 12px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bookmark-action-home {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.96);
            color: var(--primary-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s ease;
        }

        .bookmark-action-home:hover {
            transform: scale(1.06);
        }

        .bookmark-action-home.is-active {
            background: rgba(11, 143, 58, 0.12);
        }

        .profile-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(15, 23, 42, 0.08);
            font-size: 0.85rem;
        }

        .profile-row:last-child {
            border-bottom: none;
        }

        .profile-row span {
            color: var(--text-secondary);
        }

        .profile-row strong {
            color: var(--text-dark);
            font-family: var(--font-metrics);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .activity-item {
            position: relative;
            padding-left: 22px;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 14px;
            bottom: -14px;
            width: 2px;
            background: #e2e8f0;
        }

        .activity-item:last-child::before {
            display: none;
        }

        .activity-dot {
            position: absolute;
            left: 0;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-green);
            border: 2.5px solid white;
            box-shadow: 0 0 0 1px rgba(11, 143, 58, 0.2);
            z-index: 1;
        }

        .activity-content {
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: var(--border-subtle);
        }

        .activity-content p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-dark);
            font-weight: 500;
            line-height: 1.4;
        }

        .activity-content span {
            display: block;
            margin-top: 4px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-family: var(--font-metrics);
        }

        .progress-track {
            height: 6px;
            background: #f1f5f9;
            border-radius: 10px;
            margin: 8px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            border-radius: 10px;
        }

        /* Announcement Item */
        .announcement-item {
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
            padding: 12px 0;
        }

        .announcement-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr 300px;
            }

            .sidebar-left {
                display: none;
            }
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                display: flex;
                flex-direction: column;
                gap: 20px;
                padding: 0 16px 32px;
                margin: 16px auto;
            }

            .side-content {
                display: block;
                width: 100%;
            }

            .hero-section {
                padding: 28px 24px;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/includes/header.php'; ?>

    <main class="dashboard-grid">

        <!-- Hero Section Banner -->
        <section class="hero-section">
            <div class="hero-text">
                <h1><?= get_greeting() ?>, <?= safe_output(explode(' ', $user_data['fullname'])[0]) ?> 👋</h1>
                <p>Welcome back to your academic hub.</p>
                <div class="hero-badges">
                    <span class="badge"><i class="ri-user-star-line"></i> <?= safe_output(ucfirst($role)) ?></span>
                    <span class="badge"><i class="ri-government-line"></i> <?= safe_output(format_department($user_data['department'])) ?></span>
                    <span class="badge"><i class="ri-building-line"></i> <?= safe_output($user_data['level']) ?></span>
                </div>
            </div>
        </section>

        <!-- Sidebar Left (Academic Info) -->
        <aside class="sidebar-left">
            <div class="card-container">
                <div class="section-header">
                    <h2>Academic Profile</h2>
                </div>
                <div>
                    <div class="profile-row">
                        <span>Matric No</span>
                        <strong><?= safe_output($user_data['matric_number']) ?></strong>
                    </div>
                    <div class="profile-row">
                        <span>Programme</span>
                        <strong><?= safe_output(normalize_programme($user_data['programme'])) ?></strong>
                    </div>
                    <div class="profile-row">
                        <span>Department</span>
                        <strong><?= safe_output(format_department($user_data['department'])) ?></strong>
                    </div>
                    <div class="profile-row">
                        <span>Level</span>
                        <strong><?= safe_output($user_data['level']) ?></strong>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Dashboard Content -->
        <section class="main-content">

            <!-- Quick Stats Layout -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="ri-book-open-line"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['books_read'] ?></h3>
                        <p>Books Read</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="ri-bookmark-3-line"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['bookmarks'] ?></h3>
                        <p>Bookmarks</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-yellow"><i class="ri-upload-cloud-2-line"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['uploads'] ?></h3>
                        <p>Uploaded</p>
                    </div>
                </div>
            </div>

            <!-- Continue Reading Cards -->
            <?php if ($history->num_rows > 0): ?>
                <div class="section-header">
                    <h2><i class="ri-play-list-play-line" style="color: var(--primary-green);"></i> Continue Reading</h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 28px;">
                    <?php while ($h = $history->fetch_assoc()): ?>
                        <div class="card-container" style="display: flex; gap: 14px; align-items: center; padding: 16px; margin: 0;">
                            <div style="width: 48px; height: 60px; background: #f1f5f9; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #64748b; border: var(--border-subtle);">
                                <i class="ri-file-pdf-2-line"></i>
                            </div>
                            <div style="flex: 1; overflow: hidden;">
                                <h4 style="font-family: var(--font-display); font-size: 0.9rem; font-weight: 700; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-dark);"><?= safe_output($h['title']) ?></h4>
                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?= $h['progress_percent'] ?>%;"></div>
                                </div>
                                <span style="font-family: var(--font-metrics); font-size: 0.75rem; color: var(--text-secondary); font-weight: 500;"><?= $h['progress_percent'] ?>% Complete • <?= date('M d', strtotime($h['last_opened'])) ?></span>
                            </div>
                            <a href="<?= $BASE_URL ?>view/reader.php?uuid=<?= $h['uuid'] ?>" style="color: var(--primary-green); font-size: 24px; transition: var(--transition); display: flex;"><i class="ri-play-circle-fill"></i></a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Recommended For You Section -->
            <div class="section-header">
                <h2><i class="ri-sparkling-2-line" style="color: var(--primary-yellow);"></i> Recommended For You</h2>
                <a href="<?= $BASE_URL ?>library/library.php" class="view-all">See All Library</a>
            </div>
            <div class="card-container">
                <div class="horizontal-list">
                    <?php while ($rec = $recommendations->fetch_assoc()): ?>
                        <div style="position: relative;">
                            <button class="bookmark-action-home" type="button" data-book-id="<?= (int)$rec['id'] ?? 0 ?>" data-bookmarked="<?= isset($bookmarked_ids[(int)($rec['id'] ?? 0)]) ? '1' : '0' ?>" aria-label="Save book" style="position:absolute;top:10px;right:10px;z-index:3;">
                                <i class="<?= isset($bookmarked_ids[(int)($rec['id'] ?? 0)]) ? 'ri-bookmark-fill' : 'ri-bookmark-line' ?>"></i>
                            </button>
                            <a href="<?= $BASE_URL ?>view/reader.php?uuid=<?= $rec['uuid'] ?>" style="text-decoration: none;">
                                <div class="book-mini-card">
                                    <img src="<?= !empty($rec['thumbnail_path']) ? $BASE_URL . $rec['thumbnail_path'] : $BASE_URL . 'assets/images/NACOS_LOGO.png' ?>" alt="Cover">
                                    <h4><?= safe_output($rec['title']) ?></h4>
                                    <p><?= safe_output($rec['author']) ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <!-- Right Sidebar (Activities & Dynamic Database Announcements) -->
        <aside class="side-content">
            <div class="card-container">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                </div>
                <div class="activity-list">
                    <?php while ($act = $activities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div class="activity-content">
                                <p><?= str_replace('_', ' ', ucfirst($act['action'])) ?></p>
                                <span><?= date('d M, H:i', strtotime($act['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div> 

            <!-- Dynamic Announcements Notice Card (Requirement 4) -->
            <div class="card-container" style="background: linear-gradient(180deg, #ffffff 0%, #fffef0 100%); border: 1px solid rgba(255, 204, 0, 0.25);">
                <div class="section-header">
                    <h2>Announcements</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="announcement-item">
                                <h4 style="margin: 0 0 6px; font-family: var(--font-display); font-size: 0.9rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                                    <i class="ri-megaphone-line" style="color: #d97706;"></i>
                                    <?= safe_output($ann['title']) ?>
                                </h4>
                                <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0 0 4px; line-height: 1.4;">
                                    <?= safe_output($ann['message']) ?>
                                </p>
                                <span style="font-size: 0.7rem; color: #a1a1aa; font-family: var(--font-metrics);"><?= date('d M Y, H:i', strtotime($ann['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">No active bulletins or announcements found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </main>

    <!-- Floating Assistant Core Component -->
    <div class="ai-assistant-btn" id="aiBtn">
        <i class="ri-robot-line"></i>
    </div>

    <div class="ai-panel" id="aiPanel">
        <div class="ai-header">
            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="AI">
            <div>
                <strong style="display: block; font-size: 0.95rem; font-family: var(--font-display); font-weight: 700; line-height: 1.2;">NACOS Assistant</strong>
                <span style="font-size: 0.72rem; opacity: 0.85; font-family: var(--font-metrics); font-weight: 500;">Online & Ready to Help</span>
            </div>
            <i class="ri-close-line" id="aiClose" style="margin-left: auto; cursor: pointer; font-size: 20px;"></i>
        </div>
        <div class="ai-body" id="aiBody">
            <div class="ai-msg bot">
                Hello <?= safe_output(explode(' ', $user_data['fullname'])[0]) ?>! I'm your academic assistant. How can I help you navigate the library today?
            </div>
        </div>
        <div class="ai-input-area">
            <input type="text" id="aiInput" placeholder="Type your question..." autocomplete="off">
            <button class="btn-read" id="aiSend" style="padding: 0;" type="button" aria-label="Send message"><i class="ri-send-plane-fill"></i></button>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        const aiBtn = document.getElementById('aiBtn');
        const aiPanel = document.getElementById('aiPanel');
        const aiClose = document.getElementById('aiClose');
        const aiInput = document.getElementById('aiInput');
        const aiSend = document.getElementById('aiSend');
        const aiBody = document.getElementById('aiBody');

        aiBtn.addEventListener('click', () => aiPanel.style.display = 'flex');
        aiClose.addEventListener('click', () => aiPanel.style.display = 'none');

        function addAiMessage(content, type) {
            const message = document.createElement('div');
            message.className = 'ai-msg ' + type;
            message.innerHTML = content;
            aiBody.appendChild(message);
            aiBody.scrollTop = aiBody.scrollHeight;
        }

        async function sendAiMessage() {
            const message = aiInput.value.trim();
            if (!message || aiSend.disabled) return;

            addAiMessage(message.replace(/[&<>"']/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
            }), 'user');
            aiInput.value = '';
            aiSend.disabled = true;
            aiInput.disabled = true;

            const formData = new FormData();
            formData.append('message', message);
            formData.append('csrf_token', '<?= csrf_token() ?>');

            try {
                const response = await fetch('<?= $BASE_URL ?>ai_handler.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'}
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'The assistant could not respond.');
                }
                addAiMessage(data.response, 'bot');
            } catch (error) {
                addAiMessage(error.message || 'The assistant is temporarily unavailable. Please try again.', 'bot');
            } finally {
                aiSend.disabled = false;
                aiInput.disabled = false;
                aiInput.focus();
            }
        }

        aiSend.addEventListener('click', sendAiMessage);
        aiInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') sendAiMessage();
        });

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