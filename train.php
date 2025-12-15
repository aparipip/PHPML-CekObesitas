<?php
require_once __DIR__ . '/vendor/autoload.php';

use Phpml\Classification\NaiveBayes;

/**
 * ============================================================
 *  KONFIGURASI DATASET (sesuai header CSV Anda)
 * ============================================================
 */
$csvFile      = __DIR__ . '/dataset.csv';
$splitColumn  = 'Pegawai';   // kolom untuk split 1-60 train, 61-80 test
$labelColumn  = 'Diagnosa';  // label/kelas (OBESITAS / NORMAL)
$ignoreCols   = ['Pegawai']; // kolom yang tidak dipakai sebagai fitur

/**
 * ============================================================
 *  HELPER: Output rapi seperti contoh (CLI / Browser)
 * ============================================================
 */
function isCli(): bool {
    return (php_sapi_name() === 'cli');
}
function preOpen(): void {
    if (!isCli()) echo "<pre>";
}
function preClose(): void {
    if (!isCli()) echo "</pre>";
}
function line(string $char='=', int $len=52): string {
    return str_repeat($char, $len) . PHP_EOL;
}
function row2(string $label, string $value, int $wLabel=16): string {
    $label = str_pad($label, $wLabel, ' ', STR_PAD_RIGHT);
    return $label . " : " . $value . PHP_EOL;
}

/**
 * ============================================================
 *  HELPER: Normalisasi nilai (YA/TIDAK, spasi, case)
 * ============================================================
 */
function norm(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $u = strtoupper($v);

    // normalisasi YA/TIDAK (jaga-jaga jika ada variasi)
    if ($u === 'YES' || $u === 'YA' || $u === 'Y') return 'YA';
    if ($u === 'NO'  || $u === 'TIDAK' || $u === 'T' ) return 'TIDAK';

    // normalisasi P/L jika ada yang lowercase
    if ($u === 'P') return 'P';
    if ($u === 'L') return 'L';

    return $v; // kategori lain biarkan apa adanya
}

/**
 * Anggap positif = OBESITAS
 */
function isObesitas(string $label): bool {
    $u = strtoupper(trim($label));
    return ($u === 'OBESITAS' || $u === 'OBESE' || $u === 'OBES');
}

/**
 * ============================================================
 *  1) BACA CSV
 * ============================================================
 */
if (!file_exists($csvFile)) {
    preOpen();
    echo "ERROR: dataset.csv tidak ditemukan di: $csvFile" . PHP_EOL;
    preClose();
    exit;
}

$h = fopen($csvFile, 'r');
$header = fgetcsv($h);

if (!$header || count($header) < 2) {
    preOpen();
    echo "ERROR: Header CSV tidak terbaca atau format CSV salah." . PHP_EOL;
    preClose();
    exit;
}

$rows = [];
while (($row = fgetcsv($h)) !== false) {
    if (count($row) !== count($header)) continue;

    $assoc = array_combine($header, $row);
    if (!isset($assoc[$splitColumn]) || !isset($assoc[$labelColumn])) {
        preOpen();
        echo "ERROR: Kolom '$splitColumn' atau '$labelColumn' tidak ditemukan di dataset.csv" . PHP_EOL;
        echo "Header yang terbaca: " . implode(", ", $header) . PHP_EOL;
        preClose();
        fclose($h);
        exit;
    }

    // normalisasi isi
    foreach ($assoc as $k => $v) {
        $assoc[$k] = norm((string)$v);
    }

    // pastikan splitColumn numerik
    $assoc[$splitColumn] = (int)trim((string)$assoc[$splitColumn]);

    $rows[] = $assoc;
}
fclose($h);

$datasetSize = count($rows);
if ($datasetSize === 0) {
    preOpen();
    echo "ERROR: Tidak ada data yang terbaca dari dataset.csv" . PHP_EOL;
    preClose();
    exit;
}

/**
 * ============================================================
 *  2) TENTUKAN FITUR (semua kolom selain label & ignore)
 * ============================================================
 */
$featureCols = [];
foreach ($header as $col) {
    if ($col === $labelColumn) continue;
    if (in_array($col, $ignoreCols, true)) continue;
    $featureCols[] = $col;
}

/**
 * ============================================================
 *  3) SPLIT TRAIN/TEST MANUAL (Pegawai 1-60 / 61-80)
 * ============================================================
 */
