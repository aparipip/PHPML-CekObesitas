<?php
require_once __DIR__ . '/vendor/autoload.php';

use Phpml\Classification\NaiveBayes;

/**
 * =========================
 * KONFIGURASI
 * =========================
 */
$csvFile      = __DIR__ . '/dataset.csv';
$splitColumn  = 'Pegawai';
$labelColumn  = 'Diagnosa';
$ignoreCols   = ['Pegawai'];

/**
 * =========================
 * HELPER
 * =========================
 */
function norm(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $u = strtoupper($v);

    if ($u === 'YES' || $u === 'YA' || $u === 'Y') return 'YA';
    if ($u === 'NO'  || $u === 'TIDAK' || $u === 'T') return 'TIDAK';

    if ($u === 'P') return 'P';
    if ($u === 'L') return 'L';

    return $v;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * =========================
 * 1) BACA CSV
 * =========================
 */
if (!file_exists($csvFile)) {
    die("ERROR: dataset.csv tidak ditemukan.");
}

$hFile  = fopen($csvFile, 'r');
$header = fgetcsv($hFile);
if (!$header) die("ERROR: Header CSV tidak terbaca.");

$rows = [];
while (($row = fgetcsv($hFile)) !== false) {
    if (count($row) !== count($header)) continue;

    $assoc = array_combine($header, $row);
    if (!isset($assoc[$splitColumn]) || !isset($assoc[$labelColumn])) {
        die("ERROR: Kolom '$splitColumn' / '$labelColumn' tidak ditemukan.");
    }

    foreach ($assoc as $k => $v) $assoc[$k] = norm((string)$v);
    $assoc[$splitColumn] = (int)trim((string)$assoc[$splitColumn]);

    $rows[] = $assoc;
}
fclose($hFile);

if (count($rows) === 0) die("ERROR: Data kosong.");

/**
 * =========================
 * 2) FITUR
 * =========================
 */
$featureCols = [];
foreach ($header as $col) {
    if ($col === $labelColumn) continue;
    if (in_array($col, $ignoreCols, true)) continue;
    $featureCols[] = $col;
}

/**
 * =========================
 * 3) SPLIT TRAIN (1-60)
 * =========================
 */
$trainRows = array_values(array_filter($rows, fn($r) => $r[$splitColumn] >= 1 && $r[$splitColumn] <= 60));
if (count($trainRows) === 0) die("ERROR: Data train (pegawai 1-60) tidak terbaca.");

/**
 * =========================
 * 4) BUILD X,y TRAIN (string kategori)
 * =========================
 */
$Xtrain = [];
$ytrain = [];
foreach ($trainRows as $r) {
    $label = trim((string)$r[$labelColumn]);
    if ($label === '') continue;

    $sample = [];
    foreach ($featureCols as $c) {
        $val = trim((string)$r[$c]);
        $sample[] = ($val === '') ? 'MISSING' : $val;
    }

    $Xtrain[] = $sample;
    $ytrain[] = $label;
}

/**
 * =========================
 * 5) ENCODE kategori -> angka (mapping dari TRAIN)
 *    + buat daftar opsi dropdown dari mapping
 * =========================
 */
$maps = [];         // index fitur -> ["kategori" => int]
$options = [];      // index fitur -> array kategori (untuk dropdown)

for ($i=0; $i<count($Xtrain); $i++) {
    for ($j=0; $j<count($Xtrain[$i]); $j++) {
        $v = $Xtrain[$i][$j];
        if (!isset($maps[$j])) $maps[$j] = [];
        if (!array_key_exists($v, $maps[$j])) {
            $maps[$j][$v] = count($maps[$j]);
        }
        $Xtrain[$i][$j] = $maps[$j][$v];
    }
}

// options dropdown (ambil keys kategori)
for ($j=0; $j<count($featureCols); $j++) {
    $opts = array_keys($maps[$j] ?? []);
    sort($opts, SORT_NATURAL);
    $options[$j] = $opts;
}

/**
 * =========================
 * 6) TRAIN MODEL
 * =========================
 */
$model = new NaiveBayes();
$model->train($Xtrain, $ytrain);

/**
 * =========================
 * 7) HANDLE SUBMIT FORM
 * =========================
 */
