<?php
// includes/footer.php
// Minimal academic footer; no session assumptions required
$currentYear = date('Y');
?>
<footer class="site-footer" role="contentinfo">
	<div class="site-footer__inner">
		<p class="site-footer__text">© <?= $currentYear ?> NACOS App — <span class="muted">Built for student access</span></p>
	</div>

	<style>
		.site-footer {
			border-top: 1px solid var(--border-gray);
			background: transparent;
			padding: 12px 18px;
			color: var(--text-secondary);
			font-size: 13px;
			position: static;
			width: 100%;
		}

		.site-footer__inner {
			max-width: 1200px;
			margin: 0 auto;
			text-align: center;
		}

		.site-footer__text {
			margin: 0;
			color: var(--text-secondary);
			font-weight: 500;
		}

		.site-footer__text .muted {
			color: rgba(34, 34, 34, 0.6);
			font-weight: 400;
			margin-left: 6px;
		}

		@media (max-width: 700px) {
			.site-footer { padding: 14px 12px; font-size: 12px; }
		}
	</style>

	<?php include_once __DIR__ . '/modal.php'; ?>

	<?php /* PWA "Add to Home Screen" install prompt — authenticated pages only */ ?>
	<?php include_once __DIR__ . '/pwa_install.php'; ?>
</footer>
