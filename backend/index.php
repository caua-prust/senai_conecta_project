<?php
ob_start();
ini_set('display_errors', 0); 
error_reporting(0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function responder($dados) {
    ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

session_start();

$db_path = __DIR__ . '/config/database.php';
if (!file_exists($db_path)) responder(['erro' => 'Ficheiro database.php não encontrado.']);
require_once $db_path;

$action = $_GET['action'] ?? '';
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) { @mkdir($uploadDir, 0777, true); }

try {
    if ($action == 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha_raw = trim($_POST['senha'] ?? '');
        
        if (empty($nome) || empty($username) || empty($email) || empty($senha_raw)) {
            responder(['sucesso' => false, 'msg' => 'Preencha todos os campos.']);
        }

        $senha = password_hash($senha_raw, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO usuario (nome, username, email, senha) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $username, $email, $senha]);
        responder(['sucesso' => true, 'msg' => 'Conta criada com sucesso!']);
    } 
    elseif ($action == 'login') {
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['username'] = $user['username'];
            responder(['sucesso' => true]);
        } else {
            responder(['sucesso' => false, 'msg' => 'Dados incorretos.']);
        }
    }
    elseif ($action == 'check_session') {
        if(isset($_SESSION['user_id'])){
            $stmt = $pdo->prepare("SELECT foto FROM usuario WHERE id_usuario = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            responder([
                'autenticado' => true, 
                'username' => $_SESSION['username'], 
                'user_id' => $_SESSION['user_id'],
                'foto' => $user['foto'] ?? 'avatar.png'
            ]);
        } else {
            responder(['autenticado' => false]);
        }
    }
    elseif ($action == 'atualizar_foto' && isset($_SESSION['user_id'])) {
        if(isset($_FILES['foto']) && $_FILES['foto']['error'] == 0){
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = uniqid() . '_avatar.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
            
            $stmt = $pdo->prepare("UPDATE usuario SET foto = ? WHERE id_usuario = ?");
            $stmt->execute([$foto, $_SESSION['user_id']]);
            responder(['sucesso' => true]);
        } else {
            responder(['sucesso' => false, 'erro' => 'Imagem não recebida.']);
        }
    }
    elseif ($action == 'logout') {
        session_destroy();
        responder(['sucesso' => true]);
    }
    elseif ($action == 'feed') {
        $busca = $_GET['q'] ?? '';
        $userId = $_SESSION['user_id'] ?? 0;
        
        $sql = "SELECT p.*, u.nome, u.username, u.foto, 
                (SELECT COUNT(*) FROM curtida c WHERE c.id_publicacao = p.id_publicacao) as curtidas,
                (SELECT COUNT(*) FROM curtida c WHERE c.id_publicacao = p.id_publicacao AND c.id_usuario = ?) as user_curtiu
                FROM publicacao p 
                JOIN usuario u ON p.id_usuario = u.id_usuario ";
        
        if(!empty($busca)) $sql .= " WHERE u.username LIKE ? ";
        $sql .= " ORDER BY p.datahora_publicacao DESC";
        
        $stmt = $pdo->prepare($sql);
        if(!empty($busca)) $stmt->execute([$userId, "%$busca%"]);
        else $stmt->execute([$userId]);
        
        responder($stmt->fetchAll() ?: []); 
    }
    elseif ($action == 'publicar' && isset($_SESSION['user_id'])) {
        $texto = trim($_POST['texto'] ?? '');
        if(empty($texto)) responder(['erro' => 'Texto vazio.']);
        
        $imagem = null;
        if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0){
            $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
            $imagem = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadDir . $imagem);
        }
        
        $stmt = $pdo->prepare("INSERT INTO publicacao (id_usuario, texto, imagem) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $texto, $imagem]);
        responder(['sucesso' => true]);
    }
    elseif ($action == 'curtir' && isset($_SESSION['user_id'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id_pub = $data['id_publicacao'] ?? 0;
        $id_user = $_SESSION['user_id'];
        
        if ($id_pub) {
            $check = $pdo->prepare("SELECT id_curtida FROM curtida WHERE id_publicacao = ? AND id_usuario = ?");
            $check->execute([$id_pub, $id_user]);
            if($check->rowCount() > 0) {
                $pdo->prepare("DELETE FROM curtida WHERE id_publicacao = ? AND id_usuario = ?")->execute([$id_pub, $id_user]);
            } else {
                $pdo->prepare("INSERT INTO curtida (id_publicacao, id_usuario) VALUES (?, ?)")->execute([$id_pub, $id_user]);
            }
        }
        responder(['sucesso' => true]);
    }
    elseif ($action == 'comentar' && isset($_SESSION['user_id'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        $texto = trim($data['texto'] ?? '');
        $id_pub = $data['id_publicacao'] ?? 0;
        if(!empty($texto) && $id_pub) {
            $stmt = $pdo->prepare("INSERT INTO comentario (id_publicacao, id_usuario, texto_comentario) VALUES (?, ?, ?)");
            $stmt->execute([$id_pub, $_SESSION['user_id'], $texto]);
        }
        responder(['sucesso' => true]);
    }
    elseif ($action == 'listar_comentarios') {
        $id_pub = $_GET['id_publicacao'] ?? 0;
        $stmt = $pdo->prepare("SELECT c.*, u.nome, u.username, u.foto 
                               FROM comentario c JOIN usuario u ON c.id_usuario = u.id_usuario 
                               WHERE c.id_publicacao = ? ORDER BY c.datahora_comentario ASC");
        $stmt->execute([$id_pub]);
        responder($stmt->fetchAll() ?: []);
    }
    elseif ($action == 'excluir' && isset($_SESSION['user_id'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("DELETE FROM publicacao WHERE id_publicacao = ? AND id_usuario = ?");
        $stmt->execute([$data['id_publicacao'] ?? 0, $_SESSION['user_id']]);
        responder(['sucesso' => true]);
    }
    else { responder(['erro' => 'Ação inválida.']); }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) responder(['sucesso' => false, 'msg' => 'Username ou Email em uso.']);
    else responder(['sucesso' => false, 'erro' => 'Erro BD: ' . $e->getMessage()]);
}
?>