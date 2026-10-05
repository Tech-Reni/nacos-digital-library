<?php
// includes/modal.php
// Reusable Google-themed modal component: neutral (blue), success (green), warning (orange/red), error (red)
?>
<div id="appModal" class="app-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="appModalTitle" aria-describedby="appModalDescription">
	<div class="app-modal__backdrop" data-modal-close></div>
	<div class="app-modal__dialog" role="document">
		<div class="app-modal__header">
			<div class="app-modal__icon" id="appModalIcon" aria-hidden="true">
				<!-- Injected dynamically via JS depending on status -->
			</div>
			<div class="app-modal__titles">
				<h2 class="app-modal__title" id="appModalTitle"></h2>
				<p class="app-modal__subtitle" id="appModalDescription"></p>
			</div>
			<button type="button" class="app-modal__close" aria-label="Close modal" data-modal-close>
				<i class="ri-close-line"></i>
			</button>
		</div>

		<div class="app-modal__message" id="appModalMessage"></div>

		<div class="app-modal__actions">
			<button type="button" class="app-modal__button app-modal__button--secondary" data-modal-close id="appModalSecondaryBtn">Cancel</button>
			<button type="button" class="app-modal__button app-modal__button--primary" id="appModalPrimaryBtn">OK</button>
		</div>
	</div>
</div>

<style>
:root {
	--modal-bg: rgba(15, 23, 42, 0.45); /* Soft neutral scrim */
	--modal-surface: #ffffff;
	--modal-border: rgba(15, 23, 42, 0.06);
	--modal-text: #1f2937;
	--modal-subtext: #4b5563;
	
	/* Google Palette (Material Design 3 Specs) */
	--google-blue: #1a73e8;
	--google-blue-bg: #e8f0fe;
	--google-green: #137333;
	--google-green-bg: #e6f4ea;
	--google-yellow: #b06000; /* Darker tone for rich contrast & accessibility */
	--google-yellow-bg: #fef7e0;
	--google-red: #c5221f;
	--google-red-bg: #fce8e6;

	/* Dynamic Fonts mappings */
	--font-display: 'Plus Jakarta Sans', sans-serif;
	--font-body: 'Inter', sans-serif;
	--font-metrics: 'Outfit', sans-serif;
}

.app-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	display: none;
	align-items: center;
	justify-content: center;
	padding: 24px;
	z-index: 4000;
}

.app-modal--open {
	display: flex;
}

.app-modal__backdrop {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: var(--modal-bg);
	backdrop-filter: blur(4px);
	transition: opacity 0.2s ease;
}

.app-modal__dialog {
	position: relative;
	max-width: 480px;
	width: 100%;
	background: var(--modal-surface);
	border-radius: 20px;
	padding: 28px;
	box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.1), 0 20px 40px -15px rgba(15, 23, 42, 0.05);
	border: 1px solid var(--modal-border);
	z-index: 1;
	transform: scale(0.95) translateY(8px);
	opacity: 0;
	transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.app-modal--open .app-modal__dialog {
	transform: scale(1) translateY(0);
	opacity: 1;
}

.app-modal__header {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	margin-bottom: 18px;
}

.app-modal__icon {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 22px;
	flex-shrink: 0;
}

.app-modal__titles {
	flex: 1;
	padding-top: 2px;
}

.app-modal__title {
	margin: 0;
	font-family: var(--font-display);
	font-size: 1.15rem;
	font-weight: 700;
	letter-spacing: -0.01em;
	color: var(--modal-text);
}

.app-modal__subtitle {
	margin: 4px 0 0;
	font-family: var(--font-metrics);
	font-size: 0.82rem;
	font-weight: 500;
	color: var(--modal-subtext);
}

.app-modal__message {
	font-family: var(--font-body);
	font-size: 0.9rem;
	line-height: 1.6;
	color: #374151;
	margin-bottom: 24px;
	padding-left: 2px;
}

.app-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

.app-modal__button {
	padding: 10px 24px;
	border-radius: 100px; /* Modern pill-shape action triggers */
	border: 1px solid transparent;
	font-size: 0.88rem;
	font-family: var(--font-body);
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.app-modal__button--primary {
	color: #ffffff;
}

.app-modal__button--secondary {
	background: transparent;
	color: var(--modal-text);
	border-color: #dadce0;
}

.app-modal__button--secondary:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
}

.app-modal__close {
	background: transparent;
	border: none;
	color: #94a3b8;
	font-size: 1.3rem;
	padding: 4px;
	cursor: pointer;
	border-radius: 50%;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.15s ease;
}

.app-modal__close:hover {
	color: var(--modal-text);
	background: #f1f5f9;
}

