<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id'])) 
{
    header("Location: ../../frontend/login.html");
    exit();
}

$paymentId = $_GET['payment_id'] ?? null;

file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Buscando transação com payment_id: " . ($paymentId ?? 'NULL') . " e user_id: " . $_SESSION['user_id'] . "\n", FILE_APPEND);

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE payment_id = :payment_id ORDER BY created_at DESC LIMIT 1");
$stmt->execute([':payment_id' => $paymentId]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction && !$paymentId) 
{
    file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Buscando transação apenas pelo user_id: " . $_SESSION['user_id'] . "\n", FILE_APPEND);
    
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$transaction) 
{
    file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - ERRO: Nenhuma transação encontrada!\n", FILE_APPEND);
    die("Erro: Nenhuma transação encontrada para esse pagamento.");
}

file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - SUCESSO: Transação encontrada: " . json_encode($transaction) . "\n", FILE_APPEND);

if (!$transaction['payment_id'] && $paymentId) 
{
    file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Atualizando payment_id na transação\n", FILE_APPEND);
    
    $stmt = $pdo->prepare("UPDATE transactions SET payment_id = :payment_id WHERE id = :transaction_id");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':transaction_id' => $transaction['id']
    ]);
}

$status = $transaction['status'];
$valorPago = number_format($transaction['amount'], 2, ',', '.');

require __DIR__ . '/../../frontend/status_pages/payment_success.php';
?>
