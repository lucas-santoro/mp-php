<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../frontend/login.html");
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
    die(json_encode(["error" => "Nenhuma transação encontrada para esse pagamento."]));
}

header("Location: payment_pending.php");
exit();
?>