$trainRows = array_values(array_filter($rows, fn($r) => $r[$splitColumn] >= 1  && $r[$splitColumn] <= 60));
$testRows  = array_values(array_filter($rows, fn($r) => $r[$splitColumn] >= 61 && $r[$splitColumn] <= 80));

$trainSize = count($trainRows);
$testSize  = count($testRows);

/**
 * ============================================================
 *  4) BUILD X (samples) & y (labels)
 * ============================================================
 */
function buildXY(array $rows, array $featureCols, string $labelColumn): array {
    $X = [];
    $y = [];
    foreach ($rows as $r) {
        $label = trim((string)$r[$labelColumn]);
        if ($label === '') continue;

        $sample = [];
        foreach ($featureCols as $c) {
            $val = trim((string)$r[$c]);
            $sample[] = ($val === '') ? 'MISSING' : $val;
        }

        $X[] = $sample;
        $y[] = $label;
    }
    return [$X, $y];
}

[$Xtrain, $ytrain] = buildXY($trainRows, $featureCols, $labelColumn);
[$Xtest,  $ytest ] = buildXY($testRows,  $featureCols, $labelColumn);

/**
 * ============================================================
 *  5) ENCODE KATEGORI -> ANGKA (MAP dari TRAIN)
 * ============================================================
 */
$maps = []; // index fitur -> ["kategori" => angka]

// encode train (bangun mapping)
for ($i = 0; $i < count($Xtrain); $i++) {
    for ($j = 0; $j < count($Xtrain[$i]); $j++) {
        $v = $Xtrain[$i][$j];
        if (!isset($maps[$j])) $maps[$j] = [];
        if (!array_key_exists($v, $maps[$j])) {
            $maps[$j][$v] = count($maps[$j]);
        }
        $Xtrain[$i][$j] = $maps[$j][$v];
    }
}

// encode test pakai mapping train (kategori baru -> fallback 0)
for ($i = 0; $i < count($Xtest); $i++) {
    for ($j = 0; $j < count($Xtest[$i]); $j++) {
        $v = $Xtest[$i][$j];
        $Xtest[$i][$j] = (isset($maps[$j]) && array_key_exists($v, $maps[$j])) ? $maps[$j][$v] : 0;
    }
}

/**
 * ============================================================
 *  6) TRAIN MODEL (Naive Bayes)
 * ============================================================
 */
$model = new NaiveBayes();
$model->train($Xtrain, $ytrain);

/**
 * ============================================================
 *  7) EVALUASI (Akurasi + Confusion Matrix + Classification Report)
 * ============================================================
 */
$correct = 0;
$TP = 0; $TN = 0; $FP = 0; $FN = 0;

$yPred = [];

for ($i = 0; $i < count($Xtest); $i++) {
    $pred = $model->predict($Xtest[$i]);
    $yPred[$i] = $pred;

    if ($pred === $ytest[$i]) $correct++;

    $a = isObesitas($ytest[$i]); // actual positif?
    $p = isObesitas($pred);      // pred positif?

    if ($a && $p) $TP++;
    elseif (!$a && !$p) $TN++;
    elseif (!$a && $p) $FP++;
    else $FN++;
}

$acc = (count($Xtest) > 0) ? (($correct / count($Xtest)) * 100) : 0;

/**
 * ============================================================
 *  7B) Classification Report (OBESITAS vs NORMAL)
 * ============================================================
 * Kita buat 2 kelas eksplisit agar tabel stabil:
 * - "OBESITAS"
 * - "NORMAL"
 */
$classes = ['OBESITAS', 'NORMAL'];

// hitung support (actual)
$support = ['OBESITAS' => 0, 'NORMAL' => 0];
for ($i=0; $i<count($ytest); $i++) {
    $actual = isObesitas($ytest[$i]) ? 'OBESITAS' : 'NORMAL';
    $support[$actual]++;
}

// hitung metrik per kelas
function safeDiv(float $a, float $b): float {
    return ($b == 0.0) ? 0.0 : ($a / $b);
}

$precision = [];
$recall = [];
$f1 = [];

foreach ($classes as $cls) {
    if ($cls === 'OBESITAS') {
        // positif
        $p = safeDiv($TP, ($TP + $FP));
        $r = safeDiv($TP, ($TP + $FN));
    } else {
        // treat NORMAL as positif (kelas negatif jadi positif)
        // TP_normal = TN
        // FP_normal = FN
        // FN_normal = FP
        $p = safeDiv($TN, ($TN + $FN));
        $r = safeDiv($TN, ($TN + $FP));
    }

    $precision[$cls] = $p;
    $recall[$cls] = $r;
    $f1[$cls] = safeDiv(2 * $p * $r, ($p + $r));
}

