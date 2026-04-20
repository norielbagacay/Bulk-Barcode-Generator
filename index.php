<?php
require_once 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Shared type rules. Keys are form values; entries drive both the PHP
 * validator and the JS UI (emitted to the page as JSON).
 *
 * - label:  human-readable name shown in the dropdown
 * - picqer: Picqer TYPE_* constant name (barcode mode only; null for QR)
 * - length: default digit length
 * - fixed:  true means the length field is locked
 * - hint:   short sentence displayed under the length field
 */
function barcode_type_rules() {
    return [
        'CODE_128'        => ['label' => 'CODE 128',         'picqer' => 'TYPE_CODE_128',        'length' => 6,  'fixed' => false, 'hint' => 'Any length. Alphanumeric allowed.'],
        'CODE_39'         => ['label' => 'CODE 39',          'picqer' => 'TYPE_CODE_39',         'length' => 6,  'fixed' => false, 'hint' => 'Any length. Uppercase letters and digits.'],
        'CODE_93'         => ['label' => 'CODE 93',          'picqer' => 'TYPE_CODE_93',         'length' => 6,  'fixed' => false, 'hint' => 'Any length. Alphanumeric.'],
        'EAN_13'          => ['label' => 'EAN-13',           'picqer' => 'TYPE_EAN_13',          'length' => 12, 'fixed' => true,  'hint' => 'Exactly 12 digits (13th is a checksum added by the library).'],
        'EAN_8'           => ['label' => 'EAN-8',            'picqer' => 'TYPE_EAN_8',           'length' => 7,  'fixed' => true,  'hint' => 'Exactly 7 digits (8th is a checksum).'],
        'UPC_A'           => ['label' => 'UPC-A',            'picqer' => 'TYPE_UPC_A',           'length' => 11, 'fixed' => true,  'hint' => 'Exactly 11 digits (12th is a checksum).'],
        'UPC_E'           => ['label' => 'UPC-E',            'picqer' => 'TYPE_UPC_E',           'length' => 6,  'fixed' => true,  'hint' => 'Exactly 6 digits.'],
        'ITF_14'          => ['label' => 'ITF-14',           'picqer' => 'TYPE_ITF14',           'length' => 13, 'fixed' => true,  'hint' => 'Exactly 13 digits (14th is a checksum).'],
        'CODABAR'         => ['label' => 'CODABAR',          'picqer' => 'TYPE_CODABAR',         'length' => 6,  'fixed' => false, 'hint' => 'Any length. Digits only.'],
        'I2OF5'           => ['label' => 'Interleaved 2 of 5','picqer' => 'TYPE_INTERLEAVED_2_5', 'length' => 6,  'fixed' => false, 'hint' => 'Even number of digits.'],
        'MSI'             => ['label' => 'MSI',              'picqer' => 'TYPE_MSI',             'length' => 6,  'fixed' => false, 'hint' => 'Any length. Digits only.'],
        'POSTNET'         => ['label' => 'POSTNET',          'picqer' => 'TYPE_POSTNET',         'length' => 5,  'fixed' => false, 'hint' => '5, 9, or 11 digits.'],
        'PHARMA'          => ['label' => 'Pharma Code',      'picqer' => 'TYPE_PHARMA_CODE',     'length' => 4,  'fixed' => false, 'hint' => '3-6 digits.'],
        'KIX'             => ['label' => 'KIX',              'picqer' => 'TYPE_KIX',             'length' => 6,  'fixed' => false, 'hint' => 'Any length. Alphanumeric.'],
        'IMB'             => ['label' => 'IMB',              'picqer' => 'TYPE_IMB',             'length' => 20, 'fixed' => true,  'hint' => 'Exactly 20 digits.'],
        'QR'              => ['label' => 'QR Code',          'picqer' => null,                    'length' => 6,  'fixed' => false, 'hint' => 'Any length or content.'],
    ];
}

class BarcodeGenerator {
    private $generator;

    public function __construct() {
        $this->generator = new BarcodeGeneratorPNG();
    }

