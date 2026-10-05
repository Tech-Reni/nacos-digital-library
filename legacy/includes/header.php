<?php
// includes/header.php
// Primary sticky header with user avatar and accessible dropdown
// Assumes session is already started and $_SESSION['fullname'] may be available

require_once __DIR__ . '/../includes/db.php';


$fullname = isset($_SESSION['fullname']) ? trim((string) $_SESSION['fullname']) : '';
$initial = 'U';
if ($fullname !== '') {
	// Use multibyte-safe substring and uppercase
	$initial = mb_strtoupper(mb_substr($fullname, 0, 1), 'UTF-8');
}
$escaped_fullname = $fullname ? htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8') : 'User';

// Get current page for meta description
$current_page = basename($_SERVER['PHP_SELF']);
$page_descriptions = [
	'index.php' => 'NACOS App - Access student services, academic resources, books, and campus updates for Yaba College of Technology students.',
	'home.php' => 'NACOS App - Your hub for student services, academic resources, books, and campus updates.',
	'library.php' => 'Browse and search the NACOS App collection of academic books and resources.',
	'upload.php' => 'Upload and share academic resources with your department.',
	'reader.php' => 'Read and study from our collection of digital books and academic materials.',
	'bookmarks.php' => 'Your saved books and bookmarked resources for quick access.',
	'profile.php' => 'Manage your NACOS student profile, update your academic details, and keep your account information current.',
];
$meta_description = $page_descriptions[$current_page] ?? 'NACOS App - Empowering students with useful academic resources and campus services.';
?>
<!-- Meta Description -->
<meta name="description" content="<?= htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8') ?>">
<!-- Favicon -->
<link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">

