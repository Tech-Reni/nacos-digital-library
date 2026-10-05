<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

try {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    // The column already exists.
}

$errors = [];
$results = [];
$summary = null;

function bulk_cell_key($value)
{
    $value = strtolower(trim((string)$value, " \t\r\n\xEF\xBB\xBF"));
    return preg_replace('/[^a-z0-9]+/', '_', $value);
}

function bulk_read_csv($path)
{
    $handle = fopen($path, 'r');
    if (!$handle) return [];
    $first_line = fgets($handle);
    if ($first_line === false) return [];
    $delimiter = substr_count($first_line, ';') > substr_count($first_line, ',') ? ';' : ',';
    rewind($handle);
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) return [];
    $headers = array_map('bulk_cell_key', $headers);
    $rows = [];
    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($values, function ($value) {
            return trim((string)$value) !== '';
        })) === 0) continue;
        $row = [];
        foreach ($headers as $index => $header) $row[$header] = trim((string)($values[$index] ?? ''));
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function bulk_read_xlsx($path)
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('XLSX support requires the PHP ZipArchive extension.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('The XLSX file could not be opened.');

    $shared = [];
    $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared_xml !== false) {
        $xml = simplexml_load_string($shared_xml);
        if ($xml) foreach ($xml->si as $item) {
            $text = (string)$item->t;
            if ($text === '') foreach ($item->r as $run) $text .= (string)$run->t;
            $shared[] = $text;
        }
    }

    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet_xml === false) throw new RuntimeException('The XLSX file has no readable first worksheet.');
    $sheet = simplexml_load_string($sheet_xml);
    if (!$sheet) throw new RuntimeException('The XLSX worksheet is invalid.');

    $rows = [];
    foreach ($sheet->sheetData->row as $xml_row) {
        $row = [];
        foreach ($xml_row->c as $cell) {
            $reference = (string)$cell['r'];
            preg_match('/([A-Z]+)/', $reference, $match);
            $column = 0;
            foreach (str_split($match[1] ?? '') as $letter) $column = ($column * 26) + ord($letter) - 64;
            $value = (string)$cell->v;
            if ((string)$cell['t'] === 's') $value = $shared[(int)$value] ?? '';
            $row[$column - 1] = trim($value);
        }
        if ($row) $rows[] = $row;
    }
    if (count($rows) < 1) return [];
    $headers = array_map('bulk_cell_key', $rows[0]);
    $output = [];
    foreach (array_slice($rows, 1) as $values) {
        if (count(array_filter($values, function ($value) {
            return trim((string)$value) !== '';
        })) === 0) continue;
        $row = [];
        foreach ($headers as $index => $header) $row[$header] = trim((string)($values[$index] ?? ''));
        $output[] = $row;
    }
    return $output;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $file = $_FILES['student_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a CSV or XLSX file to import.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The import file must not exceed 5MB.';
    } else {
        try {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $rows = $extension === 'xlsx' ? bulk_read_xlsx($file['tmp_name']) : ($extension === 'csv' ? bulk_read_csv($file['tmp_name']) : []);
            if (!$rows) $errors[] = 'The file is empty or could not be read.';
            if ($extension !== 'csv' && $extension !== 'xlsx') $errors[] = 'Only CSV and XLSX files are supported.';

            if (empty($errors)) {
                $insert = $conn->prepare("INSERT INTO users (uuid, fullname, matric_number, department, level, programme, password_hash, device_fp, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, '', 1)");
                if (!$insert) throw new RuntimeException('Could not prepare the student registration query.');

                foreach ($rows as $number => $row) {
                    $line = $number + 2;
                    $matric = strtoupper(trim($row['matric_number'] ?? $row['matric'] ?? $row['username'] ?? ''));
                    $surname = trim($row['surname'] ?? $row['last_name'] ?? '');
                    $fullname = trim($row['fullname'] ?? $row['full_name'] ?? '');
                    $department = normalize_department($row['department'] ?? 'computer-science');
                    $level = strtoupper(trim($row['level'] ?? 'ND1'));
                    $programme = normalize_programme($row['programme'] ?? $row['study_mode'] ?? 'Full-time');

                    if ($fullname === '') $fullname = $surname;
                    if ($matric === '' || $surname === '') {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Matric number and surname are required.'];
                        continue;
                    }
                    if (!validate_matric_number($matric)) {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Invalid matric number format.'];
                        continue;
                    }
                    if (!in_array($department, ['computer-science', 'mass-communication', 'accountancy'], true)) {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Unsupported department.'];
                        continue;
                    }
                    if (!in_array($level, ['ND1', 'ND2', 'ND3', 'HND1', 'HND2', 'HND3'], true)) {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Unsupported level.'];
                        continue;
                    }
                    if (!in_array($programme, ['Full-time', 'Part-time', 'CODFEL'], true)) {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Unsupported programme.'];
                        continue;
                    }

                    $uuid = bin2hex(random_bytes(16));
                    $password_hash = password_hash($surname, PASSWORD_BCRYPT);
                    $insert->bind_param('sssssss', $uuid, $fullname, $matric, $department, $level, $programme, $password_hash);
                    if ($insert->execute()) {
                        $new_id = $conn->insert_id;
                        log_audit($conn, 'bulk_student_registered', 'users', $new_id, ['matric_number' => $matric]);
                        $results[] = ['line' => $line, 'status' => 'Imported', 'message' => $matric . ' (' . format_department($department) . ')'];
                    } elseif ($insert->errno === 1062) {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Matric number already exists.'];
                    } else {
                        $results[] = ['line' => $line, 'status' => 'Skipped', 'message' => 'Database error while creating account.'];
                    }
                }
                $insert->close();
                $summary = ['imported' => count(array_filter($results, function ($item) {
                    return $item['status'] === 'Imported';
                })), 'skipped' => count(array_filter($results, function ($item) {
                    return $item['status'] === 'Skipped';
                }))];
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<?php
/**
 * Renders the Bulk Student Registration panel inside the shared Admin
 * layout shell (which provides the sidebar + mobile hamburger menu).
 */
function render_bulk_students_content()
{
    global $conn, $BASE_URL, $errors, $results, $summary;
?>
    <style>
        .bulk-panel {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .bulk-panel h1 {
            margin-top: 0;
        }

        .bulk-panel code {
            background: #f1f5f9;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .bulk-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin: 24px 0;
        }

        .bulk-form input[type=file] {
            flex: 1 1 280px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        .bulk-btn {
            border: 0;
            border-radius: 8px;
            padding: 11px 18px;
            background: #0b8f3a;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .bulk-alert {
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
        }

        .bulk-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .bulk-success {
            background: #dcfce7;
            color: #166534;
        }

        .bulk-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 20px;
        }

        .bulk-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 520px;
        }

        .bulk-table th,
        .bulk-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bulk-table th {
            font-size: .8rem;
            text-transform: uppercase;
            color: #64748b;
        }

        @media (max-width: 620px) {
            .bulk-panel {
                padding: 18px;
            }

            .bulk-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div style="max-width: 1100px; margin: 0 auto;">
        <section class="bulk-panel">
            <h1>Bulk Student Registration</h1>
            <p>Upload a class nominal to create student accounts. Required columns: <code>matric_number</code> and <code>surname</code>. Optional columns: <code>fullname</code>, <code>department</code>, <code>level</code>, and <code>programme</code>.</p>
            <p>Each student's matric number is their username and their surname is their temporary password. They must choose a new password on first login.</p>

            <?php foreach ($errors as $error): ?>
                <div class="bulk-alert bulk-error"><?= safe_output($error) ?></div>
            <?php endforeach; ?>

            <?php if ($summary): ?>
                <div class="bulk-alert bulk-success">Imported <?= (int) $summary['imported'] ?> student(s); skipped <?= (int) $summary['skipped'] ?> row(s).</div>
            <?php endif; ?>

            <form class="bulk-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="file" name="student_file" accept=".csv,.xlsx" required>
                <button class="bulk-btn" type="submit">Import Students</button>
            </form>

            <?php if ($results): ?>
                <div class="bulk-table-wrap">
                    <table class="bulk-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><?= (int) $result['line'] ?></td>
                                    <td><?= safe_output($result['status']) ?></td>
                                    <td><?= safe_output($result['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
<?php
}

render_admin_layout('render_bulk_students_content', 'bulk_students', 'Bulk Student Registration', ['Students' => $BASE_URL . 'admin/users.php', 'Bulk Register' => '']);
?>