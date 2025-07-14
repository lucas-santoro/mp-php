<?php
require_once '../db.php';
require_once '../../vendor/autoload.php';
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
$paymentClient = new PaymentClient();

session_start();

if (!isset($_SESSION['user_id'])) 
{
    header("Location: ../../frontend/login.html");
    exit();
}

$paymentId = $_GET['payment_id'] ?? null;
$preferenceId = $_GET['preference_id'] ?? null;

file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Buscando transação com payment_id: " . ($paymentId ?? 'NULL') . " e preference_id: " . ($preferenceId ?? 'NULL') . " e user_id: " . $_SESSION['user_id'] . "\n", FILE_APPEND);

// 1. Buscar transação pelo payment_id se ele existir
if ($paymentId) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE payment_id = :payment_id ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':payment_id' => $paymentId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Se não encontrar pelo payment_id, tentar pelo preference_id
if (!$transaction && $preferenceId) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE preference_id = :preference_id ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':preference_id' => $preferenceId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Se ainda não encontrou, buscar transação aprovada mais recente para o usuário
if (!$transaction) {
    file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Buscando transação apenas pelo user_id: " . $_SESSION['user_id'] . "\n", FILE_APPEND);
    
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. Se ainda não encontrou nada, erro
if (!$transaction) {
    file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - ERRO: Nenhuma transação encontrada!\n", FILE_APPEND);
    die("Erro: Nenhuma transação encontrada para esse pagamento.");
}

file_put_contents(__DIR__ . '/../../logs/success_debug.log', date('Y-m-d H:i:s') . " - SUCESSO: Transação encontrada: " . json_encode($transaction) . "\n", FILE_APPEND);

// 5. Atualiza o payment_id se estiver ausente
if (!$transaction['payment_id'] && $paymentId) {
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