// accuracy (0-1 untuk report)
$acc01 = (count($ytest) > 0) ? ($correct / count($ytest)) : 0.0;

// macro avg
$macroP = ( $precision['OBESITAS'] + $precision['NORMAL'] ) / 2;
$macroR = ( $recall['OBESITAS'] + $recall['NORMAL'] ) / 2;
$macroF = ( $f1['OBESITAS'] + $f1['NORMAL'] ) / 2;

// weighted avg
$totalSupport = max(1, $support['OBESITAS'] + $support['NORMAL']);
$weightedP = ($precision['OBESITAS'] * $support['OBESITAS'] + $precision['NORMAL'] * $support['NORMAL']) / $totalSupport;
$weightedR = ($recall['OBESITAS'] * $support['OBESITAS'] + $recall['NORMAL'] * $support['NORMAL']) / $totalSupport;
$weightedF = ($f1['OBESITAS'] * $support['OBESITAS'] + $f1['NORMAL'] * $support['NORMAL']) / $totalSupport;

/**
 * ============================================================
 *  8) OUTPUT HASIL NAIVE BAYES PHP-ML
 * ============================================================
 */
preOpen();

echo line('=');
echo "              HASIL NAIVE BAYES PHP-ML" . PHP_EOL;
echo line('=');

echo row2("Total Data", (string)$datasetSize);
echo row2("Training",   (string)$trainSize . " (Pegawai 1-60)");
echo row2("Testing",    (string)$testSize  . " (Pegawai 61-80)");

if ($trainSize !== 60 || $testSize !== 20) {
    echo PHP_EOL;
    echo "Catatan: Split tidak sesuai target 60/20. Cek apakah dataset lengkap pegawai 1-80." . PHP_EOL;
}

echo line('-');
echo row2("Akurasi", number_format($acc, 2) . "%");
echo line('=');
echo PHP_EOL;
echo PHP_EOL;

echo line('=');
echo "       Confusion Matrix (Positif = OBESITAS)" . PHP_EOL;
echo line('=');
echo row2("TP", (string)$TP);
echo row2("FP", (string)$FP);
echo row2("TN", (string)$TN);
echo row2("FN", (string)$FN);
echo line('=');
echo PHP_EOL;
echo PHP_EOL;

/**
 * ============================================================
 *  9) CLASSIFICATION REPORT 
 * ============================================================
 */
echo line('=');
echo "               CLASSIFICATION REPORT" . PHP_EOL;
echo line('=');

echo "Class | Precision | Recall | F1-Score | Support" . PHP_EOL;
echo line('-');

printf("%-5s | %-9s | %-6s | %-8s | %-7s\n",
    "OBS",
    number_format($precision['OBESITAS'], 2),
    number_format($recall['OBESITAS'], 2),
    number_format($f1['OBESITAS'], 2),
    $support['OBESITAS']
);

printf("%-5s | %-9s | %-6s | %-8s | %-7s\n",
    "NOR",
    number_format($precision['NORMAL'], 2),
    number_format($recall['NORMAL'], 2),
    number_format($f1['NORMAL'], 2),
    $support['NORMAL']
);

echo line('-');
echo row2("Accuracy", number_format($acc01, 2));
echo row2("Macro Avg", number_format($macroP, 2) . " | " . number_format($macroR, 2) . " | " . number_format($macroF, 2));
echo row2("Weighted Avg", number_format($weightedP, 2) . " | " . number_format($weightedR, 2) . " | " . number_format($weightedF, 2));
echo line('=');
echo PHP_EOL;
echo PHP_EOL;

echo line('=');
echo "        Contoh Prediksi (pegawai 61-65):" . PHP_EOL;
echo line('=');

for ($i = 0; $i < min(5, count($Xtest)); $i++) {
    $pegawai = $testRows[$i][$splitColumn] ?? (61 + $i);
    $pred = $yPred[$i];
    echo "- Pegawai {$pegawai} | Asli=" . str_pad($ytest[$i], 8) . " | Pred={$pred}" . PHP_EOL;
}

echo line('=');

preClose();
