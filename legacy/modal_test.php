<?php
require_once __DIR__ . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NACOS Modal Test</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7fb;
            margin: 0;
            padding: 32px;
            font-family: Inter, system-ui, sans-serif;
        }
        .test-shell {
            width: min(980px, 100%);
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
            padding: 32px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .test-shell h1 {
            margin: 0 0 10px;
            font-size: 2rem;
            color: #111827;
        }
        .test-shell p {
            margin: 0 0 24px;
            color: #52606d;
            line-height: 1.7;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .test-card {
            padding: 20px;
            border-radius: 18px;
            background: #f8fbff;
            border: 1px solid rgba(26, 115, 232, 0.16);
        }
        .test-card h2 {
            margin: 0 0 14px;
            font-size: 1.05rem;
            color: #111827;
        }
        .test-card button {
            width: 100%;
        }
        .full-width {
            grid-column: span 2;
        }
        @media (max-width: 720px) {
            .test-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="test-shell">
        <h1>Modal Test Page</h1>
        <p>Use the buttons below to open five example modal instances. Each button demonstrates a different type and behavior.</p>

        <div class="test-grid">
            <div class="test-card">
                <h2>Neutral Info</h2>
                <button type="button" class="app-modal__button app-modal__button--primary" onclick="openModalNeutral()">Open Neutral Modal</button>
            </div>

            <div class="test-card">
                <h2>Success</h2>
                <button type="button" class="app-modal__button app-modal__button--primary" onclick="openModalSuccess()">Open Success Modal</button>
            </div>

            <div class="test-card">
                <h2>Warning With Cancel</h2>
                <button type="button" class="app-modal__button app-modal__button--primary" onclick="openModalWarning()">Open Warning Modal</button>
            </div>

            <div class="test-card">
                <h2>Error</h2>
                <button type="button" class="app-modal__button app-modal__button--primary" onclick="openModalError()">Open Error Modal</button>
            </div>

            <div class="test-card full-width">
                <h2>Confirmation + Auto Close</h2>
                <button type="button" class="app-modal__button app-modal__button--primary" onclick="openModalConfirm()">Open Confirm Modal</button>
            </div>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/modal.php'; ?>

    <script>
        function openModalNeutral() {
            AppModal.open({
                type: 'neutral',
                title: 'Information',
                subtitle: 'General notice',
                message: 'This is a neutral information modal for general updates and tips.',
                primaryText: 'Got it',
                showSecondary: false
            });
        }

        function openModalSuccess() {
            AppModal.open({
                type: 'success',
                title: 'Success',
                subtitle: 'Operation completed',
                message: 'Your changes were saved successfully. Everything looks good.',
                primaryText: 'Continue',
                showSecondary: false
            });
        }

        function openModalWarning() {
            AppModal.open({
                type: 'warning',
                title: 'Warning',
                subtitle: 'Check your selection',
                message: 'This action may affect other records. Please review before proceeding.',
                primaryText: 'Proceed',
                secondaryText: 'Cancel',
                showSecondary: true,
                onConfirm: function () {
                    alert('You chose to proceed from the warning modal.');
                }
            });
        }

        function openModalError() {
            AppModal.open({
                type: 'error',
                title: 'Error',
                subtitle: 'Something went wrong',
                message: 'A problem occurred while processing your request. Please try again later.',
                primaryText: 'Retry',
                secondaryText: 'Dismiss',
                showSecondary: true,
                onConfirm: function () {
                    alert('Retry action triggered.');
                }
            });
        }

        function openModalConfirm() {
            AppModal.open({
                type: 'warning',
                title: 'Confirm Deletion',
                subtitle: 'This cannot be undone',
                message: 'If you confirm, the selected item will be permanently deleted in 5 seconds.',
                primaryText: 'Delete',
                secondaryText: 'Keep it',
                showSecondary: true,
                onConfirm: function () {
                    alert('Delete confirmed.');
                },
                onCancel: function () {
                    alert('Deletion canceled.');
                },
                autoClose: 5000
            });
        }
    </script>
</body>
</html>
