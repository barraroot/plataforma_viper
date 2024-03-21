<?php
session_start();

if (!isset($_SESSION['email'])) {
    exit;
}
    include './../conectarbanco.php';

$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$valorAcumulado = $_POST['valuecoin'];

$emailDaSessao = $_SESSION['email'];

$sql = "UPDATE appconfig SET saldo = saldo + ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ds", $valorAcumulado, $emailDaSessao);

if ($stmt->execute()) {
    exit();
    
} else {
    echo "Erro ao atualizar o saldo: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>