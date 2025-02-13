<?php
require_once '../db.php';
require_once '../../vendor/autoload.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);

$paymentClient = new PaymentClient();

$input = file_get_contents("php://input");
$data = json_decode($input, true);

file_put_contents(__DIR__ . '/../../logs/webhook.log', date('Y-m-d H:i:s') . " - Recebido: " . json_encode($data) . "\n", FILE_APPEND);

if (!isset($data['topic']) || strtolower($data['topic']) !== 'payment') {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - IGNORADO: Notificação não é de pagamento. Dados: " . json_encode($data) . "\n", FILE_APPEND);
    http_response_code(200);
    exit("Notificação ignorada.");
}

$paymentId = $data['data']['id'] ?? $data['resource'] ?? null;

if (!$paymentId) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: ID do pagamento ausente. Dados recebidos: " . json_encode($data) . "\n", FILE_APPEND);
    http_response_code(400);
    exit("Erro: ID do pagamento não encontrado.");
}

try {
    $payment = $paymentClient->get($paymentId);
    if (!$payment) {
        file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - AVISO: API do Mercado Pago não retornou dados para payment_id $paymentId.\n", FILE_APPEND);
        http_response_code(200);
        exit("Nenhuma informação encontrada para esse pagamento.");
    }
} catch (\Exception $e) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO API: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(200);
    exit("Erro ao processar webhook: " . $e->getMessage());
}

$userId = (string) $payment->external_reference;
$valorPago = $payment->transaction_amount;
$status = $payment->status;

$stmt = $pdo->prepare("SELECT id, amount, status, user_id FROM transactions WHERE (payment_id = :payment_id OR (user_id = :user_id AND status = 'pending')) ORDER BY created_at DESC LIMIT 1");
$stmt->execute([
    ':payment_id' => $paymentId,
    ':user_id' => $userId
]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Nenhuma transação encontrada para payment_id $paymentId e userId $userId.\n", FILE_APPEND);
    http_response_code(400);
    exit("Transação não encontrada.");
}

if ((string) $userId !== (string) $transaction['user_id']) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Tentativa de fraude! userId $userId não corresponde à transação no banco (esperado: " . $transaction['user_id'] . ").\n", FILE_APPEND);
    http_response_code(400);
    exit("Erro: ID do usuário não corresponde.");
}

if ($status === 'approved') {
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved', payment_id = :payment_id WHERE id = :transaction_id");
    $stmt->execute([':payment_id' => $paymentId, ':transaction_id' => $transaction['id']]);

    $stmt = $pdo->prepare("UPDATE users SET credits = credits + :valorPago WHERE id = :user_id");
    $stmt->execute([':valorPago' => $valorPago, ':user_id' => $userId]);
} elseif ($status === 'pending') {
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'pending' WHERE id = :transaction_id");
    $stmt->execute([':transaction_id' => $transaction['id']]);
} elseif ($status === 'rejected') {
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed', payment_id = NULL WHERE id = :transaction_id");
    $stmt->execute([':transaction_id' => $transaction['id']]);
}

http_response_code(200);
exit("Webhook processado com sucesso.");
