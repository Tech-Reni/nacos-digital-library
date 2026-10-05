<?php
$BASE_URL = $GLOBALS['BASE_URL'] ?? '/';

/**
 * includes/pwa_install.php
 * ---------------------------------------------------------------------------
 * PWA "Add to Home Screen" install prompt in an iOS 26 "Liquid Glass"
 * glassmorphism style. Also registers the app manifest + service worker (sw.js).
 *
 * It is mounted from includes/footer.php, which is only included on
 * authenticated pages — so this prompt NEVER appears on login/signup pages.
 *
 * Behaviour:
 *   - Android / desktop Chrome : uses the native beforeinstallprompt event to
 *     trigger a real "Install App" install.
 *   - iPhone / iPad            : Safari blocks automatic installs, so it shows
 *     step-by-step "Share -> Add to Home Screen" instructions instead.
 *   - Reinstall detection      : Chrome only fires beforeinstallprompt when the
 *     PWA is NOT installed, so after a user uninstalls and revisits, the prompt
 *     is re-armed automatically. iOS prompts again on a daily basis.
 * ---------------------------------------------------------------------------
 */
?>
<div class="pwa-install" id="pwaInstall" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pwaInstallTitle">
	<div class="pwa-install__backdrop" data-pwa-close></div>

	<section class="pwa-sheet" role="document">
		<!-- Liquid-glass specular sheen (top highlight) -->
		<i class="pwa-sheet__sheen" aria-hidden="true"></i>

		<div class="pwa-sheet__body">
			<div class="pwa-sheet__icon" aria-hidden="true">
				<img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS App logo">
			</div>

			<div class="pwa-sheet__text">
				<h2 id="pwaInstallTitle" class="pwa-sheet__title">Install the NACOS App</h2>
				<p class="pwa-sheet__subtitle" data-pwa-subtitle></p>
			</div>

			<button type="button" class="pwa-sheet__close" data-pwa-close aria-label="Close install prompt">
				<i class="ri-close-line"></i>
			</button>
		</div>

		<!-- iPhone-only instructions: Share -> Add to Home Screen -->
		<ol class="pwa-steps" data-pwa-steps hidden>
