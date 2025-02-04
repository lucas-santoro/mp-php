<?php
session_start();
require_once '../backend/db.php';

$userLoggedIn = false;
$credits = 0;

if (isset($_SESSION['user_id'])) 
{
    $userLoggedIn = true;
    
    try {
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $credits = $user ? $user['credits'] : 0;
    } catch (PDOException $e) 
    {
        die("Erro ao buscar créditos: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja</title>
    <link rel="stylesheet" href="styles/index.css">
</head>
<body>
    <div class="navbar">
        <h2>Minha Loja</h2>
        <div>
            <?php if ($userLoggedIn): ?>
                <span>Créditos: R$ <?php echo number_format($credits, 2, ',', '.'); ?></span>
                <a href="../backend/logout.php">Sair</a>
            <?php else: ?>
                <span>Créditos: R$ 0,00</span>
                <a href="login.html">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="products">
    <div class="product">
        <h3>Produto 1</h3>
        <p>R$ 100,00</p>
        <button onclick="window.location.href='<?php echo $userLoggedIn ? "backend/checkout.php?produto=1" : "login.html"; ?>'">
            Comprar
        </button>
    </div>
    <div class="product">
        <h3>Produto 2</h3>
        <p>R$ 200,00</p>
        <button onclick="window.location.href='<?php echo $userLoggedIn ? "backend/checkout.php?produto=2" : "login.html"; ?>'">
            Comprar
        </button>
    </div>
</div>

</body>
</html>
