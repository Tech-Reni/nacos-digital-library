<?php

/**
 * election.php
 * Student Election Voting & Real-time Portfolio Standings (Live Countdown Sync)
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

check_auth();

$user_id = $_SESSION['user_id'];

// Fetch current active election details
$election_res = $conn->query("SELECT * FROM elections WHERE status = 'open' ORDER BY id DESC LIMIT 1");
$election = $election_res->fetch_assoc();

// Auto-close election on student page access if timer has expired (Self-closing system)
if ($election && $election['status'] === 'open' && !empty($election['end_at'])) {
    if (strtotime($election['end_at']) <= time()) {
        $conn->query("UPDATE elections SET status = 'closed' WHERE id = " . $election['id']);
        $election = null; // Forces page reload to render Closed/Standing mode directly
    }
}

// API Endpoint for dynamic live standings updates
if (isset($_GET['action']) && $_GET['action'] === 'get_results') {
    header('Content-Type: application/json');
    $active_election_res = $conn->query("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
    $active_election = $active_election_res->fetch_assoc();

    $positions = [];
    if ($active_election) {
        $pos_res = $conn->query("SELECT * FROM election_positions WHERE election_id = {$active_election['id']}");
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
    }

    echo json_encode(['election' => $active_election, 'positions' => $positions]);
    exit;
}

// Process student ballot submissions securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $election) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security context.']);
        exit;
    }

    $votes = $_POST['votes'] ?? []; // Map of [position_id => candidate_id]
    if (empty($votes)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select at least one candidate.']);
        exit;
    }

    $voted_positions = [];
    $stmt = $conn->prepare("SELECT position_id FROM election_votes WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $voted_positions[] = $r['position_id'];
    }
    $stmt->close();

    $conn->begin_transaction();
    try {
        foreach ($votes as $pos_id => $cand_id) {
            $pos_id = intval($pos_id);
            $cand_id = intval($cand_id);

            if (in_array($pos_id, $voted_positions)) {
                continue; // Skip portfolios the user has already voted for
            }

            // Register the vote
            $vote_stmt = $conn->prepare("INSERT INTO election_votes (user_id, position_id, candidate_id) VALUES (?, ?, ?)");
            $vote_stmt->bind_param("iii", $user_id, $pos_id, $cand_id);
            $vote_stmt->execute();
            $vote_stmt->close();

            // Increment count
            $inc_stmt = $conn->prepare("UPDATE election_candidates SET votes_count = votes_count + 1 WHERE id = ?");
            $inc_stmt->bind_param("i", $cand_id);
            $inc_stmt->execute();
            $inc_stmt->close();
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your selection has been securely registered!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Execution failed: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch list of positions the student has already voted for
$voted_positions = [];
$voted_choices = []; // Map of [position_id => candidate_id]
if ($election) {
    $voted_stmt = $conn->prepare("SELECT position_id, candidate_id FROM election_votes WHERE user_id = ?");
    $voted_stmt->bind_param("i", $user_id);
    $voted_stmt->execute();
    $voted_res = $voted_stmt->get_result();
    while ($row = $voted_res->fetch_assoc()) {
        $voted_positions[] = $row['position_id'];
        $voted_choices[$row['position_id']] = $row['candidate_id'];
    }
    $voted_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Vote in the NACOS YabaTech student elections - Cast your ballot for executive council positions securely and view live results.">
    <title>NACOS YabaTech | Election</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0b8f3a;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --surface: #ffffff;
            --border-subtle: 1px solid rgba(15, 23, 42, 0.06);
            --card-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
        }

        body {
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            margin: 0;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 24px 60px;
        }

        .card {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            border: var(--border-subtle);
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }

        .pos-section {
            margin-bottom: 32px;
            border-bottom: 1px dashed rgba(15, 23, 42, 0.08);
            padding-bottom: 24px;
        }

        .pos-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
            color: var(--primary-green);
            margin: 0 0 16px;
            font-weight: 700;
        }

        .cand-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .cand-card {
            background: #f8fafc;
            border: var(--border-subtle);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .cand-card:hover {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }

        .cand-card input[type="radio"] {
            position: absolute;
            right: 16px;
            top: 16px;
            width: 20px;
            height: 20px;
            accent-color: var(--primary-green);
        }

        .btn-submit {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 30px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s ease;
        }

        .btn-submit:hover {
            background: #066b2a;
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/includes/header.php'; ?>

    <main class="container">

        <?php if (!$election): ?>
            <div class="card" style="text-align: center; padding: 60px 24px;">
                <i class="ri-shut-down-line" style="font-size: 4rem; color: var(--text-secondary); display: block; margin-bottom: 16px;"></i>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.5rem; margin: 0 0 8px;">No Election Active</h2>
                <p style="color: var(--text-secondary); margin: 0;">There are currently no active executive council elections. Please check back later.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 32px; border-bottom: 1px solid rgba(15, 23, 42, 0.08); padding-bottom: 24px;">
                    <div>
                        <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; margin: 0 0 8px;"><?= htmlspecialchars($election['title']) ?></h2>
                        <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">Select one candidate per portfolio. Your selections are secure and anonymous.</p>
                    </div>
                    <?php if (!empty($election['end_at'])): ?>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 10px 20px; border-radius: 12px; font-weight: 700; color: #ef4444; font-family: 'Outfit', sans-serif;" id="studentCountdownDisplay">
                            <i class="ri-alarm-warning-line"></i> Loading Timer...
                        </div>
                    <?php endif; ?>
                </div>

                <form id="portfolioBallotForm">
                    <?php
                    $pos_res = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']}");
                    $has_unvoted = false;

                    while ($pos = $pos_res->fetch_assoc()):
                        $already_voted = in_array($pos['id'], $voted_positions);
                    ?>
                        <div class="pos-section">
                            <h3 class="pos-title"><?= htmlspecialchars($pos['title']) ?>
                                <?php if ($already_voted): ?>
                                    <span style="font-size: 0.72rem; background: #e6f4ea; color: var(--primary-green); padding: 4px 10px; border-radius: 30px; margin-left: 8px;"><i class="ri-checkbox-circle-fill"></i> Vote Registered</span>
                                <?php endif; ?>
                            </h3>

                            <?php if ($already_voted): ?>
                                <div class="standings-view" data-position-id="<?= $pos['id'] ?>" data-user-choice="<?= $voted_choices[$pos['id']] ?>">
                                    <!-- Loaded via AJAX -->
                                </div>
                            <?php else:
                                $has_unvoted = true;
                                $cand_stmt = $conn->prepare("SELECT * FROM election_candidates WHERE position_id = ?");
                                $cand_stmt->bind_param("i", $pos['id']);
                                $cand_stmt->execute();
                                $candidates = $cand_stmt->get_result();
                            ?>
                                <div class="cand-grid">
                                    <?php while ($cand = $candidates->fetch_assoc()): ?>
                                        <div class="cand-card" onclick="document.getElementById('c_<?= $cand['id'] ?>').checked = true">
                                            <input type="radio" name="votes[<?= $pos['id'] ?>]" id="c_<?= $cand['id'] ?>" value="<?= $cand['id'] ?>" required>
                                            <strong style="display: block; font-size: 0.95rem; margin-bottom: 4px;"><?= htmlspecialchars($cand['name']) ?></strong>
                                            <span style="font-family: monospace; font-size: 0.78rem; color: var(--text-secondary); display: block; margin-bottom: 8px;"><?= htmlspecialchars($cand['matric_number']) ?></span>
                                            <p style="margin: 0; font-size: 0.82rem; color: var(--text-secondary); line-height: 1.4;">"<?= htmlspecialchars($cand['manifesto']) ?>"</p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php $cand_stmt->close();
                            endif; ?>
                        </div>
                    <?php endwhile; ?>

                    <?php if ($has_unvoted): ?>
                        <button type="submit" class="btn-submit">
                            <i class="ri-checkbox-circle-line"></i> Cast Ballot on Portfolio Selections
                        </button>
                    <?php else: ?>
                        <div style="background: #e6f4ea; color: var(--primary-green); padding: 16px; border-radius: 10px; font-weight: 600; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                            <i class="ri-checkbox-circle-fill" style="font-size: 1.25rem;"></i> You have completed voting for all active portfolios in this election!
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        // Sync Remaining Time on Student Ballot Panel
        const endAtString = "<?= !empty($election['end_at']) ? $election['end_at'] : '' ?>";
        if (endAtString !== '') {
            const targetTime = new Date(endAtString).getTime();

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = targetTime - now;
                const display = document.getElementById('studentCountdownDisplay');

                if (!display) return;

                if (distance < 0) {
                    display.innerHTML = "<i class='ri-alarm-warning-line'></i> Voting Closed";
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    return;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                display.innerHTML = `<i class='ri-alarm-warning-line'></i> Time Left: ${hours}h ${minutes}m ${seconds}s`;
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        function loadLiveResults() {
            const containers = document.querySelectorAll('.standings-view');
            if (containers.length === 0) return;

            fetch('election.php?action=get_results')
                .then(r => r.json())
                .then(data => {
                    containers.forEach(container => {
                        const posId = parseInt(container.getAttribute('data-position-id'));
                        const userChoiceId = parseInt(container.getAttribute('data-user-choice'));

                        const posData = data.positions.find(p => p.id === posId);
                        if (!posData) return;

                        const totalVotes = posData.candidates.reduce((sum, c) => sum + c.votes_count, 0);
                        let html = '<div style="display: flex; flex-direction: column; gap: 12px; background: #f8fafc; padding: 16px; border-radius: 12px; border: var(--border-subtle);">';

                        posData.candidates.forEach(cand => {
                            const pct = totalVotes > 0 ? Math.round((cand.votes_count / totalVotes) * 100) : 0;
                            const votedSign = cand.id === userChoiceId ? ' <span style="color: var(--primary-green); font-weight:700;">(Your Vote)</span>' : '';

                            html += `
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 600; margin-bottom: 4px;">
                                        <span>${cand.name} ${votedSign}</span>
                                        <span>${cand.votes_count} votes (${pct}%)</span>
                                    </div>
                                    <div style="height: 10px; background: #e2e8f0; border-radius: 30px; overflow: hidden;">
                                        <div style="width: ${pct}%; height: 100%; background: var(--primary-green); border-radius: 30px; transition: width 0.5s ease;"></div>
                                    </div>
                                </div>`;
                        });

                        html += '</div>';
                        container.innerHTML = html;
                    });
                });
        }

        const ballotForm = document.getElementById('portfolioBallotForm');
        if (ballotForm) {
            ballotForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('csrf_token', '<?= csrf_token() ?>');

                fetch('election.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            });
        }

        loadLiveResults();
        setInterval(loadLiveResults, 3000);
    </script>
</body>

</html>