    /**
     * @param string $data         The value encoded into the barcode.
     * @param string $prefix       The label text drawn at the left of the image.
     * @param string $typeConst    Picqer TYPE_* constant name, e.g. 'TYPE_CODE_128'.
     * @param int    $width        Bar width factor.
     * @param int    $height       Bar height in pixels.
     * @return resource|false      GD image resource on success, false on failure.
     */
    public function generateBarcode($data, $prefix, $typeConst = 'TYPE_CODE_128', $width = 2, $height = 50) {
        try {
            $type = constant(get_class($this->generator) . '::' . $typeConst);
            $barcodeData = $this->generator->getBarcode($data, $type, $width, $height);

            $prefix = strtoupper($prefix);

            $barcodeImage = imagecreatefromstring($barcodeData);
            $barcodeWidth = imagesx($barcodeImage);
            $barcodeHeight = imagesy($barcodeImage);

            $textHeight = 20;
            $padding = 10;
            $finalWidth = $barcodeWidth + (2 * $padding);
            $finalHeight = $barcodeHeight + $textHeight + (2 * $padding);

            $finalImage = imagecreate($finalWidth, $finalHeight);
            $white = imagecolorallocate($finalImage, 255, 255, 255);
            $black = imagecolorallocate($finalImage, 0, 0, 0);
            imagefill($finalImage, 0, 0, $white);
            imagecopy($finalImage, $barcodeImage, $padding, $padding, 0, 0, $barcodeWidth, $barcodeHeight);

            $fontPath = __DIR__ . "/arial.ttf";
            $fontSize = 12;
            $textY = $barcodeHeight + $padding + $fontSize + 5;

            imagettftext($finalImage, $fontSize, 0, $padding, $textY, $black, $fontPath, $prefix);

            $bboxData = imagettfbbox($fontSize, 0, $fontPath, $data);
            $textWidthData = $bboxData[2] - $bboxData[0];
            $textXData = $finalWidth - $textWidthData - $padding;
            imagettftext($finalImage, $fontSize, 0, $textXData, $textY, $black, $fontPath, $data);

            imagedestroy($barcodeImage);

            return $finalImage;
        } catch (Exception $e) {
            error_log("Barcode generation error: " . $e->getMessage());
            return false;
        }
    }

