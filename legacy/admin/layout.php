<?php

/**
 * admin/layout.php
 * Core Master Admin Layout Shell matching YabaTech UI language
 */

function render_admin_layout($content_callback, $active_tab, $page_title, $breadcrumbs = [])
{
    global $conn, $BASE_URL;

    // Secure authentication controls
    check_role(['admin']);

    $fullname = $_SESSION['fullname'] ?? 'Administrator';
    $initial = mb_strtoupper(mb_substr($fullname, 0, 1, 'UTF-8'));

    // Fetch counts for moderation alerts badge
    $count_stmt = $conn->query("SELECT COUNT(*) FROM books WHERE status = 'pending'");
    $pending_books_count = $count_stmt ? $count_stmt->fetch_row()[0] : 0;
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="NACOS App Admin Panel - Manage books, users, announcements, elections, and system settings.">
        <title>Admin Panel | <?= htmlspecialchars($page_title) ?></title>

        <!-- Design Tokens Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
        <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

        <style>
            :root {
                --primary-green: #0b8f3a;
                --dark-green: #066b2a;
                --primary-yellow: #ffcc00;
                --text-dark: #111827;
                --text-secondary: #52606d;
                --bg: #f3f7fa;
                --surface: #ffffff;
                --border-color: rgba(15, 23, 42, 0.06);
                --card-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);

                --font-display: 'Plus Jakarta Sans', sans-serif;
                --font-body: 'Inter', sans-serif;
                --font-metrics: 'Outfit', sans-serif;
            }

            body {
                background-color: var(--bg);
                font-family: var(--font-body);
                color: var(--text-dark);
                margin: 0;
                display: flex;
                min-height: 100vh;
            }

            /* Sidebar Navigation Layout */
            .admin-sidebar {
                width: 280px;
                background: #ffffff;
                border-right: 1px solid var(--border-color);
                display: flex;
                flex-direction: column;
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 1000;
            }

            .sidebar-brand {
                padding: 24px;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                align-items: center;
            }

            .sidebar-brand a {
                font-family: var(--font-display);
                font-weight: 800;
                text-decoration: none;
                font-size: 1.25rem;
                letter-spacing: -0.03em;
            }

            .sidebar-menu {
                list-style: none;
                padding: 20px 16px;
                margin: 0;
                display: flex;
                flex-direction: column;
                gap: 6px;
                flex-grow: 1;
                overflow-y: auto;
            }

            .sidebar-menu-item a {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 10px;
                color: var(--text-secondary);
                text-decoration: none;
                font-weight: 600;
                font-family: var(--font-metrics);
                font-size: 0.92rem;
                transition: all 0.2s ease;
            }

            .sidebar-menu-item a:hover {
                background: #f1f5f9;
                color: var(--text-dark);
            }

            .sidebar-menu-item.active a {
                background: #f0fdf4;
                color: var(--primary-green);
            }

            .menu-badge {
                background: #ef4444;
                color: #ffffff;
                font-size: 0.72rem;
                padding: 2px 8px;
                border-radius: 30px;
                margin-left: auto;
                font-weight: 700;
            }

            /* Main Workspace Content Pane */
            .admin-workspace {
                margin-left: 280px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                min-width: 0;
            }

            /* Top Bar Panel */
            .admin-header {
                height: 72px;
                background: #ffffff;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 0 32px;
                position: sticky;
                top: 0;
                z-index: 900;
            }

            .admin-header__left {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }

            .sidebar-toggle-btn {
                display: none;
                width: 40px;
                height: 40px;
                border: 1px solid rgba(15, 23, 42, 0.08);
                background: #f8fafc;
                border-radius: 12px;
                color: var(--text-dark);
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.1rem;
                transition: all 0.2s ease;
            }

            .sidebar-toggle-btn:hover {
                background: #e2e8f0;
                color: var(--primary-green);
            }

            .admin-header__title {
                margin: 0;
                font-family: var(--font-display);
                font-size: 1.15rem;
                font-weight: 700;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .header-search {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #f1f5f9;
                padding: 8px 16px;
                border-radius: 30px;
                width: 320px;
                border: 1px solid transparent;
            }

            .header-search input {
                border: none;
                background: transparent;
                outline: none;
                font-size: 0.85rem;
                font-family: var(--font-body);
                width: 100%;
            }

            .header-controls {
                display: flex;
                align-items: center;
                gap: 12px;
                position: relative;
            }

            .header-user-chip {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #f8fafc;
                border: 1px solid rgba(15, 23, 42, 0.06);
                border-radius: 999px;
                padding: 6px 10px 6px 6px;
                position: relative;
            }

            .header-user-label {
                font-family: var(--font-metrics);
                font-size: 0.84rem;
                font-weight: 700;
                color: var(--text-dark);
                max-width: 180px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .header-avatar-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--primary-green), #16a34a);
                color: #ffffff;
                border: 2px solid #ffffff;
                cursor: pointer;
                font-weight: 800;
                font-family: var(--font-metrics);
                box-shadow: 0 6px 16px rgba(11, 143, 58, 0.16);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .header-avatar-btn:hover {
                transform: scale(1.04);
                box-shadow: 0 8px 18px rgba(11, 143, 58, 0.2);
            }

            .admin-user-dropdown {
                position: absolute;
                right: 0;
                top: calc(100% + 10px);
                min-width: 220px;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
                padding: 8px;
                transform-origin: top right;
                opacity: 0;
                transform: translateY(-8px) scale(.96);
                pointer-events: none;
                transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
                border: 1px solid rgba(15, 23, 42, 0.06);
                z-index: 1200;
            }

            .admin-user-dropdown[aria-hidden="false"],
            .admin-user-dropdown.show {
                opacity: 1;
                transform: translateY(0) scale(1);
                pointer-events: auto;
            }

            .admin-user-dropdown .user-dropdown__greet {
                font-size: 0.75rem;
                color: var(--text-secondary);
                font-family: var(--font-metrics);
                padding: 8px 12px;
                border-bottom: 1px solid rgba(15, 23, 42, 0.05);
                margin-bottom: 6px;
                display: flex;
                flex-direction: column;
            }

            .admin-user-dropdown .user-dropdown__greet strong {
                font-size: 0.88rem;
                color: var(--text-dark);
                font-weight: 700;
                margin-top: 2px;
            }

            .admin-user-dropdown .user-dropdown__item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 9px 12px;
                font-size: 0.85rem;
                font-family: var(--font-body);
                color: var(--text-dark);
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.15s ease;
            }

            .admin-user-dropdown .user-dropdown__item i {
                font-size: 1.05rem;
                color: #64748b;
                transition: color 0.15s ease;
            }

            .admin-user-dropdown .user-dropdown__item:hover,
            .admin-user-dropdown .user-dropdown__item:focus {
                background: #f1f5f9;
                color: var(--primary-green);
                outline: none;
            }

            .admin-user-dropdown .user-dropdown__item:hover i {
                color: var(--primary-green);
            }

            .admin-user-dropdown .user-dropdown__divider {
                height: 1px;
                background: rgba(15, 23, 42, 0.06);
                margin: 6px 0;
            }

            .admin-user-dropdown .user-dropdown__item--danger {
                color: #ef4444;
                font-weight: 700;
            }

            .admin-user-dropdown .user-dropdown__item--danger i {
                color: #ef4444;
            }

            .admin-user-dropdown .user-dropdown__item--danger:hover,
            .admin-user-dropdown .user-dropdown__item--danger:focus {
                background: #fef2f2;
                color: #dc2626;
            }

            /* Workspace container spacing */
            .admin-content {
                padding: 32px;
                flex-grow: 1;
            }

            .breadcrumbs {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 24px;
                font-size: 0.82rem;
                font-family: var(--font-metrics);
                color: var(--text-secondary);
            }

            .breadcrumbs a {
                color: var(--text-secondary);
                text-decoration: none;
                font-weight: 500;
            }

            .breadcrumbs a:hover {
                color: var(--primary-green);
            }

            /* Standardized Components */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 24px;
                margin-bottom: 32px;
            }

            .stat-card {
                background: #ffffff;
                border-radius: 16px;
                padding: 24px;
                box-shadow: var(--card-shadow);
                border: 1px solid var(--border-color);
                display: flex;
                align-items: center;
                gap: 18px;
            }

            .stat-icon {
                width: 52px;
                height: 52px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                flex-shrink: 0;
            }

            .stat-info h3 {
                font-family: var(--font-metrics);
                font-size: 1.75rem;
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

            .card-container {
                background: #ffffff;
                border-radius: 16px;
                padding: 24px;
                box-shadow: var(--card-shadow);
                border: 1px solid var(--border-color);
                margin-bottom: 32px;
            }

            /* Form styling */
            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                font-family: var(--font-metrics);
                font-size: 0.88rem;
            }

            .form-control {
                width: 100%;
                padding: 12px 16px;
                border-radius: 10px;
                border: 1px solid #cbd5e1;
                font-size: 0.9rem;
                outline: none;
                box-sizing: border-box;
                font-family: var(--font-body);
                transition: all 0.2s ease;
            }

            .form-control:focus {
                border-color: var(--primary-green);
                box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.1);
            }

            /* CSS Tables */
            .admin-table-wrapper {
                background: #ffffff;
                border-radius: 16px;
                border: 1px solid var(--border-color);
                overflow: hidden;
                box-shadow: var(--card-shadow);
            }

            .admin-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
            }

            .admin-table th,
            .admin-table td {
                padding: 16px 24px;
                border-bottom: 1px solid var(--border-color);
                font-size: 0.88rem;
            }

            .admin-table th {
                background: #f8fafc;
                font-weight: 700;
                font-family: var(--font-metrics);
                color: var(--text-secondary);
            }

            .admin-table tr:last-child td {
                border-bottom: none;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                border-radius: 100px;
                font-weight: 600;
                font-size: 0.88rem;
                font-family: var(--font-metrics);
                cursor: pointer;
                border: none;
                text-decoration: none;
                transition: all 0.15s ease;
            }

            .btn-primary {
                background: var(--primary-green);
                color: #ffffff;
            }

            .btn-primary:hover {
                background: var(--dark-green);
            }

            .btn-secondary {
                background: #f1f5f9;
                color: var(--text-dark);
            }

            .btn-secondary:hover {
                background: #e2e8f0;
            }

            .btn-danger {
                background: #fee2e2;
                color: #ef4444;
            }

            .btn-danger:hover {
                background: #fecaca;
            }

            @media (max-width: 900px) {
                .admin-sidebar {
                    display: none;
                    width: 280px;
                    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.2);
                }

                .admin-sidebar.is-open {
                    display: flex;
                }

                .admin-workspace {
                    margin-left: 0;
                }

                .sidebar-toggle-btn {
                    display: inline-flex;
                }

                .header-user-label {
                    display: none;
                }

                .admin-header {
                    padding: 0 18px;
                }

                .admin-content {
                    padding: 22px 18px;
                }

                .card-container {
                    padding: 20px;
                }

                /* Allow wide data tables to scroll horizontally instead of
                   breaking the mobile layout */
                .admin-table-wrapper {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }

                .admin-table {
                    min-width: 640px;
                }
            }

            @media (max-width: 620px) {
                .admin-content {
                    padding: 16px 14px;
                }

                .card-container {
                    padding: 16px;
                    border-radius: 12px;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .admin-header__title {
                    font-size: 1rem;
                }

                .admin-header {
                    height: 64px;
                    padding: 0 14px;
                }

                .stat-card {
                    padding: 18px;
                }

                .breadcrumbs {
                    flex-wrap: wrap;
                    margin-bottom: 18px;
                }

                .admin-table th,
                .admin-table td {
                    padding: 12px 14px;
                }
            }
        </style>
    </head>

    <body>

        <!-- Left Sidebar Panel Navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <a href="<?= $BASE_URL ?>" class="site-logo">
                    <span class="logo-accent" style="color: var(--text-dark);">NACOS</span><span class="logo-main" style="color: var(--primary-green);">YabaTech</span>
                </a>
            </div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/index.php"><i class="ri-dashboard-line"></i> Dashboard</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'moderation' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/moderation.php">
                        <i class="ri-checkbox-circle-line"></i> Book Moderation
                        <?php if ($pending_books_count > 0): ?>
                            <span class="menu-badge"><?= $pending_books_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'books' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/books.php"><i class="ri-book-open-line"></i> Books</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'users' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/users.php"><i class="ri-group-line"></i> Users</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'bulk_students' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/bulk_students.php"><i class="ri-file-upload-line"></i> Bulk Register</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'announcements' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/announcements.php"><i class="ri-notification-3-line"></i> Announcements</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'election' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/election.php"><i class="ri-checkbox-line"></i> Election Manager</a>
                </li>
                <li class="sidebar-menu-item <?= $active_tab === 'settings' ? 'active' : '' ?>">
                    <a href="<?= $BASE_URL ?>admin/settings.php"><i class="ri-settings-4-line"></i> Settings</a>
                </li>
                <li class="sidebar-menu-item" style="margin-top: auto;">
                    <a href="<?= $BASE_URL ?>auth/logout.php" style="color: #ef4444;"><i class="ri-logout-box-r-line"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Content Workspace -->
        <section class="admin-workspace">
            <header class="admin-header">
                <div class="admin-header__left">
                    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" type="button" aria-label="Toggle sidebar menu">
                        <i class="ri-menu-line"></i>
                    </button>
                    <h1 class="admin-header__title"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="header-controls">
                    <div class="header-user-chip">
                        <span class="header-user-label"><?= htmlspecialchars($fullname) ?></span>
                        <button id="adminUserAvatarBtn" class="header-avatar-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
                            <span><?= htmlspecialchars($initial) ?></span>
                        </button>

                        <div id="adminUserDropdown" class="admin-user-dropdown" role="menu" aria-label="Account menu" aria-hidden="true">
                            <div class="user-dropdown__greet">
                                <span>Hello,</span>
                                <strong><?= htmlspecialchars($fullname) ?></strong>
                            </div>

                            <a href="<?= $BASE_URL ?>home.php" role="menuitem" class="user-dropdown__item">
                                <i class="ri-home-4-line"></i> Home
                            </a>
                            <a href="<?= $BASE_URL ?>profile.php" role="menuitem" class="user-dropdown__item">
                                <i class="ri-user-line"></i> Profile
                            </a>
                            <a href="<?= $BASE_URL ?>election.php" role="menuitem" class="user-dropdown__item">
                                <i class="ri-check-line"></i> Election
                            </a>
                            <a href="<?= $BASE_URL ?>library/library.php" role="menuitem" class="user-dropdown__item">
                                <i class="ri-book-open-line"></i> Library
                            </a>

                            <div class="user-dropdown__divider" role="separator" aria-hidden="true"></div>

                            <a href="<?= $BASE_URL ?>auth/logout.php" role="menuitem" class="user-dropdown__item user-dropdown__item--danger">
                                <i class="ri-logout-box-r-line"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <!-- Dynamic Breadcrumbs -->
                <div class="breadcrumbs">
                    <a href="<?= $BASE_URL ?>admin/index.php">Admin Panel</a>
                    <?php foreach ($breadcrumbs as $title => $url): ?>
                        <i class="ri-arrow-right-s-line"></i>
                        <?php if ($url): ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($title) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Main Dynamic Callback Injection Area -->
                <?php $content_callback(); ?>
            </main>
        </section>

        <script>
            (function () {
                const sidebar = document.querySelector('.admin-sidebar');
                const toggle = document.getElementById('sidebarToggleBtn');
                const avatarBtn = document.getElementById('adminUserAvatarBtn');
                const dropdown = document.getElementById('adminUserDropdown');

                if (!sidebar || !toggle) {
                    return;
                }

                toggle.addEventListener('click', function () {
                    const isOpen = sidebar.classList.toggle('is-open');
                    document.body.style.overflow = isOpen ? 'hidden' : '';
                });

                document.addEventListener('click', function (event) {
                    if (window.innerWidth > 900) {
                        return;
                    }

                    const clickedInsideSidebar = sidebar.contains(event.target);
                    const clickedToggle = toggle.contains(event.target);

                    if (!clickedInsideSidebar && !clickedToggle && sidebar.classList.contains('is-open')) {
                        sidebar.classList.remove('is-open');
                        document.body.style.overflow = '';
                    }
                });

                window.addEventListener('resize', function () {
                    if (window.innerWidth > 900) {
                        sidebar.classList.remove('is-open');
                        document.body.style.overflow = '';
                    }
                });

                if (avatarBtn && dropdown) {
                    dropdown.setAttribute('aria-hidden', 'true');

                    function openDropdown() {
                        dropdown.classList.add('show');
                        dropdown.setAttribute('aria-hidden', 'false');
                        avatarBtn.setAttribute('aria-expanded', 'true');
                    }

                    function closeDropdown() {
                        dropdown.classList.remove('show');
                        dropdown.setAttribute('aria-hidden', 'true');
                        avatarBtn.setAttribute('aria-expanded', 'false');
                    }

                    avatarBtn.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        const expanded = avatarBtn.getAttribute('aria-expanded') === 'true';
                        if (expanded) {
                            closeDropdown();
                        } else {
                            openDropdown();
                        }
                    });

                    document.addEventListener('click', function (ev) {
                        if (!dropdown.contains(ev.target) && !avatarBtn.contains(ev.target)) {
                            closeDropdown();
                        }
                    });

                    document.addEventListener('keydown', function (ev) {
                        if (ev.key === 'Escape' || ev.key === 'Esc') {
                            closeDropdown();
                            avatarBtn.focus();
                        }
                    });

                    dropdown.addEventListener('focusout', function (ev) {
                        const related = ev.relatedTarget;
                        if (!related || (!dropdown.contains(related) && !avatarBtn.contains(related))) {
                            closeDropdown();
                        }
                    });
                }
            })();
        </script>

    </body>

    </html>
<?php
}
?>