<header class="site-header" aria-label="Primary">
	<div class="site-header__inner">
		<!-- Left: App title / logo text -->
		<div class="site-header__left">
			<a href="<?= $GLOBALS['BASE_URL'] ?>" class="site-logo" aria-label="NACOS App home">
				<span class="logo-accent">NACOS</span><span class="logo-main">YabaTech</span>
			</a>
		</div>

		<!-- Center: Search bar -->
		<div class="site-header__center" id="siteHeaderCenter">
			<a href="<?= $GLOBALS['BASE_URL'] ?>library/library.php" class="search-placeholder" role="search" aria-label="Search books, authors or subjects">
				<i class="ri-search-2-line"></i>
				<span>Search books, authors or subjects...</span>
			</a>
		</div>

		<!-- Right: avatar and dropdown trigger -->
		<div class="site-header__right">
			<button id="userAvatarBtn" class="avatar-btn" type="button"
				aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
				<span class="avatar-initial" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
			</button>

			<!-- Dropdown menu (hidden by default) -->
			<div id="userDropdown" class="user-dropdown" role="menu" aria-label="Account menu">
				<div class="user-dropdown__greet">
					<span>Hello,</span>
					<strong><?= $escaped_fullname ?></strong>
				</div>

				<a href="<?= $GLOBALS['BASE_URL'] ?>profile.php" role="menuitem" class="user-dropdown__item">
					<i class="ri-user-line"></i> My Profile
				</a>
				<a href="<?= $GLOBALS['BASE_URL'] ?>bookmarks/bookmarks.php" role="menuitem" class="user-dropdown__item">
					<i class="ri-bookmark-line"></i> Saved Books
				</a>
				<a href="<?= $GLOBALS['BASE_URL'] ?>election.php" role="menuitem" class="user-dropdown__item">
					<i class="ri-check-line"></i> Election
				</a>
				<a href="<?= $GLOBALS['BASE_URL'] ?>library/library.php" role="menuitem" class="user-dropdown__item">
					<i class="ri-book-open-line"></i> Library
				</a>
				<a href="<?= $GLOBALS['BASE_URL'] ?>auth/mfa_setup.php" role="menuitem" class="user-dropdown__item">
					<i class="ri-settings-4-line"></i> Account Settings
				</a>

				<!-- Dynamic Role Check: Renders Admin Panel Link for Administrators -->
				<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
					<a href="<?= $GLOBALS['BASE_URL'] ?>admin/index.php" role="menuitem" class="user-dropdown__item">
						<i class="ri-shield-user-line" style="color: var(--primary-green);"></i> Admin Panel
					</a>
				<?php endif; ?>

				<div class="user-dropdown__divider" role="separator" aria-hidden="true"></div>

				<a href="<?= $GLOBALS['BASE_URL'] ?>auth/logout.php" role="menuitem" class="user-dropdown__item user-dropdown__item--danger">
					<i class="ri-logout-box-r-line"></i> Logout
				</a>
			</div>
		</div>
	</div>

	<style>
		/* Header layout */
		.site-header {
			position: sticky;
			top: 0;
			z-index: 1200;
			background: var(--surface, #ffffff);
			border-bottom: 1px solid rgba(15, 23, 42, 0.06);
			box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
		}

		.site-header__inner {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 24px;
			padding: 12px 24px;
			max-width: 1600px;
			margin: 0 auto;
		}

		/* Left Area Logo Custom Theme */
		.site-header__left {
			display: flex;
			align-items: center;
		}

		.site-logo {
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-weight: 800;
			text-decoration: none;
			font-size: 1.2rem;
			letter-spacing: -0.03em;
			display: flex;
			align-items: center;
			gap: 2px;
		}

		.logo-accent {
			color: var(--text-dark, #111827);
		}

		.logo-main {
			color: var(--primary-green, #0b8f3a);
			font-weight: 500;
		}

		/* Center Area Page / Search Context */
		.site-header__center {
			flex: 1 1 320px;
			text-align: center;
			display: flex;
			justify-content: center;
		}

		.page-title {
			margin: 0;
			font-size: 0.95rem;
			color: var(--text-dark, #111827);
			font-weight: 600;
			font-family: 'Inter', sans-serif;
		}

		.search-placeholder {
			color: var(--text-secondary, #52606d);
			font-size: 0.85rem;
			font-family: 'Inter', sans-serif;
			padding: 8px 16px;
			border-radius: 30px;
			background: #f1f5f9;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			border: 1px solid rgba(15, 23, 42, 0.04);
			cursor: pointer;
			width: 100%;
			max-width: 320px;
			transition: all 0.2s ease;
		}

		.search-placeholder:hover {
			background: #e2e8f0;
			border-color: rgba(15, 23, 42, 0.08);
		}

		.search-placeholder i {
			font-size: 1rem;
			color: var(--text-secondary, #52606d);
		}

		/* Right Avatar Trigger */
		.site-header__right {
			position: relative;
			display: flex;
			align-items: center;
		}

		.avatar-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 38px;
			height: 38px;
			border-radius: 50%;
			background: var(--primary-green, #0b8f3a);
			color: #ffffff;
			border: none;
			cursor: pointer;
			font-weight: 700;
			font-family: 'Outfit', sans-serif;
			font-size: 0.95rem;
			transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
			box-shadow: 0 2px 8px rgba(11, 143, 58, 0.15);
		}

		.avatar-btn:hover {
			transform: scale(1.04);
			background: var(--dark-green, #066b2a);
		}

		.avatar-btn:focus {
			outline: none;
			box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.25);
		}

		.theme-toggle-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 38px;
			height: 38px;
			border-radius: 50%;
			border: 1px solid rgba(15, 23, 42, 0.08);
			background: var(--surface);
			color: var(--text-secondary);
			cursor: pointer;
			transition: all 0.2s ease;
			margin-right: 12px;
		}

		.theme-toggle-btn:hover {
			background: rgba(15, 23, 42, 0.06);
			color: var(--text-dark);
		}

		.theme-toggle-btn i {
			font-size: 1.1rem;
		}

		.user-dropdown {
			position: absolute;
			right: 0;
			top: calc(100% + 10px);
			min-width: 240px;
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
		}

		.user-dropdown[aria-hidden="false"],
		.user-dropdown.show {
			opacity: 1;
			transform: translateY(0) scale(1);
			pointer-events: auto;
		}

		.user-dropdown__greet {
			font-size: 0.75rem;
			color: var(--text-secondary, #52606d);
			font-family: 'Outfit', sans-serif;
			padding: 8px 12px;
			border-bottom: 1px solid rgba(15, 23, 42, 0.05);
			margin-bottom: 6px;
			display: flex;
			flex-direction: column;
		}

		.user-dropdown__greet strong {
			font-size: 0.88rem;
			color: var(--text-dark, #111827);
			font-weight: 600;
			margin-top: 2px;
		}

		.user-dropdown__item {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 9px 12px;
			font-size: 0.85rem;
			font-family: 'Inter', sans-serif;
			color: var(--text-dark, #111827);
			text-decoration: none;
			border-radius: 8px;
			transition: all 0.15s ease;
		}

		.user-dropdown__item i {
			font-size: 1.05rem;
			color: #64748b;
			transition: color 0.15s ease;
		}

		.user-dropdown__item:focus,
		.user-dropdown__item:hover {
			background: #f1f5f9;
			color: var(--primary-green, #0b8f3a);
			outline: none;
		}

		.user-dropdown__item:hover i {
			color: var(--primary-green, #0b8f3a);
		}

		.user-dropdown__item--danger {
			color: #ef4444;
			font-weight: 600;
		}

		.user-dropdown__item--danger i {
			color: #ef4444;
		}

		.user-dropdown__item--danger:focus,
		.user-dropdown__item--danger:hover {
			background: #fef2f2;
			color: #dc2626;
		}

		.user-dropdown__item--danger:hover i {
			color: #dc2626;
		}

		.user-dropdown__divider {
			height: 1px;
			background: rgba(15, 23, 42, 0.06);
			margin: 6px 0;
		}

		/* Responsive Adaptation */
		@media (max-width: 700px) {
			.site-header__inner {
				padding: 10px 16px;
				gap: 12px;
			}

			.site-header__center {
				display: none;
			}

			.user-dropdown {
				right: 4px;
				min-width: 210px;
			}
		}

		@media (max-width: 400px) {
			.site-logo {
				font-size: 1.05rem;
			}

			.avatar-btn {
				width: 34px;
				height: 34px;
				font-size: 0.85rem;
			}
		}

		/* Responsive Adaptation */
		@media (max-width: 700px) {
			.site-header__inner {
				padding: 10px 16px;
			}

			.site-header__center {
				display: none;
			}

			.user-dropdown {
				right: 4px;
				min-width: 210px;
			}
		}
	</style>

	<script>
		// Client-side drop-down menu control logic maintaining structural accessibility
		(function() {
			var avatarBtn = document.getElementById('userAvatarBtn');
			var dropdown = document.getElementById('userDropdown');
			if (!avatarBtn || !dropdown) return;

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

			avatarBtn.addEventListener('click', function(ev) {
				ev.stopPropagation();
				var expanded = avatarBtn.getAttribute('aria-expanded') === 'true';
				if (expanded) closeDropdown();
				else openDropdown();
			});

			document.addEventListener('click', function(ev) {
				if (!dropdown.contains(ev.target) && !avatarBtn.contains(ev.target)) {
					closeDropdown();
				}
			});

			document.addEventListener('keydown', function(ev) {
				if (ev.key === 'Escape' || ev.key === 'Esc') {
					closeDropdown();
					avatarBtn.focus();
				}
			});

			dropdown.addEventListener('focusout', function(ev) {
				var related = ev.relatedTarget;
				if (!related || (!dropdown.contains(related) && !avatarBtn.contains(related))) {
					closeDropdown();
				}
			});

			var themeToggleBtn = document.getElementById('themeToggleBtn');
			var themeStorageKey = 'nacosTheme';

			function updateThemeButton(theme) {
				if (!themeToggleBtn) return;
				themeToggleBtn.innerHTML = theme === 'dark' ? '<i class="ri-sun-line"></i>' : '<i class="ri-moon-line"></i>';
				themeToggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
			}

			function setAppTheme(theme) {
				document.documentElement.dataset.theme = theme;
				document.documentElement.classList.toggle('dark', theme === 'dark');
				updateThemeButton(theme);
				try {
					localStorage.setItem(themeStorageKey, theme);
				} catch (e) {
					// ignore private mode localStorage failures
				}
			}

			function loadAppTheme() {
				var storedTheme = null;
				try {
					storedTheme = localStorage.getItem(themeStorageKey);
				} catch (e) {
					storedTheme = null;
				}
				var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
				setAppTheme(storedTheme || (prefersDark ? 'dark' : 'light'));
			}

			if (themeToggleBtn) {
				loadAppTheme();
				themeToggleBtn.addEventListener('click', function() {
					var currentTheme = document.documentElement.dataset.theme || 'light';
					setAppTheme(currentTheme === 'dark' ? 'light' : 'dark');
				});
			}
		})();
	</script>
</header>