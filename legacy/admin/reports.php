<?php

/**
 * admin/reports.php
 * Master Admin Panel Analytics Dashboard & Live CSS Reports Engine
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

function render_reports_content()
{
    global $conn;

    // Retrieve database metrics dynamically
    $cs_count = $conn->query("SELECT COUNT(*) FROM books WHERE department = 'computer-science'")->fetch_row()[0];
    $mc_count = $conn->query("SELECT COUNT(*) FROM books WHERE department = 'mass-communication'")->fetch_row()[0];
    $ac_count = $conn->query("SELECT COUNT(*) FROM books WHERE department = 'accountancy'")->fetch_row()[0];
    $total_books = $cs_count + $mc_count + $ac_count;

    // Retrieve dynamic monthly history counters
    $monthly_stmt = $conn->query("SELECT MONTHNAME(created_at) as month, COUNT(*) as count 
                                  FROM books 
                                  GROUP BY MONTH(created_at) 
                                  ORDER BY MONTH(created_at) ASC LIMIT 6");
    $months = [];
    while ($m = $monthly_stmt->fetch_assoc()) {
        $months[] = $m;
    }
?>
    <!-- Statistics overview row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">

        <!-- Upload breakdown across departments -->
        <div class="card-container" style="margin-bottom: 0;">
            <h3 style="font-family: var(--font-display); font-size: 1.02rem; font-weight: 700; margin: 0 0 20px;"><i class="ri-pie-chart-line"></i> Category Catalog Standing</h3>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php
                $depts = [
                    ['title' => 'Computer Science', 'count' => $cs_count, 'color' => 'var(--primary-green)'],
                    ['title' => 'Mass Communication', 'count' => $mc_count, 'color' => '#3b82f6'],
                    ['title' => 'Accountancy', 'count' => $ac_count, 'color' => '#ca8a04']
                ];
                foreach ($depts as $d):
                    $pct = ($total_books > 0) ? round(($d['count'] / $total_books) * 100) : 0;
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 600; margin-bottom: 4px;">
                            <span><?= htmlspecialchars($d['title']) ?> (<?= $d['count'] ?>)</span>
                            <span><?= $pct ?>%</span>
                        </div>
                        <div style="height: 10px; background: #f1f5f9; border-radius: 30px; overflow: hidden;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $d['color'] ?>; border-radius: 30px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Monthly Upload Statistics Chart representation -->
        <div class="card-container" style="margin-bottom: 0;">
            <h3 style="font-family: var(--font-display); font-size: 1.02rem; font-weight: 700; margin: 0 0 20px;"><i class="ri-bar-chart-box-line"></i> Monthly Submissions Overview</h3>
            <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 160px; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                <?php
                $max_count = (!empty($months)) ? max(array_column($months, 'count')) : 1;
                foreach ($months as $m):
                    $h_pct = round(($m['count'] / $max_count) * 100);
                ?>
                    <div style="display: flex; flex-direction: column; align-items: center; width: calc(100% / <?= count($months) ?> - 10px);">
                        <div style="width: 100%; height: <?= $h_pct ?>%; background: var(--primary-green); border-radius: 6px 6px 0 0; transition: height 0.5s ease; min-height: 4px;" title="<?= $m['count'] ?> uploads"></div>
                        <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); margin-top: 8px; text-transform: uppercase;"><?= substr($m['month'], 0, 3) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($months)): ?>
                    <p style="font-size: 0.82rem; color: var(--text-secondary); text-align: center; width: 100%;">No metadata history recorded.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
<?php
}

render_admin_layout('render_reports_content', 'reports', 'System Analytics Reports', ['Reports' => '']);
?>