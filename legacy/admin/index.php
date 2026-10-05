<?php
/**
 * admin/index.php
 * Master Admin Panel Dashboard Home
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

// Secure routing
check_role(['admin']);

// Self-healing database check: Ensure 'status' column exists in users table
try {
    $conn->query("SELECT status FROM users LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
}

function render_dashboard_content() {
    global $conn, $BASE_URL;

    // Fetch dashboard statistics safely
    $pending_books = $conn->query("SELECT COUNT(*) FROM books WHERE status = 'pending'")->fetch_row()[0];
    $approved_books = $conn->query("SELECT COUNT(*) FROM books WHERE status = 'approved'")->fetch_row()[0];
    $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $uploaded_today = $conn->query("SELECT COUNT(*) FROM books WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];

    // Safe query: removed row_id column to prevent SQL engine crashes
    $audit_trail = $conn->query("SELECT action, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5");
    $recent_logins = $conn->query("SELECT fullname, matric_number, last_login FROM users ORDER BY last_login DESC LIMIT 5");
    $recent_uploads = $conn->query("SELECT b.title, b.author, u.fullname as uploader, b.created_at 
                                    FROM books b 
                                    JOIN users u ON b.uploader_id = u.id 
                                    ORDER BY b.created_at DESC LIMIT 5");
?>
    <!-- Metric Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="ri-time-line"></i></div>
            <div class="stat-info">
                <h3><?= $pending_books ?></h3>
                <p>Pending Moderations</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #15803d;"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-info">
                <h3><?= $approved_books ?></h3>
                <p>Approved Books</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #1d4ed8;"><i class="ri-group-line"></i></div>
            <div class="stat-info">
                <h3><?= $total_users ?></h3>
                <p>Registered Users</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f3e8ff; color: #6b21a8;"><i class="ri-upload-cloud-line"></i></div>
            <div class="stat-info">
                <h3><?= $uploaded_today ?></h3>
                <p>Uploaded Today</p>
            </div>
        </div>
    </div>

    <!-- Multi-Column Layout -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
        
        <!-- Live Audit Timeline -->
        <div class="card-container" style="margin-bottom: 0;">
            <h2 style="font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; margin: 0 0 20px;"><i class="ri-history-line"></i> Live Audit Trail</h2>
            <div class="activity-list">
                <?php while ($log = $audit_trail->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-dot" style="background: var(--primary-green);"></div>
                        <div class="activity-content">
                            <p style="text-transform: capitalize; margin: 0; font-size: 0.85rem; font-weight: 600;">
                                <?= safe_output(str_replace('_', ' ', $log['action'])) ?>
                            </p>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php if ($audit_trail->num_rows === 0): ?>
                    <p style="font-size: 0.88rem; color: var(--text-secondary);">No dynamic system logs found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Uploads Pane -->
        <div class="card-container" style="margin-bottom: 0;">
            <h2 style="font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; margin: 0 0 20px;"><i class="ri-file-upload-line"></i> Recent Upload Submissions</h2>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php while ($upload = $recent_uploads->fetch_assoc()): ?>
                    <div style="display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <div style="background: #eff6ff; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2563eb; font-size: 1.25rem;">
                            <i class="ri-file-pdf-line"></i>
                        </div>
                        <div style="flex-grow: 1; min-width: 0;">
                            <h4 style="margin: 0; font-size: 0.85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= safe_output($upload['title']) ?></h4>
                            <p style="margin: 2px 0 0; font-size: 0.78rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">By <?= safe_output($upload['author']) ?> • Uploaded by <?= safe_output($upload['uploader']) ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php if ($recent_uploads->num_rows === 0): ?>
                    <p style="font-size: 0.88rem; color: var(--text-secondary);">No document submissions logged.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
<?php
}

render_admin_layout('render_dashboard_content', 'dashboard', 'Dashboard Home', []);
?>