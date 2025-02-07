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

if (!isset($data['topic']) || $data['topic'] !== 'payment') {
    file_put_contents(__DIR__ . '/../../logs/webhook.log', date('Y-m-d H:i:s') . " - IGNORADO: Notificação não é de pagamento. Dados: " . json_encode($data) . "\n", FILE_APPEND);
    http_response_code(200);
    exit("Notificação ignorada.");
}

$paymentId = null;
if (isset($data['data']['id'])) {
    $paymentId = $data['data']['id'];
} elseif (isset($data['resource'])) {
    if (is_numeric($data['resource'])) {
        $paymentId = $data['resource'];
    } else {
        preg_match('/(\d+)$/', $data['resource'], $matches);
        if (!empty($matches[1])) {
            $paymentId = $matches[1];
        }
    }
}

if (!$paymentId) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: ID do pagamento ausente.\n", FILE_APPEND);
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

$userId = $payment->external_reference;
$valorPago = $payment->transaction_amount;
$status = $payment->status;

$stmt = $pdo->prepare("SELECT id, amount, status FROM transactions WHERE payment_id = :payment_id OR (user_id = :user_id AND status = 'pending') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([
    ':payment_id' => $paymentId,
    ':user_id' => $userId
]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Nenhuma transação encontrada para payment_id $paymentId.\n", FILE_APPEND);
    http_response_code(400);
    exit("Transação não encontrada.");
}

if ($transaction['status'] === 'approved') {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - IGNORADO: Pagamento $paymentId já processado.\n", FILE_APPEND);
    http_response_code(200);
    exit("Pagamento já processado.");
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
