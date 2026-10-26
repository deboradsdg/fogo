<?php
// login.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

// Se já estiver com sessão ativa, redireciona ao painel
if (getUsuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = null;

// Processamento do envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($identificador) || empty($senha)) {
        $erro = 'Preencha todos os campos para continuar.';
    } else {
        try {
            $pdo = getFogoDatabase();

            // Busca pelo e-mail ou matrícula
            $stmt = $pdo->prepare('
                SELECT id, nome, matricula, corporacao, email, senha_hash, perfil, ativo 
                FROM usuarios 
                WHERE (email = :id OR matricula = :id) AND ativo = 1 
                LIMIT 1
            ');
            $stmt->execute([':id' => $identificador]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                session_regenerate_id(true);

                // Grava os dados do agente na sessão
                $_SESSION['usuario'] = [
                    'id'         => $usuario['id'],
                    'nome'       => $usuario['nome'],
                    'matricula'  => $usuario['matricula'],
                    'corporacao' => $usuario['corporacao'],
                    'email'      => $usuario['email'],
                    'perfil'     => $usuario['perfil']
                ];

                // Redireciona para o painel principal
                header('Location: index.php');
                exit;
            } else {
                $erro = 'Credenciais inválidas ou agente desativado.';
            }
        } catch (PDOException $e) {
            $erro = 'Falha no banco de dados: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Operacional | F.O.G.O.</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 40px 32px;
            position: relative;
        }
        .login-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: var(--gradient-tech);
            border-radius: 12px 12px 0 0;
        }
        .brand-title {
            text-align: center;
            font-size: 2.3rem;
            margin-bottom: 4px;
        }
        .brand-sub {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 28px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }
        .form-group label {
            font-size: 0.8rem;
            color: var(--cyan-glow);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="tech-card login-box">
        <h1 class="brand-title tech-gradient-text">F.O.G.O.</h1>
        <p class="brand-sub">Ferramenta Onisciente de Gestão e Operações</p>

        <?php if ($erro): ?>
            <div class="alert-banner alert-erro" style="margin-bottom: 18px; padding: 10px 14px; font-size: 0.85rem;">
                <span>✖ <?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="identificador">E-mail ou Matrícula</label>
                <input type="text" id="identificador" name="identificador" placeholder="ex: ADM001 ou souza@fogo.gov.br" required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Chave Criptográfica</label>
                <input type="password" id="senha" name="senha" placeholder="••••••••••••" required>
            </div>

            <button type="submit" class="btn-tech" style="width: 100%; justify-content: center; margin-top: 10px;">
                Conectar ao Sistema
            </button>
        </form>
    </div>
</div>

</body>
</html>