    public function saveBarcodeImage($data, $prefix, $filename, $typeConst = 'TYPE_CODE_128', $width = 2, $height = 50) {
        try {
            $barcodeImage = $this->generateBarcode($data, $prefix, $typeConst, $width, $height);
            if ($barcodeImage !== false) {
                imagepng($barcodeImage, $filename, 9);
                imagedestroy($barcodeImage);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Barcode generation error: " . $e->getMessage());
            return false;
        }
    }

    public function generateBarcodeHTML($data, $typeConst = 'TYPE_CODE_128', $width = 2, $height = 50) {
        $htmlGenerator = new BarcodeGeneratorHTML();
        $type = constant(get_class($htmlGenerator) . '::' . $typeConst);
        return $htmlGenerator->getBarcode($data, $type, $width, $height);
    }

    /**
     * Generates a QR code PNG for $content and writes it to $filename.
     * Returns true on success, false on failure.
     */
    public function saveQrImage($content, $filename) {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($content)
                ->size(300)
                ->margin(10)
                ->build();
            file_put_contents($filename, $result->getString());
            return true;
        } catch (Exception $e) {
            error_log("QR generation error: " . $e->getMessage());
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rules    = barcode_type_rules();
    $mode     = (isset($_POST['mode']) && $_POST['mode'] === 'qr') ? 'qr' : 'barcode';
    $typeKey  = $mode === 'qr' ? 'QR' : (isset($_POST['type']) ? $_POST['type'] : 'CODE_128');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $prefix   = isset($_POST['prefix']) ? $_POST['prefix'] : 'LG';
    $length   = (int)($_POST['length'] ?? 6);
    $startRaw = isset($_POST['start_number']) ? trim($_POST['start_number']) : '';

    $errors = [];

    if (!isset($rules[$typeKey]) || ($mode === 'barcode' && $typeKey === 'QR')) {
        $errors[] = "Unknown barcode type '$typeKey'.";
    }
    if ($quantity < 1 || $quantity > 1000) {
        $errors[] = "Quantity must be between 1 and 1000.";
    }
    if ($length < 1 || $length > 30) {
        $errors[] = "Length must be between 1 and 30 digits.";
    }
    if (empty($errors) && $rules[$typeKey]['fixed'] && $length !== $rules[$typeKey]['length']) {
        $errors[] = $rules[$typeKey]['label'] . " requires exactly " . $rules[$typeKey]['length'] . " digits.";
    }

    $useSequential = $startRaw !== '' && ctype_digit($startRaw) && (int)$startRaw > 0;
    $start = $useSequential ? (int)$startRaw : 0;

    if (empty($errors) && $useSequential) {
        $maxValue = (int)str_repeat('9', $length);
        if ($start + $quantity - 1 > $maxValue) {
            $errors[] = "Starting number + quantity overflows $length-digit range (max $maxValue).";
        }
    }

    if (!empty($errors)) {
        $success = false;
        $message = implode(' ', $errors);
    } else {
        $generator = new BarcodeGenerator();

        if (!file_exists('barcodes')) {
            mkdir('barcodes', 0777, true);
        }

        if ($useSequential) {
            $numbers = [];
            for ($i = 0; $i < $quantity; $i++) {
                $numbers[] = $start + $i;
            }
        } else {
            $maxValue = (int)str_repeat('9', $length);
            $minValue = (int)('1' . str_repeat('0', max(0, $length - 1)));
            $pool = range($minValue, min($maxValue, $minValue + 10 * max($quantity, 100)));
            shuffle($pool);
            $numbers = array_slice($pool, 0, $quantity);
        }

        $generatedBarcodes = [];
        $filePrefix = $mode === 'qr' ? 'qr' : 'barcode';

        foreach ($numbers as $n) {
            $barcodeNumber = str_pad((string)$n, $length, '0', STR_PAD_LEFT);
            $paddedPrefix  = str_pad($prefix, 2, '0', STR_PAD_LEFT);
            $filename      = "barcodes/{$filePrefix}_" . $barcodeNumber . ".png";

            $ok = false;
            if ($mode === 'qr') {
                $ok = $generator->saveQrImage($prefix . $barcodeNumber, $filename);
            } else {
                $ok = $generator->saveBarcodeImage($barcodeNumber, $paddedPrefix, $filename, $rules[$typeKey]['picqer'], 2, 50);
            }

            if ($ok) {
                $generatedBarcodes[] = [
                    'number'   => $barcodeNumber,
                    'filename' => $filename,
                ];
            }
        }

        $zipFile = null;
        if (!empty($generatedBarcodes) && class_exists('ZipArchive')) {
            $zipBase = $mode === 'qr' ? 'qrcodes' : 'barcodes';
            $zipName = $zipBase . '-' . date('Ymd-His') . '.zip';
            $zipPath = 'barcodes/' . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($generatedBarcodes as $b) {
                    if (file_exists($b['filename'])) {
                        $zip->addFile($b['filename'], basename($b['filename']));
                    }
                }
                $zip->close();
                $zipFile = $zipPath;
            }
        }

        $success = !empty($generatedBarcodes);
        $message = $success
            ? "Successfully generated " . count($generatedBarcodes) . " " . ($mode === 'qr' ? 'QR codes' : 'barcodes') . "."
            : "No items were generated.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #fafafa;
            --surface: #ffffff;
            --surface-2: #f5f5f7;
            --border: #e5e5ea;
            --border-strong: #d4d4d8;
            --text: #0a0a0a;
            --text-muted: #71717a;
            --text-subtle: #a1a1aa;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: #eef2ff;
            --success: #059669;
            --success-soft: #ecfdf5;
            --danger: #dc2626;
            --danger-soft: #fef2f2;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-feature-settings: 'cv11', 'ss01', 'ss03';
            -webkit-font-smoothing: antialiased;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }

        .page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 40px;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--text);
            display: grid;
            place-items: center;
            color: white;
        }

        .brand-mark svg {
            width: 22px;
            height: 22px;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .brand-text p {
            margin: 2px 0 0;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.0625rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .card-header p {
            margin: 4px 0 0;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .card-body {
            padding: 28px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .mode-toggle {
            display: inline-flex;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--surface-2);
        }

        .mode-option {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .mode-option input { display: none; }
        .mode-option:has(input:checked) { background: var(--text); color: white; }

        select#type {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9375rem;
            color: var(--text);
            background: var(--surface);
        }

        select#type:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        input[disabled], input[readonly] { background: var(--surface-2); color: var(--text-muted); cursor: not-allowed; }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text);
            letter-spacing: -0.005em;
        }

        .field .hint {
            font-size: 0.75rem;
            color: var(--text-subtle);
        }

        input[type="number"], input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9375rem;
            color: var(--text);
            background: var(--surface);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input[type="number"]:hover, input[type="text"]:hover {
            border-color: var(--text-subtle);
        }

        input[type="number"]:focus, input[type="text"]:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background: var(--text);
            color: white;
        }

        .btn-primary:hover {
            background: #27272a;
        }

        .btn-ghost {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border-strong);
        }

        .btn-ghost:hover {
            background: var(--surface-2);
            border-color: var(--text-subtle);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 8px;
            border-top: 1px solid var(--border);
            margin-top: 20px;
            padding-top: 20px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            margin: 24px 0 0;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            border: 1px solid transparent;
        }

        .alert svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }

        .alert-success {
            background: var(--success-soft);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: var(--danger-soft);
            color: #991b1b;
            border-color: #fecaca;
        }

        .results {
            margin-top: 40px;
        }

        .results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .results-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .results-header h2 {
            margin: 0;
            font-size: 1.0625rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .results-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .results-count-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success);
        }

        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .barcode-item {
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .barcode-item:hover {
            border-color: var(--border-strong);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        .barcode-preview {
            padding: 24px 20px;
            background: var(--surface-2);
            display: grid;
            place-items: center;
            border-bottom: 1px solid var(--border);
            min-height: 120px;
        }

        .barcode-preview img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .barcode-meta {
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .barcode-number {
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text);
            letter-spacing: 0.02em;
        }

        .download-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .download-link:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: #c7d2fe;
        }

        .download-link svg {
            width: 12px;
            height: 12px;
        }

        .install-banner {
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 340px;
            padding: 14px 16px;
            background: var(--surface);
            border: 1px solid var(--danger);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            font-size: 0.8125rem;
            color: var(--text);
        }

        .install-banner strong {
            display: block;
            color: var(--danger);
            margin-bottom: 6px;
        }

        .install-banner code {
            display: block;
            margin-top: 8px;
            padding: 8px 10px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.75rem;
            color: var(--text);
        }

        @media (max-width: 720px) {
            .page { padding: 24px 16px 48px; }
            .form-grid { grid-template-columns: 1fr; }
            .card-body { padding: 20px; }
            .card-header { padding: 20px; }
            .form-actions { flex-direction: column-reverse; }
            .form-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="header">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14"/>
                </svg>
            </div>
            <div class="brand-text">
                <h1>Barcode Generator</h1>
                <p>Generate Barcodes/QR in bulk</p>
            </div>
        </header>

        <section class="card" aria-labelledby="form-title">
            <div class="card-header">
                <h2 id="form-title">New batch</h2>
                <p>Configure your barcode batch and generate PNG files instantly.</p>
            </div>
            <div class="card-body">
<form method="POST">
    <div class="field" style="margin-bottom: 16px;">
        <label>Mode</label>
        <div class="mode-toggle" role="radiogroup" aria-label="Mode">
            <?php $mode = isset($_POST['mode']) ? $_POST['mode'] : 'barcode'; ?>
            <label class="mode-option">
                <input type="radio" name="mode" value="barcode" <?php echo $mode === 'barcode' ? 'checked' : ''; ?>>
                <span>Barcode</span>
            </label>
            <label class="mode-option">
                <input type="radio" name="mode" value="qr" <?php echo $mode === 'qr' ? 'checked' : ''; ?>>
                <span>QR Code</span>
            </label>
        </div>
    </div>

    <div class="field" id="type-field" style="margin-bottom: 16px;">
        <label for="type">Barcode type</label>
        <select id="type" name="type">
            <?php
            $selectedType = isset($_POST['type']) ? $_POST['type'] : 'CODE_128';
            foreach (barcode_type_rules() as $key => $rule):
                if ($key === 'QR') continue;
            ?>
                <option value="<?php echo $key; ?>" <?php echo $selectedType === $key ? 'selected' : ''; ?>>
                    <?php echo $rule['label']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="hint" id="type-hint">Any length. Alphanumeric allowed.</span>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" max="1000"
                   value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : 10; ?>" required>
            <span class="hint">Between 1 and 1000</span>
        </div>

        <div class="field">
            <label for="prefix">Prefix</label>
            <input type="text" id="prefix" name="prefix" maxlength="10"
                   value="<?php echo isset($_POST['prefix']) ? $_POST['prefix'] : 'LG'; ?>" required>
            <span class="hint">Label / content prefix</span>
        </div>

        <div class="field">
            <label for="start_number">Starting number</label>
            <input type="number" id="start_number" name="start_number" min="1"
                   value="<?php echo isset($_POST['start_number']) ? $_POST['start_number'] : ''; ?>"
                   placeholder="empty = random">
            <span class="hint">Empty → random. Filled → sequential.</span>
        </div>

        <div class="field">
            <label for="length">Number length</label>
            <input type="number" id="length" name="length" min="1" max="30"
                   value="<?php echo isset($_POST['length']) ? $_POST['length'] : 6; ?>">
            <span class="hint" id="length-hint">Digits for zero-padding.</span>
        </div>
    </div>

    <div class="form-actions">
        <button type="reset" class="btn btn-ghost">Reset</button>
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M13 5l7 7-7 7"/>
            </svg>
            Generate
        </button>
    </div>
</form>

                <?php if (isset($success)): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                        <?php if ($success): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                            </svg>
                        <?php endif; ?>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (isset($success) && $success && !empty($generatedBarcodes)): ?>
            <section class="results" aria-labelledby="results-title">
                <div class="results-header">
                    <div class="results-header-left">
                        <h2 id="results-title">Generated <?php echo $mode === 'qr' ? 'QR codes' : 'barcodes'; ?></h2>
                        <span class="results-count">
                            <span class="results-count-dot"></span>
                            <?php echo count($generatedBarcodes); ?> created
                        </span>
                    </div>
                    <?php if (!empty($zipFile)): ?>
                        <a href="<?php echo $zipFile; ?>" download class="btn btn-primary btn-sm">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                            </svg>
                            Download all (ZIP)
                        </a>
                    <?php endif; ?>
                </div>
                <div class="barcode-grid">
                    <?php foreach ($generatedBarcodes as $barcode): ?>
                        <div class="barcode-item">
                            <div class="barcode-preview">
                                <img src="<?php echo $barcode['filename']; ?>"
                                     alt="<?php echo $mode === 'qr' ? 'QR code' : 'Barcode'; ?> <?php echo $barcode['number']; ?>">
                            </div>
                            <div class="barcode-meta">
                                <span class="barcode-number"><?php echo $barcode['number']; ?></span>
                                <a href="<?php echo $barcode['filename']; ?>"
                                   download="barcode_<?php echo str_replace('-', '_', $barcode['number']); ?>.png"
                                   class="download-link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                    </svg>
                                    PNG
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php if (!class_exists('Picqer\Barcode\BarcodeGeneratorPNG')): ?>
    <div class="install-banner">
        <strong>Installation required</strong>
        Picqer barcode library isn't installed. Run:
        <code>composer require picqer/php-barcode-generator</code>
    </div>
    <?php endif; ?>
<script>
const TYPE_RULES = <?php echo json_encode(barcode_type_rules()); ?>;

const modeRadios = document.querySelectorAll('input[name="mode"]');
const typeField = document.getElementById('type-field');
const typeSelect = document.getElementById('type');
const typeHint = document.getElementById('type-hint');
const lengthInput = document.getElementById('length');
const lengthHint = document.getElementById('length-hint');

function applyRule(ruleKey) {
    const rule = TYPE_RULES[ruleKey];
    if (!rule) return;
    lengthInput.value = rule.length;
    lengthInput.readOnly = !!rule.fixed;
    lengthHint.textContent = rule.hint;
    if (typeHint) typeHint.textContent = rule.hint;
}

function syncUI() {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    if (mode === 'qr') {
        typeField.style.display = 'none';
        applyRule('QR');
    } else {
        typeField.style.display = '';
        applyRule(typeSelect.value);
    }
}

modeRadios.forEach(r => r.addEventListener('change', syncUI));
typeSelect.addEventListener('change', () => applyRule(typeSelect.value));

syncUI();
</script>
</body>
</html>