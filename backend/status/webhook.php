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

// Verifica se é uma notificação de pagamento
if (!isset($data['topic']) || strtolower($data['topic']) !== 'payment') {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - IGNORADO: Notificação não é de pagamento.\n", FILE_APPEND);
    http_response_code(200);
    exit("Notificação ignorada.");
}

// Obtém o payment_id
$paymentId = $data['resource'] ?? null;
file_put_contents(__DIR__ . '/../../logs/webhook_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Payment ID capturado corretamente: " . ($paymentId ?? 'NULL') . "\n", FILE_APPEND);


if (!$paymentId) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Payment ID ausente.\n", FILE_APPEND);
    http_response_code(400);
    exit("Erro: Payment ID ausente.");
}

file_put_contents(__DIR__ . '/../../logs/webhook_debug.log', "Buscando pagamento ID: $paymentId\n", FILE_APPEND);

// Obtém detalhes do pagamento no Mercado Pago
$payment = $paymentClient->get($paymentId);
$preferenceId = $payment->preference_id ?? null;
$userId = (string) $payment->external_reference;
$valorPago = $payment->transaction_amount;
$status = $payment->status;

file_put_contents(__DIR__ . '/../../logs/webhook_debug.log', "Resposta do Mercado Pago: " . json_encode($payment, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

// Atualiza o payment_id na transação pendente
if ($preferenceId) {
    $stmt = $pdo->prepare("UPDATE transactions SET payment_id = :payment_id WHERE preference_id = :preference_id AND status = 'pending' LIMIT 1");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':preference_id' => $preferenceId
    ]);
}

// Busca a transação agora com o payment_id atualizado
$stmt = $pdo->prepare("SELECT id, amount, status, user_id FROM transactions WHERE payment_id = :payment_id LIMIT 1");
$stmt->execute([':payment_id' => $paymentId]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Nenhuma transação encontrada para payment_id $paymentId.\n", FILE_APPEND);
    http_response_code(400);
    exit("Transação não encontrada.");
}

// Valida se o usuário do pagamento corresponde ao da transação
if ((string) $userId !== (string) $transaction['user_id']) {
    file_put_contents(__DIR__ . '/../../logs/webhook_error.log', date('Y-m-d H:i:s') . " - ERRO: Tentativa de fraude! userId $userId não corresponde à transação no banco.\n", FILE_APPEND);
    http_response_code(400);
    exit("Erro: ID do usuário não corresponde.");
}

// Verifica o status atual antes de atualizar
$stmt = $pdo->prepare("SELECT status FROM transactions WHERE payment_id = :payment_id LIMIT 1");
$stmt->execute([':payment_id' => $paymentId]);
$oldStatus = $stmt->fetchColumn();

if ($oldStatus === 'pending' && $status === 'approved') {
    file_put_contents(__DIR__ . '/../../logs/webhook_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Transação mudou de 'pending' para 'approved' - Payment ID: $paymentId\n", FILE_APPEND);
}

$stmt = $pdo->prepare("UPDATE transactions SET status = :status WHERE payment_id = :payment_id");
$stmt->execute([
    ':status' => $status,
    ':payment_id' => $paymentId
]);

file_put_contents(__DIR__ . '/../../logs/webhook_debug.log', date('Y-m-d H:i:s') . " - DEBUG: Status atualizado para $status no banco para payment_id $paymentId.\n", FILE_APPEND);


// Se aprovado, adiciona créditos ao usuário
if ($status === 'approved') {
    $stmt = $pdo->prepare("UPDATE users SET credits = credits + :valorPago WHERE id = :user_id");
    $stmt->execute([':valorPago' => $valorPago, ':user_id' => $userId]);
}

http_response_code(200);
exit("Webhook processado com sucesso.");
