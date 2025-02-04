<?php
require_once 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") 
{
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
    {
        die("E-mail inválido.");
    }

    try 
    {
        $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) 
        {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ../frontend/index.php");
            exit();
        } else 
        {
            echo "E-mail ou senha incorretos.";
        }
    } catch (PDOException $e) 
    {
        die("Erro no login: " . $e->getMessage());
    }
}