<style>
	/* ================= PWA INSTALL PROMPT — Liquid Glass ================= */
	.pwa-install {
		position: fixed;
		inset: 0;
		z-index: 5200;
		display: flex;
		align-items: flex-end;
		justify-content: center;
		padding: max(16px, env(safe-area-inset-bottom)) 18px calc(18px + env(safe-area-inset-bottom));
		visibility: hidden;
		opacity: 0;
		transition: opacity .35s ease, visibility .35s ease;
		pointer-events: none;
	}
	.pwa-install--open {
		visibility: visible;
		opacity: 1;
		pointer-events: auto;
	}

	.pwa-install__backdrop {
		position: absolute;
		inset: 0;
		background: rgba(15, 23, 42, 0.28);
		backdrop-filter: blur(6px) saturate(120%);
		-webkit-backdrop-filter: blur(6px) saturate(120%);
	}

	/* The frosted "Liquid Glass" card */
	.pwa-sheet {
		position: relative;
		width: 100%;
		max-width: 460px;
		border-radius: 28px;
		padding: 22px 22px 20px;
		color: #10231a;
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.55));
		backdrop-filter: blur(30px) saturate(180%);
		-webkit-backdrop-filter: blur(30px) saturate(180%);
		border: 1px solid rgba(255, 255, 255, 0.65);
		box-shadow:
			0 26px 70px -22px rgba(9, 56, 30, 0.45),
			inset 0 1px 0 rgba(255, 255, 255, 0.75),
			inset 0 -12px 28px -18px rgba(22, 80, 48, 0.25);
		transform: translateY(26px) scale(0.98);
		opacity: 0;
		transition: transform .4s cubic-bezier(0.16, 1, 0.3, 1), opacity .3s ease;
		overflow: hidden;
	}
	.pwa-install--open .pwa-sheet {
		transform: translateY(0) scale(1);
		opacity: 1;
	}

	/* Specular top sheen — the signature Liquid-Glass glow */
	.pwa-sheet__sheen {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 130px;
		pointer-events: none;
		background:
			linear-gradient(180deg, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)),
			radial-gradient(140% 90% at 18% 0%, rgba(150, 255, 200, 0.35), transparent 55%);
	}

	.pwa-sheet__body {
		position: relative;
		display: flex;
		align-items: center;
		gap: 14px;
	}

	.pwa-sheet__icon {
		width: 58px;
		height: 58px;
		flex-shrink: 0;
		border-radius: 16px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.55);
		box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 10px 22px -10px rgba(11, 143, 58, 0.45);
		border: 1px solid rgba(255, 255, 255, 0.6);
		overflow: hidden;
	}
	.pwa-sheet__icon img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.pwa-sheet__text { flex: 1; min-width: 0; }
	.pwa-sheet__title {
		margin: 0 0 4px;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.12rem;
		font-weight: 800;
		letter-spacing: -0.02em;
	}
	.pwa-sheet__subtitle {
		margin: 0;
		font-family: 'Inter', sans-serif;
		font-size: 0.84rem;
		line-height: 1.5;
		opacity: 0.78;
	}

	.pwa-sheet__close {
		flex-shrink: 0;
		width: 34px;
		height: 34px;
		border: none;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.45);
		color: #2b3a33;
		font-size: 1.15rem;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: background .15s ease;
	}
	.pwa-sheet__close:hover { background: rgba(255, 255, 255, 0.85); }

	/* iPhone step list */
	.pwa-steps {
		list-style: none;
		margin: 18px 0 0;
		padding: 0;
		display: grid;
		gap: 10px;
	}
	.pwa-steps li {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 11px 13px;
		border-radius: 15px;
		background: rgba(255, 255, 255, 0.42);
		border: 1px solid rgba(255, 255, 255, 0.55);
		font-size: 0.85rem;
		font-family: 'Inter', sans-serif;
	}
	.pwa-steps li i {
		font-size: 1.05rem;
		color: #0b8f3a;
		flex-shrink: 0;
		width: 24px;
		height: 24px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(11, 143, 58, 0.12);
		border-radius: 8px;
	}

	.pwa-sheet__actions {
		display: flex;
		justify-content: flex-end;
		gap: 10px;
		margin-top: 18px;
	}
	.pwa-btn {
		border: none;
		cursor: pointer;
		border-radius: 100px;
		padding: 12px 22px;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 0.88rem;
		font-weight: 700;
		transition: transform .12s ease, background .15s ease;
	}
	.pwa-btn:active { transform: scale(0.97); }
	.pwa-btn--ghost {
		background: rgba(120, 140, 132, 0.14);
		color: #2b3a33;
	}
	.pwa-btn--ghost:hover { background: rgba(120, 140, 132, 0.24); }
	.pwa-btn--primary {
		background: linear-gradient(135deg, #0b8f3a, #066b2a);
		color: #ffffff;
		box-shadow: 0 10px 22px -8px rgba(11, 143, 58, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.35);
	}

	/* Dark theme glass */
	html.dark .pwa-sheet {
		color: #eaf4ef;
		background: linear-gradient(180deg, rgba(30, 44, 38, 0.62), rgba(22, 32, 28, 0.5));
		border-color: rgba(180, 220, 200, 0.22);
		box-shadow:
			0 26px 70px -22px rgba(0, 0, 0, 0.7),
			inset 0 1px 0 rgba(255, 255, 255, 0.14),
			inset 0 -12px 28px -18px rgba(0, 0, 0, 0.5);
	}
	html.dark .pwa-sheet__subtitle { opacity: 0.82; }
	html.dark .pwa-sheet__close { color: #d7efe3; background: rgba(255, 255, 255, 0.1); }
	html.dark .pwa-steps li { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.12); }
	html.dark .pwa-btn--ghost { background: rgba(255, 255, 255, 0.1); color: #eaf4ef; }
	html.dark .pwa-btn--ghost:hover { background: rgba(255, 255, 255, 0.18); }

	@media (max-width: 600px) {
		.pwa-sheet { padding: 20px 18px 17px; }
		.pwa-sheet__title { font-size: 1.05rem; }
	}
</style>
			<li><i class="ri-share-line"></i><span>Tap the <strong>Share</strong> button in Safari.</span></li>
			<li><i class="ri-add-line"></i><span>Scroll down and tap <strong>“Add to Home Screen”</strong>.</span></li>
			<li><i class="ri-check-line"></i><span>Tap <strong>Add</strong> — the app is now on your Home Screen.</span></li>
		</ol>

		<div class="pwa-sheet__actions">
			<button type="button" class="pwa-btn pwa-btn--ghost" data-pwa-close>Not Now</button>
			<button type="button" class="pwa-btn pwa-btn--primary" data-pwa-primary hidden>Install App</button>
		</div>
	</section>
</div>
<script>
(function () {
	var root = document.getElementById('pwaInstall');
	if (!root) return;

	var closeBtns = root.querySelectorAll('[data-pwa-close]');
	var primaryBtn = root.querySelector('[data-pwa-primary]');
	var subtitleEl = root.querySelector('[data-pwa-subtitle]');
	var stepsEl = root.querySelector('[data-pwa-steps]');

	var deferredPrompt = null;
	var currentMode = null; /* 'android' | 'ios' */

	var KEY_INSTALLED = 'nacos_pwa_installed';
	var KEY_DISMISS = 'nacos_pwa_dismiss_until';

	function store(key, value) { try { localStorage.setItem(key, value); } catch (e) {} }
	function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

	function isIos() {
		return (/iPad|iPhone|iPod/.test(navigator.userAgent)) && !window.MSStream;
	}
	function isStandalone() {
		return ((window.matchMedia && (window.matchMedia('(display-mode: standalone)').matches ||
			window.matchMedia('(display-mode: minimal-ui)').matches)) || !!window.navigator.standalone);
	}
	function canShow() {
		var until = parseInt(read(KEY_DISMISS) || '0', 10);
		return !until || Date.now() >= until;
	}

	/* Register the Service Worker (PWA install + offline support). */
	if ('serviceWorker' in navigator) {
		window.addEventListener('load', function () {
			navigator.serviceWorker.register('<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/sw.js', ENT_QUOTES, 'UTF-8') ?>').catch(function () {});
		});
	}

	/* Running as an installed app already? Nothing to show. */
	if (isStandalone()) {
		store(KEY_INSTALLED, '1');
		return;
	}

	function open(strings) {
		subtitleEl.textContent = strings.subtitle;
		primaryBtn.textContent = strings.primary;
		primaryBtn.hidden = strings.hidePrimary ? true : false;
		stepsEl.hidden = !strings.showSteps;
		currentMode = strings.mode;
		root.classList.add('pwa-install--open');
		root.setAttribute('aria-hidden', 'false');
	}
	function close() {
		root.classList.remove('pwa-install--open');
		root.setAttribute('aria-hidden', 'true');
		store(KEY_DISMISS, String(Date.now() + 86400000)); /* remind again in 24h */
	}

	function showAndroid() {
		open({
			mode: 'android',
			primary: 'Install App',
			hidePrimary: false,
			showSteps: false,
			subtitle: 'Install NACOS App on your device for faster access to student services, resources and campus updates.'
		});
	}

	function showIos() {
		open({
			mode: 'ios',
			primary: 'Got it',
			hidePrimary: false,
			showSteps: true,
			subtitle: 'Safari can’t install apps automatically — add NACOS App to your Home Screen in a few taps.'
		});
	}

	/* --- Controls --- */
	closeBtns.forEach(function (btn) { btn.addEventListener('click', close); });
	root.addEventListener('click', function (ev) {
		if (ev.target === root.querySelector('.pwa-install__backdrop')) close();
	});
	document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });

	primaryBtn.addEventListener('click', function () {
		if (currentMode === 'android' && deferredPrompt) {
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then(function (choice) {
				if (choice.outcome === 'accepted') {
					store(KEY_INSTALLED, '1');
					store(KEY_DISMISS, '0'); /* immediately re-arm for future reinstall */
				}
				deferredPrompt = null;
			});
		}
		close();
	});

	/* --- Android / desktop Chrome: native install prompt ---
	 * beforeinstallprompt ONLY fires when the PWA is not installed, so after a
	 * user uninstalls and revisits, this handler (and thus the popup) appears
	 * again automatically. */
	window.addEventListener('beforeinstallprompt', function (ev) {
		ev.preventDefault();
		deferredPrompt = ev;
		store(KEY_INSTALLED, '0');
		if (canShow()) showAndroid();
	});

	window.addEventListener('appinstalled', function () {
		store(KEY_INSTALLED, '1');
		store(KEY_DISMISS, '0');
		if (root.classList.contains('pwa-install--open')) close();
	});

	/* --- iPhone / iPad: share-to-Home-Screen instructions --- */
	if (isIos() && !isStandalone() && canShow()) {
		setTimeout(showIos, 1200);
	}

	/* Small programmatic hook for testing / retriggering. */
	window.NacosPWA = window.NacosPWA || {
		showPrompt: function () { if (isIos() || isStandalone()) showIos(); else if (deferredPrompt) showAndroid(); },
		close: close
	};
})();
</script>