$prediksi = null;
$error = null;
$inputChosen = []; // simpan pilihan agar form tetap terisi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSample = [];

    for ($j=0; $j<count($featureCols); $j++) {
        $field = "f$j";
        $val = isset($_POST[$field]) ? norm((string)$_POST[$field]) : '';
        $inputChosen[$field] = $val;

        if ($val === '') {
            $error = "Semua field wajib diisi. (Kosong pada: {$featureCols[$j]})";
            break;
        }

        // encode kategori sesuai mapping TRAIN
        if (!isset($maps[$j]) || !array_key_exists($val, $maps[$j])) {
            // jika kategori tidak dikenal, fallback 0 (atau bisa error)
            $newSample[] = 0;
        } else {
            $newSample[] = $maps[$j][$val];
        }
    }

    if (!$error) {
        $prediksi = $model->predict($newSample);
    }
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Prediksi Obesitas - Naive Bayes (PHP-ML)</title>
  <style>
    body{font-family:Arial, sans-serif; background:#f6f7fb; margin:0; padding:24px;}
    .wrap{max-width:900px; margin:0 auto;}
    .card{background:#fff; border:1px solid #e7e7e7; border-radius:12px; padding:18px 18px; box-shadow:0 2px 10px rgba(0,0,0,.04);}
    h1{margin:0 0 10px 0; font-size:20px;}
    .muted{color:#555; font-size:13px; margin:0 0 16px 0;}
    form{display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px;}
    label{font-size:13px; font-weight:700; display:block; margin:0 0 6px 0;}
    select{width:100%; padding:10px; border-radius:10px; border:1px solid #ccc; background:#fff;}
    .full{grid-column:1 / -1;}
    .btn{padding:11px 14px; border:0; border-radius:10px; cursor:pointer; font-weight:700;}
    .btn-primary{background:#2d6cdf; color:#fff;}
    .alert{padding:10px 12px; border-radius:10px; margin:12px 0; font-size:14px;}
    .alert-err{background:#ffecec; border:1px solid #ffb8b8;}
    .alert-ok{background:#eaffef; border:1px solid #a8f0b7;}
    .result{font-size:18px; font-weight:800;}
    .footer{margin-top:10px; font-size:12px; color:#666;}

    .card-image {
  margin-top: 20px;
}

.card-image img {
  width: 100%;
  max-width: 100%;
  border-radius: 10px;
  border: 1px solid #ddd;
}

.card-image .caption {
  margin-top: 8px;
  font-size: 13px;
  color: #555;
  text-align: center;
}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Prediksi Diagnosa (Obesitas / Normal) - Naive Bayes</h1>
    <p class="muted">
      Model dilatih dari <b>pegawai 1–60</b>. Anda bisa mengisi atribut untuk memprediksi orang baru.
    </p>

    <?php if ($error): ?>
      <div class="alert alert-err"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($prediksi !== null): ?>
      <div class="alert alert-ok">
        <div class="result">Hasil Prediksi: <?= h($prediksi) ?></div>
        <!-- <div class="footer">Catatan: jika ada kategori input yang tidak dikenal data train, sistem memakai fallback nilai 0.</div> -->
      </div>
    <?php endif; ?>

    <form method="post">
      <?php for ($j=0; $j<count($featureCols); $j++): 
          $field = "f$j";
          $label = $featureCols[$j];
          $selected = $inputChosen[$field] ?? '';
      ?>
        <div>
          <label for="<?= h($field) ?>"><?= h($label) ?></label>
          <select id="<?= h($field) ?>" name="<?= h($field) ?>" required>
            <option value="">-- pilih --</option>
            <?php foreach (($options[$j] ?? []) as $opt): ?>
              <option value="<?= h($opt) ?>" <?= ($opt === $selected) ? 'selected' : '' ?>>
                <?= h($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endfor; ?>

      <div class="full" style="display:flex; gap:10px; margin-top:6px;">
        <button class="btn btn-primary" type="submit">Prediksi</button>
        <a class="btn" href="predict.php" style="text-decoration:none; background:#eee; color:#000; display:inline-block;">Reset</a>
      </div>
    </form>
  </div>
    <!-- CARD BARU: TABEL ATRIBUT -->
    <div class="card card-image">
    <h1>Daftar Atribut dan Nilai Kategori</h1>
    <p class="muted">
      Tabel ini menunjukkan atribut, rentang nilai, dan kategori yang digunakan
      dalam proses pelatihan dan prediksi Naive Bayes.
    </p>

    <img src="images/atribut.png" alt="Tabel Atribut Dataset Obesitas">

    <div class="caption">
      Gambar Definisi atribut dan kategori dataset deteksi obesitas
    </div>
</div>
</body>
</html>