/* Dynamic State Styling */
.app-modal--neutral .app-modal__icon {
	background-color: var(--google-blue-bg);
	color: var(--google-blue);
}
.app-modal--neutral .app-modal__button--primary {
	background-color: var(--google-blue);
}
.app-modal--neutral .app-modal__button--primary:hover {
	background-color: #1557b0;
}

.app-modal--success .app-modal__icon {
	background-color: var(--google-green-bg);
	color: var(--google-green);
}
.app-modal--success .app-modal__button--primary {
	background-color: var(--google-green);
}
.app-modal--success .app-modal__button--primary:hover {
	background-color: #0f6229;
}

.app-modal--warning .app-modal__icon {
	background-color: var(--google-yellow-bg);
	color: var(--google-yellow);
}
.app-modal--warning .app-modal__button--primary {
	background-color: var(--google-yellow);
}
.app-modal--warning .app-modal__button--primary:hover {
	background-color: #944f00;
}

.app-modal--error .app-modal__icon {
	background-color: var(--google-red-bg);
	color: var(--google-red);
}
.app-modal--error .app-modal__button--primary {
	background-color: var(--google-red);
}
.app-modal--error .app-modal__button--primary:hover {
	background-color: #a51d1a;
}

@media (max-width: 620px) {
	.app-modal {
		padding: 16px;
	}

	.app-modal__dialog {
		padding: 24px;
	}

	.app-modal__header {
		gap: 12px;
	}
}
</style>

<script>
(function () {
	const modal = document.getElementById('appModal');
	if (!modal) {
		return;
	}

	const titleEl = modal.querySelector('#appModalTitle');
	const iconEl = modal.querySelector('#appModalIcon');
	const descriptionEl = modal.querySelector('#appModalDescription');
	const messageEl = modal.querySelector('#appModalMessage');
	const primaryBtn = modal.querySelector('#appModalPrimaryBtn');
	const secondaryBtn = modal.querySelector('#appModalSecondaryBtn');
	const closeButtons = modal.querySelectorAll('[data-modal-close]');
	let activeCallback = null;
	let cancelCallback = null;

	function setType(type) {
		modal.classList.remove('app-modal--neutral', 'app-modal--success', 'app-modal--warning', 'app-modal--error');
		switch ((type || 'neutral').toLowerCase()) {
			case 'success':
				modal.classList.add('app-modal--success');
				iconEl.innerHTML = '<i class="ri-checkbox-circle-fill"></i>';
				break;
			case 'warning':
				modal.classList.add('app-modal--warning');
				iconEl.innerHTML = '<i class="ri-error-warning-fill"></i>';
			break;
			case 'error':
				modal.classList.add('app-modal--error');
				iconEl.innerHTML = '<i class="ri-close-circle-fill"></i>';
			break;
			default:
				modal.classList.add('app-modal--neutral');
				iconEl.innerHTML = '<i class="ri-information-fill"></i>';
		}
	}

	function closeModal(event) {
		if (event) {
			event.preventDefault();
		}
		modal.classList.remove('app-modal--open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
		if (typeof cancelCallback === 'function') {
			cancelCallback();
		}
		cancelCallback = null;
		activeCallback = null;
	}

	function onKeyDown(event) {
		if (event.key === 'Escape' && modal.classList.contains('app-modal--open')) {
			closeModal(event);
		}
	}

	function openModal(options) {
		options = options || {};
		setType(options.type || 'neutral');

		titleEl.textContent = options.title || 'Notice';
		descriptionEl.textContent = options.subtitle || '';
		messageEl.textContent = options.message || '';

		primaryBtn.textContent = options.primaryText || 'OK';
		secondaryBtn.textContent = options.secondaryText || 'Cancel';

		if (options.showSecondary === false) {
			secondaryBtn.style.display = 'none';
		} else {
			secondaryBtn.style.display = 'inline-flex';
		}

		activeCallback = typeof options.onConfirm === 'function' ? options.onConfirm : null;
		cancelCallback = typeof options.onCancel === 'function' ? options.onCancel : null;

		modal.classList.add('app-modal--open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		primaryBtn.focus();

		if (options.autoClose && typeof options.autoClose === 'number') {
			setTimeout(closeModal, options.autoClose);
		}
	}

	primaryBtn.addEventListener('click', function (event) {
		event.preventDefault();
		if (typeof activeCallback === 'function') {
			activeCallback();
		}
		closeModal();
	});

	closeButtons.forEach(function (button) {
		button.addEventListener('click', closeModal);
	});

	modal.addEventListener('click', function (event) {
		if (event.target === modal.querySelector('.app-modal__backdrop')) {
			closeModal(event);
		}
	});

	document.addEventListener('keydown', onKeyDown);

	window.AppModal = {
		open: openModal,
		close: closeModal,
	};
})();
</script>