<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

$produtoId = $_GET['produto'] ?? null;

$produtos = [
    1 => ["nome" => "Produto 1", "preco" => 1.00],
    2 => ["nome" => "Produto 2", "preco" => 200.00],
];

if (!isset($produtos[$produtoId])) {
    die("Produto inválido.");
}

$produto = $produtos[$produtoId];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
MercadoPagoConfig::setIntegratorId($_ENV['MP_INTEGRATOR_ID']);


$client = new PreferenceClient();

$item = [
    "id" => "1234",
    "title" => $produto["nome"],
    "description" => "Dispositivo de loja de comércio eletrônico móvel",
    "picture_url" => "https://cdn.borainvestir.b3.com.br/2024/04/11113610/dinheiro-esquecido-fgc.webp",
    "quantity" => 1,
    "currency_id" => "BRL",
    "unit_price" => $produto["preco"]
];


try {
    $preference = $client->create([
        "items" => [$item],
        "external_reference" => (string) $_SESSION['user_id'],
        "back_urls" => [
            "success" => "https://b52d-2804-7f1-eb03-64db-8c34-cb89-b3c6-86f9.ngrok-free.app/mercado-pago/backend/status/success.php",
            "failure" => "https://b52d-2804-7f1-eb03-64db-8c34-cb89-b3c6-86f9.ngrok-free.app/mercado-pago/backend/status/failure.php",
            "pending" => "https://b52d-2804-7f1-eb03-64db-8c34-cb89-b3c6-86f9.ngrok-free.app/mercado-pago/backend/status/pending.php"
        ],
        "auto_return" => "approved",
        "notification_url" => "https://b52d-2804-7f1-eb03-64db-8c34-cb89-b3c6-86f9.ngrok-free.app/mercado-pago/backend/status/webhook.php",
    ]);
} catch (\Exception $e) {
    $errorMessage = $e->getMessage();
    $errorTrace = json_encode($e->getTrace(), JSON_PRETTY_PRINT);

    file_put_contents(__DIR__ . '/../logs/mp_api_error.log', date('Y-m-d H:i:s') . " - ERRO API: $errorMessage\nTRACE: $errorTrace\n", FILE_APPEND);

    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, produto_id, amount, status, payment_id, preference_id) VALUES (:user_id, :produto_id, :amount, 'pending', :payment_id, :preference_id)");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':produto_id' => $produtoId,
        ':amount' => $produto["preco"],
        ':payment_id' => null,
        ':preference_id' => $preference->id
    ]);
} catch (PDOException $e) {
    die("Erro ao salvar transação: " . $e->getMessage());
}

header("Location: " . $preference->init_point);
exit();
