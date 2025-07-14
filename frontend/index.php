<?php
session_start();
require_once '../backend/db.php';

$userLoggedIn = false;
$credits = 0;

if (isset($_SESSION['user_id'])) {
    $userLoggedIn = true;

    try {
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $credits = $user ? $user['credits'] : 0;
    } catch (PDOException $e) {
        die("Erro ao buscar créditos: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Loja</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/index.css">
</head>
<body>

    <header class="navbar">
        <h2>Minha Loja</h2>
        <div class="navbar-right">
            <?php if ($userLoggedIn): ?>
                <span class="credits-badge">Créditos: R$ <?php echo number_format($credits, 2, ',', '.'); ?></span>
                <a href="../backend/logout.php">Sair</a>
            <?php else: ?>
                <span class="credits-badge">Créditos: R$ 0,00</span>
                <a href="login.html">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">
        <section class="hero-section">
            <h1 class="hero-title">Adquira Créditos Facilmente</h1>
            <p class="hero-subtitle">Compre créditos para usar em nossa plataforma com segurança e rapidez através do Mercado Pago.</p>
        </section>

        <section class="products">
            <div class="product">
                <h3>Pacote de Créditos 1</h3>
                <p class="product-description">Um pacote inicial perfeito para começar a explorar nossos serviços.</p>
                <p class="product-price">R$ 100,00</p>
                <button 
                    class="buy-button <?php echo !$userLoggedIn ? 'login-prompt' : ''; ?>" 
                    onclick="window.location.href='<?php echo $userLoggedIn ? "../backend/checkout.php?produto=1" : "login.html"; ?>'">
                    <?php echo $userLoggedIn ? 'Comprar Agora' : 'Faça Login para Comprar'; ?>
                </button>
            </div>

            <div class="product popular">
                <div class="popular-badge">O mais popular</div>
                <h3>Pacote de Créditos 2</h3>
                <p class="product-description">Opção intermediária com ótimo custo-benefício para uso regular.</p>
                <p class="product-price">R$ 150,00</p>
                <button 
                    class="buy-button <?php echo !$userLoggedIn ? 'login-prompt' : ''; ?>" 
                    onclick="window.location.href='<?php echo $userLoggedIn ? "../backend/checkout.php?produto=2" : "login.html"; ?>'">
                    <?php echo $userLoggedIn ? 'Comprar Agora' : 'Faça Login para Comprar'; ?>
                </button>
            </div>

            <div class="product">
                <h3>Pacote de Créditos 3</h3>
                <p class="product-description">O melhor custo-benefício para usuários que buscam mais vantagens.</p>
                <p class="product-price">R$ 200,00</p>
                <button 
                    class="buy-button <?php echo !$userLoggedIn ? 'login-prompt' : ''; ?>" 
                    onclick="window.location.href='<?php echo $userLoggedIn ? "../backend/checkout.php?produto=3" : "login.html"; ?>'">
                    <?php echo $userLoggedIn ? 'Comprar Agora' : 'Faça Login para Comprar'; ?>
                </button>
            </div>
        </section>
    </main>

</body>
</html>