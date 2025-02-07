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
    1 => ["nome" => "Produto 1", "preco" => 100.00],
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
    "picture_url" => "https://www.google.com/url?sa=i&url=https%3A%2F%2Fborainvestir.b3.com.br%2Fobjetivos-financeiros%2Fdinheiro-esquecido-no-fgc-supera-r-100-milhoes-veja-se-voce-tem-valores-a-receber%2F&psig=AOvVaw0ihBDk8NlRoWoSO_ZrjOT6&ust=1738972391511000&source=images&cd=vfe&opi=89978449&ved=0CBQQjRxqFwoTCJizoI-fsIsDFQAAAAAdAAAAABAE",
    "category_id" => "eletronicos",
    "quantity" => 1,
    "currency_id" => "BRL"
];


$preference = $client->create([
    "items" => [$item],
    "external_reference" => (string) $_SESSION['user_id'],
    "back_urls" => [
        "success" => "http://localhost/mercado-pago/backend/status/success.php",
        "failure" => "http://localhost/mercado-pago/backend/status/failure.php",
        "pending" => "http://localhost/mercado-pago/backend/status/pending.php"
    ],
    "auto_return" => "approved",
    "notification_url" => "https://2319-2804-7f1-eb03-64db-c0e1-ca27-a055-3418.ngrok-free.app/mercado-pago/backend/status/webhook.php",
    // "payment_methods" => [
    //     "excluded_payment_methods" => [
    //         ["id" => "visa"] 
    //     ],
    //     "installments" => 6 
    // ]
]);


try {
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, produto_id, amount, status, payment_id) VALUES (:user_id, :produto_id, :amount, 'pending', :payment_id)");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':produto_id' => $produtoId,
        ':amount' => $produto["preco"],
        ':payment_id' => null
    ]);
} catch (PDOException $e) {
    die("Erro ao salvar transação: " . $e->getMessage());
}

header("Location: " . $preference->init_point);
exit();
