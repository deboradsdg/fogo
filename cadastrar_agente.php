<?php
// cadastrar_agente.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

exigirAdmin();
$pdo = getFogoDatabase();
$erro = null;
$sucesso = null;

if (isset($_GET['toggle_status'])) {
    $idAgente = filter_input(INPUT_GET, 'toggle_status', FILTER_VALIDATE_INT);
    $statusAtual = filter_input(INPUT_GET, 'current', FILTER_VALIDATE_INT);
    
    if ($idAgente === (int)$_SESSION['usuario']['id']) {
        $erro = 'Você não pode desativar seu próprio usuário de administrador.';
    } elseif ($idAgente) {
        $novoStatus = $statusAtual === 1 ? 0 : 1;
        $stmtStatus = $pdo->prepare('UPDATE usuarios SET ativo = :status WHERE id = :id');
        $stmtStatus->execute([':status' => $novoStatus, ':id' => $idAgente]);
        header('Location: cadastrar_agente.php?msg=status_alterado');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $corporacao = trim($_POST['corporacao'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';
    $perfil = trim($_POST['perfil'] ?? 'agente');

    if (empty($nome) || empty($matricula) || empty($corporacao) || empty($email) || empty($senha)) {
        $erro = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Forneça um endereço de e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha de acesso deve conter pelo menos 6 caracteres.';
    } else {
        try {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO usuarios (nome, matricula, corporacao, email, senha_hash, perfil, ativo)
                VALUES (:nome, :matricula, :corporacao, :email, :senha_hash, :perfil, 1)
            ');
            $stmt->execute([
                ':nome'       => $nome,
                ':matricula'  => $matricula,
                ':corporacao' => $corporacao,
                ':email'      => $email,
                ':senha_hash' => $senhaHash,
                ':perfil'     => $perfil
            ]);
            $sucesso = "Agente {$nome} ({$matricula}) registrado com sucesso!";
        } catch (PDOException $e) {
            $erro = ($e->getCode() === '23000') ? 'Matrícula ou e-mail já existente.' : 'Erro: ' . $e->getMessage();
        }
    }
}

$stmtLista = $pdo->query('SELECT id, nome, matricula, corporacao, email, perfil, ativo FROM usuarios ORDER BY id DESC');
$agentes = $stmtLista->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Agentes | F.O.G.O.</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="admin-split-layout">
    <div class="admin-header-bar">
        <h1 class="tech-gradient-text" style="font-size: 1.6rem;">Gestão de Agentes e Operadores</h1>
        <a href="index.php" class="btn-outline">← Voltar ao Painel</a>
    </div>

    <!-- Formulário Lateral de Cadastro -->
    <div class="tech-card" style="padding: 24px; height: fit-content;">
        <h2 style="font-size: 1.15rem; color: var(--purple-glow); margin-bottom: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
            Novo Agente
        </h2>

        <?php if ($erro): ?>
            <div class="alert-banner alert-erro" style="margin-bottom: 14px; padding: 10px 14px;">
                <span>✖ <?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert-banner alert-sucesso" style="margin-bottom: 14px; padding: 10px 14px;">
                <span>✔ <?= htmlspecialchars($sucesso) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="cadastrar_agente.php">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="nome" required placeholder="Ex: Roberto Carlos">
            </div>

            <div class="form-group">
                <label>Matrícula Funcional</label>
                <input type="text" name="matricula" required placeholder="Ex: GT2048, GM109">
            </div>

            <div class="form-group">
                <label>Corporação</label>
                <select name="corporacao" required>
                    <option value="">Selecione...</option>
                    <option value="Agente de Trânsito">Agente de Trânsito</option>
                    <option value="Guarda Municipal">Guarda Municipal</option>
                    <option value="Polícia Militar">Polícia Militar</option>
                    <option value="Polícia Civil">Polícia Civil</option>
                    <option value="Centro Integrado de Operações">Centro Integrado de Operações</option>
                    <option value="Fiscalização de Estacionamento">Fiscalização de Estacionamento</option>
                </select>
            </div>

            <div class="form-group">
                <label>E-mail Corporativo</label>
                <input type="email" name="email" required placeholder="agente@fogo.gov.br">
            </div>

            <div class="form-group">
                <label>Senha de Acesso</label>
                <input type="password" name="senha" required minlength="6" placeholder="Mínimo 6 caracteres">
            </div>

            <div class="form-group">
                <label>Nível de Acesso (RBAC)</label>
                <select name="perfil" required>
                    <option value="agente" selected>Agente (Consulta e Manutenção)</option>
                    <option value="admin">Administrador (Total)</option>
                </select>
            </div>

            <button type="submit" class="btn-tech" style="width: 100%; justify-content: center; margin-top: 10px;">
                Registrar Agente
            </button>
        </form>
    </div>

    <!-- Tabela de Agentes Registrados -->
    <div class="tech-card" style="padding: 24px; overflow-x: auto;">
        <h2 style="font-size: 1.15rem; color: var(--purple-glow); margin-bottom: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
            Operadores Credenciados
        </h2>
        <table>
            <thead>
                <tr>
                    <th>Matrícula / Nome</th>
                    <th>Corporação</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agentes as $ag): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--cyan-glow);"><?= htmlspecialchars($ag['matricula']) ?></strong><br>
                            <?= htmlspecialchars($ag['nome']) ?><br>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($ag['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($ag['corporacao']) ?></td>
                        <td>
                            <span class="badge-role-tag badge-<?= htmlspecialchars($ag['perfil']) ?>">
                                <?= strtoupper(htmlspecialchars($ag['perfil'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $ag['ativo'] ? 'user-active' : 'user-inactive' ?>">
                                <?= $ag['ativo'] ? '● Ativo' : '○ Inativo' ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($ag['id'] !== (int)$_SESSION['usuario']['id']): ?>
                                <a href="cadastrar_agente.php?toggle_status=<?= $ag['id'] ?>&current=<?= $ag['ativo'] ?>" 
                                   class="btn-outline" style="padding: 4px 8px; font-size: 0.75rem;"
                                   onclick="return confirm('Alterar status deste usuário?');">
                                    <?= $ag['ativo'] ? 'Desativar' : 'Reativar' ?>
                                </a>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">(Você)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>