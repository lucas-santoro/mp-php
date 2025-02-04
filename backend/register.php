<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") 
{
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
    {
        die("E-mail inválido.");
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try 
    {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $passwordHash
        ]);
        echo "Cadastro realizado com sucesso!";
    } catch (PDOException $e) 
    {
        die("Erro ao cadastrar: " . $e->getMessage());
    }
}
