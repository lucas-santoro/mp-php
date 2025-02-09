<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.html");
    exit();
}

$paymentId = $_GET['payment_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE payment_id = :payment_id AND user_id = :user_id");
$stmt->execute([
    ':payment_id' => $paymentId,
    ':user_id' => $_SESSION['user_id']
]);

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("Erro: Nenhuma transação encontrada para esse pagamento.");
}

$status = $transaction['status'];
$valorPago = number_format($transaction['amount'], 2, ',', '.');

require 'payment_status.php';
?>
