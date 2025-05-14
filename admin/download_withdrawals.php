<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Vérification d'authentification admin
//check_admin();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="withdrawals.xlsx"');
header('Cache-Control: max-age=0');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = Database::getInstance();
$pdo = $db->getConnection();

$stmt = $pdo->query("
    SELECT w.id, w.amount, w.method, w.wallet_address, w.phone, w.network, w.status, w.created_at, u.username 
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    ORDER BY w.created_at DESC
");

$withdrawals = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Utilisateur');
$sheet->setCellValue('C1', 'Montant');
$sheet->setCellValue('D1', 'Méthode');
$sheet->setCellValue('E1', 'Détails');
$sheet->setCellValue('F1', 'Réseau');
$sheet->setCellValue('G1', 'Statut');
$sheet->setCellValue('H1', 'Date');

$row = 2;
foreach ($withdrawals as $withdrawal) {
    $details = $withdrawal['method'] === 'usdt' ? ($withdrawal['wallet_address'] ?? 'Non spécifié') : ($withdrawal['phone'] ?? 'Non spécifié');
    $sheet->setCellValue('A' . $row, $withdrawal['id']);
    $sheet->setCellValue('B' . $row, $withdrawal['username']);
    $sheet->setCellValue('C' . $row, number_format($withdrawal['amount'], 2) . ' USD');
    $sheet->setCellValue('D' . $row, $withdrawal['method'] === 'usdt' ? 'USDT' : 'Mobile Money');
    $sheet->setCellValue('E' . $row, $details);
    $sheet->setCellValue('F' . $row, $withdrawal['network'] ?? 'N/A');
    $sheet->setCellValue('G' . $row, ucfirst($withdrawal['status']));
    $sheet->setCellValue('H' . $row, date('d/m/Y H:i', strtotime($withdrawal['created_at'])));
    $row++;
}

foreach (range('A', 'H') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();