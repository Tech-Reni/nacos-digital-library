<?php

/**
 * admin/election.php
 * Master Admin Election Manager with Live Countdown Controls & Draft Launch Console
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

$errors = [];
$success = "";

// Self-healing database tables creation
$conn->query("CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    end_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS election_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    title VARCHAR(255) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS election_candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    matric_number VARCHAR(50) NOT NULL,
    manifesto TEXT,
    votes_count INT DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS election_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    position_id INT NOT NULL,
    candidate_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_position (user_id, position_id)
)");

// Dynamic self-healing: Ensure end_at exists
try {
    $conn->query("SELECT end_at FROM elections LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE elections ADD COLUMN end_at DATETIME NULL");
}

// Fetch current election details
$election_res = $conn->query("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
$election = $election_res->fetch_assoc();

// Auto-create a Draft Campaign if empty (Prevents blank screen traps)
if (!$election) {
    $conn->query("INSERT INTO elections (title, status, end_at) VALUES ('NACOS Executive Council Election', 'draft', NULL)");
    $election_res = $conn->query("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
    $election = $election_res->fetch_assoc();
}

// Auto-close election if timer expired
if ($election && $election['status'] === 'open' && !empty($election['end_at'])) {
    if (strtotime($election['end_at']) <= time()) {
        $conn->query("UPDATE elections SET status = 'closed' WHERE id = " . $election['id']);
        $election['status'] = 'closed';
    }
}

// Real-time AJAX sync endpoint
if (isset($_GET['action']) && $_GET['action'] === 'get_results' && $election) {
    header('Content-Type: application/json');

    // Check countdown inside sync loop
    if ($election['status'] === 'open' && !empty($election['end_at'])) {
        if (strtotime($election['end_at']) <= time()) {
            $conn->query("UPDATE elections SET status = 'closed' WHERE id = " . $election['id']);
            $election['status'] = 'closed';
        }
    }

    $positions = [];
    $pos_res = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']}");
    while ($pos = $pos_res->fetch_assoc()) {
        $candidates = [];
        $cand_stmt = $conn->prepare("SELECT * FROM election_candidates WHERE position_id = ?");
        $cand_stmt->bind_param("i", $pos['id']);
        $cand_stmt->execute();
        $cand_res = $cand_stmt->get_result();
        while ($cand = $cand_res->fetch_assoc()) {
            $candidates[] = $cand;
        }
        $cand_stmt->close();

        $pos['candidates'] = $candidates;
        $positions[] = $pos;
    }

    echo json_encode(['election' => $election, 'positions' => $positions]);
    exit;
}

// Request processing pipeline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $election) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_title') {
        $title = trim($_POST['title'] ?? '');
        if (!empty($title)) {
            $stmt = $conn->prepare("UPDATE elections SET title = ? WHERE id = ?");
            $stmt->bind_param("si", $title, $election['id']);
            if ($stmt->execute()) {
                $success = "Election campaign title updated.";
                $election['title'] = $title;
            }
            $stmt->close();
        }
    } elseif ($action === 'launch_election') {
        $duration = intval($_POST['duration_hours'] ?? 1);
        $end_time = date('Y-m-d H:i:s', strtotime("+$duration hours"));

        $stmt = $conn->prepare("UPDATE elections SET status = 'open', end_at = ? WHERE id = ?");
        $stmt->bind_param("si", $end_time, $election['id']);
        if ($stmt->execute()) {
            log_audit($conn, "election_launched", "elections", $election['id']);
            $success = "Election campaign launched successfully! Live voting countdown activated.";
            $election['status'] = 'open';
            $election['end_at'] = $end_time;
        }
        $stmt->close();
    } elseif ($action === 'toggle_status') {
        $next_status = ($election['status'] === 'open' ? 'closed' : 'open');
        $update = $conn->prepare("UPDATE elections SET status = ? WHERE id = ?");
        $update->bind_param("si", $next_status, $election['id']);
        if ($update->execute()) {
            log_audit($conn, "election_status_changed", "elections", $election['id']);
            $success = "Election status changed to: " . strtoupper($next_status);
            $election['status'] = $next_status;
        }
        $update->close();
    } elseif ($action === 'create_position') {
        $title = trim($_POST['title'] ?? '');
        if (!empty($title)) {
            $stmt = $conn->prepare("INSERT INTO election_positions (election_id, title) VALUES (?, ?)");
            $stmt->bind_param("is", $election['id'], $title);
            if ($stmt->execute()) {
                log_audit($conn, "election_position_created", "election_positions", $stmt->insert_id);
                $success = "Portfolio position " . htmlspecialchars($title) . " successfully added.";
            }
            $stmt->close();
        }
    } elseif ($action === 'create_candidate') {
        $name = trim($_POST['name'] ?? '');
        $matric = trim($_POST['matric'] ?? '');
        $pos_id = intval($_POST['pos_id'] ?? 0);
        $manifesto = trim($_POST['manifesto'] ?? '');

        if (!empty($name) && $pos_id > 0) {
            $stmt = $conn->prepare("INSERT INTO election_candidates (position_id, name, matric_number, manifesto, votes_count) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("isss", $pos_id, $name, $matric, $manifesto);
            if ($stmt->execute()) {
                log_audit($conn, "election_candidate_created", "election_candidates", $stmt->insert_id);
                $success = "Candidate profile registered successfully.";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_candidate') {
        $cand_id = intval($_POST['candidate_id'] ?? 0);
        if ($cand_id > 0) {
            $stmt = $conn->prepare("DELETE FROM election_candidates WHERE id = ?");
            $stmt->bind_param("i", $cand_id);
            if ($stmt->execute()) {
                log_audit($conn, "election_candidate_deleted", "election_candidates", $cand_id);
                $success = "Candidate entry successfully cleared.";
            }
            $stmt->close();
        }
    } elseif ($action === 'clear_all_data') {
        // FIXED: Try-catch blocks added on all deletions to prevent database table errors
        try {
            $conn->query("DELETE FROM election_votes");
        } catch (Exception $e) {
        }
        try {
            $conn->query("DELETE FROM election_candidates");
        } catch (Exception $e) {
        }
        try {
            $conn->query("DELETE FROM election_positions");
        } catch (Exception $e) {
        }
        try {
            $conn->query("DELETE FROM elections");
        } catch (Exception $e) {
        }

        log_audit($conn, "election_hard_reset", "elections", 0);
        $success = "All database election profiles, portfolios, and vote records have been cleared.";
        $election = null;

        // Auto-refresh schema states
        header("Location: election.php");
        exit;
    }
}

function render_election_content()
{
    global $conn, $election, $errors, $success;

    if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Master Header Console -->
    <div class="card-container" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 280px;">
            <form method="POST" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_title">
                <input type="text" name="title" value="<?= htmlspecialchars($election['title']) ?>" class="form-control" style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 800; border: none; padding: 4px 8px; background: transparent;" onchange="this.form.submit()" title="Click to rename portfolio title">
            </form>
            <p style="margin: 6px 0 0 8px; font-size: 0.85rem; color: var(--text-secondary);">
                Status:
                <?php if ($election['status'] === 'draft'): ?>
                    <span style="color: #64748b; font-weight: 700;"><i class="ri-draft-line"></i> In Preparation (Draft Mode)</span>
                <?php elseif ($election['status'] === 'open'): ?>
                    <span style="color: var(--primary-green); font-weight: 700;"><i class="ri-checkbox-circle-line"></i> Active Voting</span>
                <?php else: ?>
                    <span style="color: #ef4444; font-weight: 700;"><i class="ri-close-circle-line"></i> Closed</span>
                <?php endif; ?>
            </p>
            <?php if ($election['status'] === 'open' && !empty($election['end_at'])): ?>
                <p style="margin: 6px 0 0 8px; font-size: 0.85rem; font-weight: 700; color: #ef4444;" id="adminCountdownDisplay">
                    <i class="ri-alarm-warning-line"></i> Time Remaining: Calculating...
                </p>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if ($election['status'] === 'open'): ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <button type="submit" class="btn" style="background: var(--text-dark); color: #ffffff;">
                        <i class="ri-shut-down-line"></i> Stop Election
                    </button>
                </form>
            <?php endif; ?>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('DANGER: This action clears all database election structures and vote records. Do you wish to continue?');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="clear_all_data">
                <button type="submit" class="btn btn-danger" style="background: #fee2e2; color: #ef4444;">
                    <i class="ri-delete-bin-line"></i> Reset Engine
                </button>
            </form>
        </div>
    </div>

    <!-- Launcher Console (Draft Mode Option - Requirement 2) -->
    <?php if ($election['status'] === 'draft'): ?>
        <div class="card-container" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1px solid rgba(11, 143, 58, 0.2);">
            <h3 style="font-family: var(--font-display); font-size: 1.05rem; font-weight: 800; color: var(--dark-green); margin: 0 0 12px;"><i class="ri-rocket-line"></i> Launch Campaign Console</h3>
            <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 0 0 20px;">Portfolios, positions, and candidates are currently open for modifications. You can set the countdown time limit and start the live voting campaign whenever you are ready.</p>

            <form method="POST" style="display: flex; align-items: center; gap: 16px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="launch_election">

                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary);">Voting Time Limit</label>
                    <select name="duration_hours" class="form-control" style="width: 160px; height: 38px; padding: 8px 12px;">
                        <option value="1">1 Hour</option>
                        <option value="2">2 Hours</option>
                        <option value="6">6 Hours</option>
                        <option value="12">12 Hours</option>
                        <option value="24">24 Hours</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="align-self: flex-end; height: 38px; padding: 0 24px; border-radius: 8px;">
                    Launch and Open Ballots
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Live Workspace -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div>
            <div class="card-container">
                <h3 style="font-family: var(--font-display); font-size: 1.02rem; font-weight: 700; margin: 0 0 24px;"><i class="ri-bar-chart-2-line"></i> Real-time Live Standings Monitor</h3>
                <div id="liveAdminResults">
                    <!-- Synchronized dynamically via AJAX -->
                </div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card-container">
                <h3 style="font-family: var(--font-display); font-size: 1.02rem; font-weight: 700; margin: 0 0 16px;">Setup Position</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create_position">
                    <div class="form-group">
                        <input type="text" name="title" required class="form-control" style="font-size: 0.85rem;" placeholder="e.g. Welfare Director">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 0.82rem; justify-content: center;">Add Position</button>
                </form>
            </div>

            <div class="card-container">
                <h3 style="font-family: var(--font-display); font-size: 1.02rem; font-weight: 700; margin: 0 0 16px;">Setup Candidate</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create_candidate">
                    <div class="form-group">
                        <input type="text" name="name" required class="form-control" style="font-size: 0.85rem;" placeholder="Full Student Name">
                    </div>
                    <div class="form-group">
                        <input type="text" name="matric" required class="form-control" style="font-size: 0.85rem;" placeholder="Matric Number">
                    </div>
                    <div class="form-group">
                        <select name="pos_id" required class="form-control" style="font-size: 0.85rem;">
                            <option value="">Select Position</option>
                            <?php
                            $positions = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']}");
                            while ($pos = $positions->fetch_assoc()):
                            ?>
                                <option value="<?= $pos['id'] ?>"><?= htmlspecialchars($pos['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="manifesto" class="form-control" style="font-size: 0.85rem;" placeholder="Short manifesto statement...">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 0.82rem; justify-content: center;">Register Candidate</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Synchronized Live Countdowns (Admin Display)
        const endAtString = "<?= !empty($election['end_at']) ? $election['end_at'] : '' ?>";
        if (endAtString !== '') {
            const targetTime = new Date(endAtString).getTime();

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = targetTime - now;
                const display = document.getElementById('adminCountdownDisplay');

                if (!display) return;

                if (distance < 0) {
                    display.innerHTML = "<i class='ri-alarm-warning-line'></i> Time Expired. Closing voting panels...";
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    return;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                display.innerHTML = `<i class='ri-alarm-warning-line'></i> Time Remaining: ${hours}h ${minutes}m ${seconds}s`;
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        function loadLiveAdminResults() {
            const container = document.getElementById('liveAdminResults');
            if (!container) return;

            fetch('election.php?action=get_results')
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.positions.forEach(pos => {
                        const candidates = pos.candidates;
                        const totalVotes = candidates.reduce((sum, c) => sum + c.votes_count, 0);

                        html += `<div style="margin-bottom: 24px; border-bottom: 1px dashed var(--border-color); padding-bottom: 16px;">
                            <h4 style="font-family: var(--font-display); font-size: 0.92rem; color: var(--primary-green); margin: 0 0 12px; font-weight: 700;">${pos.title}</h4>`;

                        candidates.forEach(cand => {
                            const pct = totalVotes > 0 ? Math.round((cand.votes_count / totalVotes) * 100) : 0;
                            html += `<div style="margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 600; margin-bottom: 4px;">
                                    <span>${cand.name} <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 400;">(${cand.matric_number})</span></span>
                                    <span>${cand.votes_count} votes (${pct}%)</span>
                                </div>
                                <div style="height: 10px; background: #e2e8f0; border-radius: 30px; overflow: hidden;">
                                    <div style="width: ${pct}%; height: 100%; background: var(--primary-green); border-radius: 30px; transition: width 0.5s ease;"></div>
                                </div>
                            </div>`;
                        });

                        if (candidates.length === 0) {
                            html += `<p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">No candidates registered.</p>`;
                        }

                        html += '</div>';
                    });
                    container.innerHTML = html;
                });
        }

        loadLiveAdminResults();
        setInterval(loadLiveAdminResults, 3000);
    </script>
<?php
}

render_admin_layout('render_election_content', 'election', 'Election Manager Engine', ['Election' => '']);
?>