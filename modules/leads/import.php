<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager','counselor']);

$msg = '';

function read_import_rows($filePath, $extension) {
  if ($extension === 'xlsx') {
    if (!class_exists('ZipArchive')) {
      return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
      return [];
    }
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
      $shared = simplexml_load_string($sharedXml);
      foreach ($shared->si as $item) {
        $sharedStrings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
      }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
      $zip->close();
      return [];
    }
    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    foreach ($sheet->sheetData->row as $xmlRow) {
      $row = [];
      foreach ($xmlRow->c as $cell) {
        preg_match('/^[A-Z]+/', (string)$cell['r'], $match);
        $column = 0;
        foreach (str_split($match[0] ?? '') as $letter) {
          $column = ($column * 26) + ord($letter) - 64;
        }
        $value = (string)$cell->v;
        if ((string)$cell['t'] === 's') {
          $value = $sharedStrings[(int)$value] ?? '';
        } elseif ((string)$cell['t'] === 'inlineStr') {
          $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]')));
        }
        $row[$column - 1] = $value;
      }
      if ($row) {
        ksort($row);
        $lastColumn = max(array_keys($row));
        for ($index = 0; $index <= $lastColumn; $index++) {
          $row[$index] = $row[$index] ?? '';
        }
        ksort($row);
      }
      $rows[] = $row;
    }
    $zip->close();
    return $rows;
  }

  $handle = fopen($filePath, 'r');
  $rows = [];
  while (($row = fgetcsv($handle)) !== false) {
    $rows[] = $row;
  }
  fclose($handle);
  return $rows;
}

function normalize_import_header($value) {
  $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value);
  $value = strtolower(trim($value));
  return preg_replace('/[^a-z0-9]+/', ' ', $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file']['tmp_name'])) {
    csrf_verify();
  $defaultSource = 'other';
  $extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
  $rows = in_array($extension, ['xlsx', 'csv'], true) ? read_import_rows($_FILES['csv_file']['tmp_name'], $extension) : [];
  if (!in_array($extension, ['xlsx', 'csv'], true)) {
    $msg = 'Please select a normal Excel .xlsx file or .csv file.';
  }
  if ($extension === 'xlsx' && $rows === null) {
    $msg = 'Excel support is not enabled in Apache PHP. Restart Apache after enabling the PHP ZIP extension.';
    $rows = [];
  }
  $header = array_shift($rows) ?: [];
  $header = array_map('normalize_import_header', $header ?: []);
  $headerAliases = [
    'full name' => 'name',
    'student name' => 'name',
    'lead name' => 'name',
    'mobile' => 'phone',
    'mobile number' => 'phone',
    'phone number' => 'phone',
    'phone no' => 'phone',
    'contact number' => 'phone',
    'email address' => 'email',
    'email id' => 'email',
    'location' => 'city',
    'city name' => 'city',
  ];
  $header = array_map(static fn($value) => $headerAliases[$value] ?? $value, $header);
  $columnIndex = array_flip($header);
  $requiredColumns = ['name', 'phone', 'email', 'city'];
  $validImport = $msg === '' && !array_diff($requiredColumns, array_keys($columnIndex));
  if (!$validImport) {
    $headerLooksLikeData = !preg_grep('/name|phone|mobile|email|city|location|contact/', $header);
    if ($msg === '' && $headerLooksLikeData) {
        array_unshift($rows, $header);
        $header = $requiredColumns;
        $columnIndex = array_flip($header);
        $validImport = true;
    } elseif ($msg === '') {
        $msg = 'Import failed: use columns name, phone, email, and city, or place those four fields in the first four columns without a header row.';
    }
  }
    $inserted = 0; $skipped = 0;

    foreach ($validImport ? $rows : [] as $row) {
    $name = $row[$columnIndex['name']] ?? '';
    $phone = $row[$columnIndex['phone']] ?? '';
    $email = $row[$columnIndex['email']] ?? '';
    $city = $row[$columnIndex['city']] ?? '';
    $source = $defaultSource;
        $name = trim($name ?? ''); $phone = preg_replace('/\D+/', '', $phone ?? '');
        if ($name === '' || $phone === '') { $skipped++; continue; }

        $dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ?");
        $dup->execute([$phone]);
        if ($dup->fetch()) { $skipped++; continue; }

        $source = $defaultSource;
        $assignedTo = is_counselor() ? current_user_id() : null;
        $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, city, source, assigned_to, created_by, status) VALUES (?,?,?,?,?,?,?, 'new')");
        $stmt->execute([$name, $phone, trim($email ?? ''), trim($city ?? ''), $source, $assignedTo, current_user_id()]);
        $inserted++;
    }
    if ($msg === '') {
      log_activity($pdo, "Bulk imported $inserted leads ($skipped skipped)");
      $msg = "Import complete: $inserted leads added, $skipped skipped (duplicates or missing data).";
    }
}


$pageTitle = 'Bulk Import Leads';
require __DIR__ . '/../../includes/header.php';
?>

<h4 class="mb-3">Import Bulk Leads</h4>
<?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

<div class="card card-stat form-card p-4">
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="file" name="csv_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="form-control mb-3" required>
    <button class="btn btn-primary">Upload &amp; Import</button>
    <a href="list.php" class="btn btn-outline-secondary">Back</a>
  </form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
