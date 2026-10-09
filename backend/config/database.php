<?php
$host = '127.0.0.1';
$dbname = 'senai_conecta';
$user = 'root';$pass = ''; // Coloque a sua palavra-passe aqui se usar alguma no XAMPP

try {
    // 1. Liga ao MySQL sem selecionar base de dados
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user,$pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 2. Cria a base de dados se não existir e seleciona-a
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    // 3. Cria todas as tabelas automaticamente se não existirem
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuario (
            id_usuario INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            foto VARCHAR(255) DEFAULT 'avatar.png'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS publicacao (
            id_publicacao INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            texto TEXT NOT NULL,
            imagem VARCHAR(255) DEFAULT NULL,
            datahora_publicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS curtida (
            id_curtida INT AUTO_INCREMENT PRIMARY KEY,
            id_publicacao INT NOT NULL,
            id_usuario INT NOT NULL,
            FOREIGN KEY (id_publicacao) REFERENCES publicacao(id_publicacao) ON DELETE CASCADE,
            FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS comentario (
            id_comentario INT AUTO_INCREMENT PRIMARY KEY,
            id_publicacao INT NOT NULL,
            id_usuario INT NOT NULL,
            texto_comentario TEXT NOT NULL,
            datahora_comentario DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_publicacao) REFERENCES publicacao(id_publicacao) ON DELETE CASCADE,
            FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

} catch (PDOException $e) {
    die(json_encode(['erro' => 'Falha crítica no banco de dados: ' . $e->getMessage()]));
}
?>