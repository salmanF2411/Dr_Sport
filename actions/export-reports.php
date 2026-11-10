<?php
declare(strict_types=1);

/**
 * DR.SPORT — Export Laporan ke Excel (.xlsx)
 * Menghasilkan file Excel berformat rapi untuk Laporan Pesanan dan Laporan Inventory.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Memerlukan role Admin atau Super Admin
$user = require_role(['super_admin', 'admin']);

// Autoload composer untuk PhpSpreadsheet
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Parameter filter
$type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
$start = (string) ($_GET['start'] ?? date('Y-m-01'));
$end = (string) ($_GET['end'] ?? date('Y-m-d'));

if (!valid_date($start)) {
    $start = date('Y-m-01');
}
if (!valid_date($end)) {
    $end = date('Y-m-d');
}
if ($end < $start) {
    $end = $start;
}

$orderStatus = (string) ($_GET['status'] ?? '');
$orderSearch = substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$db = db();

// ==========================================
// 1. DATA LAPORAN PESANAN
// ==========================================
$orders = [];
if ($type === 'orders' || $type === 'all') {
    $orderWhere = ['o.order_date BETWEEN ? AND ?'];
    $orderParams = [$start, $end];
    $allowedStatuses = ['Pesanan Masuk', 'Desain', 'Printing', 'Penjahitan', 'Finishing / QC', 'Selesai', 'Dibatalkan'];

    if (in_array($orderStatus, $allowedStatuses, true)) {
        $orderWhere[] = 'o.status = ?';
        $orderParams[] = $orderStatus;
    }
    if ($orderSearch !== '') {
        $orderWhere[] = '(o.order_code LIKE ? OR c.name LIKE ?)';
        $orderParams[] = '%' . $orderSearch . '%';
        $orderParams[] = '%' . $orderSearch . '%';
    }

    $ordersStmt = $db->prepare('
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone 
        FROM orders o 
        JOIN customers c ON c.id = o.customer_id 
        WHERE ' . implode(' AND ', $orderWhere) . ' 
        ORDER BY o.order_date DESC 
        LIMIT 1000
    ');
    $ordersStmt->execute($orderParams);
    $orders = $ordersStmt->fetchAll();
}

// ==========================================
// 2. DATA LAPORAN INVENTORY
// ==========================================
$inventory = [];
if ($type === 'inventory' || $type === 'all') {
    $inventoryStmt = $db->prepare("
        SELECT m.id, m.material_code, m.name, m.category, m.unit, m.location, m.current_stock, m.minimum_stock,
        COALESCE((SELECT t.stock_before FROM inventory_transactions t WHERE t.material_id=m.id AND t.created_at < ? ORDER BY t.created_at DESC, t.id DESC LIMIT 1), 0) AS opening_stock,
        COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('in','adjustment_in','return') AND DATE(t.created_at) BETWEEN ? AND ?), 0) AS total_in,
        COALESCE((SELECT SUM(t.quantity) FROM inventory_transactions t WHERE t.material_id=m.id AND t.transaction_type IN ('out','adjustment_out') AND DATE(t.created_at) BETWEEN ? AND ?), 0) AS total_out
        FROM materials m 
        ORDER BY m.name ASC
    ");
    $inventoryStmt->execute([$start, $start, $end, $start, $end]);
    $inventory = $inventoryStmt->fetchAll();
}

// ==========================================
// 3. JIKA PHPSPREADSHEET TERSEDIA (.XLSX ASLI)
// ==========================================
if (class_exists(Spreadsheet::class)) {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('DR.SPORT Operations Console')
        ->setLastModifiedBy($user['name'])
        ->setTitle('Laporan DR.SPORT')
        ->setSubject('Laporan Operasional ' . strtoupper($type))
        ->setDescription('Diekspor dari Sistem Informasi DR.SPORT');

    $sheetIndex = 0;

    // Helper styling
    $headerFill = [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2457A7'],
    ];
    $headerFont = [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 10,
    ];
    $titleFont = [
        'bold' => true,
        'size' => 15,
        'color' => ['rgb' => '172033'],
    ];
    $subTitleFont = [
        'size' => 10,
        'color' => ['rgb' => '667085'],
    ];
    $borderThin = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DCE2EA'],
            ],
        ],
    ];
    $totalRowStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F1F5F9'],
        ],
        'borders' => [
            'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
            'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E293B']],
        ],
    ];

    // --- TABEL PESANAN ---
    if ($type === 'orders' || $type === 'all') {
        $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle('Laporan Pesanan');
        $sheetIndex++;

        // Judul Laporan
        $sheet->setCellValue('A1', 'DR.SPORT — LAPORAN PESANAN JERSEY');
        $sheet->getStyle('A1')->applyFromArray(['font' => $titleFont]);

        $filterInfo = 'Periode: ' . date('d/m/Y', strtotime($start)) . ' s/d ' . date('d/m/Y', strtotime($end));
        if ($orderStatus !== '') {
            $filterInfo .= ' | Status: ' . $orderStatus;
        }
        if ($orderSearch !== '') {
            $filterInfo .= ' | Kata Kunci: "' . $orderSearch . '"';
        }
        $filterInfo .= ' | Dicetak pada: ' . date('d/m/Y H:i') . ' oleh ' . $user['name'];
        $sheet->setCellValue('A2', $filterInfo);
        $sheet->getStyle('A2')->applyFromArray(['font' => $subTitleFont]);

        // Header Kolom (Baris 4)
        $headers = [
            'A4' => 'No',
            'B4' => 'Kode Pesanan',
            'C4' => 'Tanggal Pesan',
            'D4' => 'Target Selesai',
            'E4' => 'Nama Pelanggan',
            'F4' => 'Kontak / Telp',
            'G4' => 'Deskripsi Jersey',
            'H4' => 'Jumlah (Pcs)',
            'I4' => 'Status Pengerjaan',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A4:I4')->applyFromArray([
            'font' => $headerFont,
            'fill' => $headerFill,
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(26);

        // Data Rows
        $row = 5;
        $no = 1;
        $totalPcs = 0;

        foreach ($orders as $o) {
            $pcs = (int) $o['quantity'];
            $totalPcs += $pcs;

            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, $o['order_code']);
            $sheet->setCellValue('C' . $row, date('d/m/Y', strtotime($o['order_date'])));
            $sheet->setCellValue('D' . $row, $o['target_date'] ? date('d/m/Y', strtotime($o['target_date'])) : '—');
            $sheet->setCellValue('E' . $row, $o['customer_name']);
            $sheet->setCellValue('F' . $row, $o['customer_phone'] ?? '—');
            $sheet->setCellValue('G' . $row, $o['jersey_description']);
            $sheet->setCellValue('H' . $row, $pcs);
            $sheet->setCellValue('I' . $row, $o['status']);

            // Alignments
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number format pcs
            $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');

            // Zebra shading
            if ($no % 2 === 0) {
                $sheet->getStyle('A' . $row . ':I' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }

            $row++;
            $no++;
        }

        $endRow = max(5, $row - 1);
        $sheet->getStyle('A4:I' . $endRow)->applyFromArray($borderThin);

        // Baris Total
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('H' . $row, $totalPcs);
        $sheet->setCellValue('I' . $row, ($no - 1) . ' Pesanan');

        $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(22);

        // Auto-width
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // --- TABEL INVENTORY ---
    if ($type === 'inventory' || $type === 'all') {
        $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle('Laporan Inventory');
        $sheetIndex++;

        // Judul Laporan
        $sheet->setCellValue('A1', 'DR.SPORT — LAPORAN INVENTORY BAHAN BAKU');
        $sheet->getStyle('A1')->applyFromArray(['font' => $titleFont]);

        $filterInfo = 'Periode Mutasi: ' . date('d/m/Y', strtotime($start)) . ' s/d ' . date('d/m/Y', strtotime($end)) .
            ' | Dicetak pada: ' . date('d/m/Y H:i') . ' oleh ' . $user['name'];
        $sheet->setCellValue('A2', $filterInfo);
        $sheet->getStyle('A2')->applyFromArray(['font' => $subTitleFont]);

        // Header Kolom (Baris 4)
        $headers = [
            'A4' => 'No',
            'B4' => 'Kode Bahan',
            'C4' => 'Nama Bahan Baku',
            'D4' => 'Kategori',
            'E4' => 'Satuan',
            'F4' => 'Saldo Awal',
            'G4' => 'Total Masuk (+)',
            'H4' => 'Total Keluar (-)',
            'I4' => 'Saldo Akhir Periode',
            'J4' => 'Saldo Terkini',
            'K4' => 'Minimum Stok',
            'L4' => 'Status Stok',
            'M4' => 'Lokasi Rak',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A4:M4')->applyFromArray([
            'font' => $headerFont,
            'fill' => $headerFill,
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(26);

        // Data Rows
        $row = 5;
        $no = 1;

        foreach ($inventory as $item) {
            $opening = (float) $item['opening_stock'];
            $in = (float) $item['total_in'];
            $out = (float) $item['total_out'];
            $closing = $opening + $in - $out;
            $current = (float) $item['current_stock'];
            $min = (float) $item['minimum_stock'];

            $statusLabel = 'Tersedia';
            if ($current <= 0) {
                $statusLabel = 'Habis';
            } elseif ($current <= $min) {
                $statusLabel = 'Menipis';
            }

            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, $item['material_code']);
            $sheet->setCellValue('C' . $row, $item['name']);
            $sheet->setCellValue('D' . $row, category_label($item['category'] ?? ''));
            $sheet->setCellValue('E' . $row, $item['unit']);
            $sheet->setCellValue('F' . $row, $opening);
            $sheet->setCellValue('G' . $row, $in);
            $sheet->setCellValue('H' . $row, $out);
            $sheet->setCellValue('I' . $row, $closing);
            $sheet->setCellValue('J' . $row, $current);
            $sheet->setCellValue('K' . $row, $min);
            $sheet->setCellValue('L' . $row, $statusLabel);
            $sheet->setCellValue('M' . $row, $item['location'] ?: '—');

            // Alignments
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('M' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Numerics right align & formatting
            foreach (['F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0.###');
            }

            // Warna hijau untuk masuk, merah untuk keluar
            if ($in > 0) {
                $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('15803D');
            }
            if ($out > 0) {
                $sheet->getStyle('H' . $row)->getFont()->getColor()->setRGB('B91C1C');
            }

            // Zebra shading
            if ($no % 2 === 0) {
                $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }

            $row++;
            $no++;
        }

        $endRow = max(5, $row - 1);
        $sheet->getStyle('A4:M' . $endRow)->applyFromArray($borderThin);

        // Baris Total
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->setCellValue('F' . $row, '=SUM(F5:F' . $endRow . ')');
        $sheet->setCellValue('G' . $row, '=SUM(G5:G' . $endRow . ')');
        $sheet->setCellValue('H' . $row, '=SUM(H5:H' . $endRow . ')');
        $sheet->setCellValue('I' . $row, '=SUM(I5:I' . $endRow . ')');
        $sheet->setCellValue('J' . $row, '=SUM(J5:J' . $endRow . ')');
        $sheet->setCellValue('K' . $row, '=SUM(K5:K' . $endRow . ')');
        $sheet->setCellValue('L' . $row, ($no - 1) . ' Bahan');
        $sheet->setCellValue('M' . $row, '');

        $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (['F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0.###');
        }
        $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(22);

        // Auto-width
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Set first sheet active
    $spreadsheet->setActiveSheetIndex(0);

    // Download headers
    $cleanType = ($type === 'orders' ? 'Pesanan' : ($type === 'inventory' ? 'Inventory' : 'Semua'));
    $filename = 'Laporan_' . $cleanType . '_DRSPORT_' . date('Ymd', strtotime($start)) . '_' . date('Ymd', strtotime($end)) . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ==========================================
// 4. FALLBACK EXCEL XML (.XLS) JIKA VENDOR ABSENT
// ==========================================
$cleanType = ($type === 'orders' ? 'Pesanan' : ($type === 'inventory' ? 'Inventory' : 'Semua'));
$filename = 'Laporan_' . $cleanType . '_DRSPORT_' . date('Ymd', strtotime($start)) . '_' . date('Ymd', strtotime($end)) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Calibri, sans-serif; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
    th { background-color: #2457A7; color: #FFFFFF; font-weight: bold; border: 1px solid #94A3B8; padding: 8px 10px; }
    td { border: 1px solid #DCE2EA; padding: 6px 10px; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .title { font-size: 16pt; font-weight: bold; color: #172033; }
    .subtitle { font-size: 10pt; color: #64748B; margin-bottom: 12px; }
    .bg-total { background-color: #F1F5F9; font-weight: bold; }
</style>
</head>
<body>
    <div class="title">DR.SPORT — LAPORAN <?= strtoupper($cleanType) ?></div>
    <div class="subtitle">Periode: <?= e($start) ?> s/d <?= e($end) ?> | Dicetak pada: <?= date('d/m/Y H:i') ?> oleh <?= e($user['name']) ?></div>

    <?php if ($type === 'orders' || $type === 'all'): ?>
    <h3>Laporan Pesanan Jersey</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Pesanan</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Telepon</th>
                <th>Ringkasan Jersey</th>
                <th>Jumlah (Pcs)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $sumPcs = 0;
            foreach ($orders as $o): 
                $sumPcs += (int)$o['quantity'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= e($o['order_code']) ?></td>
                <td class="text-center"><?= e($o['order_date']) ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= e($o['customer_phone'] ?? '—') ?></td>
                <td><?= e($o['jersey_description']) ?></td>
                <td class="text-right"><?= number_format((int)$o['quantity']) ?></td>
                <td class="text-center"><?= e($o['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="bg-total">
                <td colspan="6" class="text-right">TOTAL</td>
                <td class="text-right"><?= number_format($sumPcs) ?></td>
                <td class="text-center"><?= count($orders) ?> Pesanan</td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($type === 'inventory' || $type === 'all'): ?>
    <h3>Laporan Inventory Bahan Baku</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Bahan</th>
                <th>Nama Bahan Baku</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th>Saldo Awal</th>
                <th>Stok Masuk</th>
                <th>Stok Keluar</th>
                <th>Saldo Akhir</th>
                <th>Saldo Terkini</th>
                <th>Minimum</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($inventory as $i): 
                $closing = (float)$i['opening_stock'] + (float)$i['total_in'] - (float)$i['total_out'];
                $cur = (float)$i['current_stock'];
                $min = (float)$i['minimum_stock'];
                $st = ($cur <= 0 ? 'Habis' : ($cur <= $min ? 'Menipis' : 'Tersedia'));
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= e($i['material_code']) ?></td>
                <td><?= e($i['name']) ?></td>
                <td class="text-center"><?= e(category_label($i['category'] ?? '')) ?></td>
                <td class="text-center"><?= e($i['unit']) ?></td>
                <td class="text-right"><?= e(format_stock($i['opening_stock'])) ?></td>
                <td class="text-right"><?= e(format_stock($i['total_in'])) ?></td>
                <td class="text-right"><?= e(format_stock($i['total_out'])) ?></td>
                <td class="text-right"><strong><?= e(format_stock($closing)) ?></strong></td>
                <td class="text-right"><?= e(format_stock($i['current_stock'])) ?></td>
                <td class="text-right"><?= e(format_stock($i['minimum_stock'])) ?></td>
                <td class="text-center"><?= e($st) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
