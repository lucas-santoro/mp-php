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

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Falhou</title>
    <link rel="stylesheet" href="../../styles/index.css">
</head>
<body>
    <div class="container">
        <h2>Pagamento Falhou</h2>
        <p>Infelizmente, seu pagamento não foi aprovado.</p>
        <p><a href="../../frontend/index.php"><button>Voltar para a página inicial</button></a></p>
    </div>
</body>
</html>
