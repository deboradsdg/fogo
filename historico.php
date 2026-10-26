<?php
// historico.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

// Exige autenticação (tanto admin quanto agente podem consultar o histórico)
exigirAutenticacao();

$idEquipamento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idEquipamento) {
    header('Location: index.php?msg=erro_id_invalido');
    exit;
}

$pdo = getFogoDatabase();

// 1. Busca os detalhes do equipamento selecionado
$stmtEq = $pdo->prepare('
    SELECT id, tipo, numero_serie, marca, modelo, logradouro, referencia, cidade_uf, situacao, necessidade_manutencao
    FROM equipamentos
    WHERE id = :id
    LIMIT 1
');
$stmtEq->execute([':id' => $idEquipamento]);
$equipamento = $stmtEq->fetch();

if (!$equipamento) {
    header('Location: index.php?msg=erro_nao_encontrado');
    exit;
}

// 2. Busca todas as ocorrências e manutenções com dados do usuário que fez o registro
$stmtHist = $pdo->prepare('
    SELECT 
        h.id,
        h.situacao_anterior,
        h.situacao_nova,
        h.manutencao_status,
        h.observacao,
        h.registrado_em,
        u.nome AS usuario_nome,
        u.matricula AS usuario_matricula,
        u.corporacao AS usuario_corporacao,
        u.perfil AS usuario_perfil
    FROM historico_manutencoes h
    LEFT JOIN usuarios u ON u.id = h.usuario_id
    WHERE h.equipamento_id = :equipamento_id
    ORDER BY h.registrado_em DESC, h.id DESC
');
$stmtHist->execute([':equipamento_id' => $idEquipamento]);
$eventos = $stmtHist->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico Operacional | Equipamento #<?= htmlspecialchars($equipamento['id']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="layout-container">
    <!-- Cabeçalho de Navegação -->
    <header class="tech-card topbar">
        <div class="brand-meta">
            <h1 class="brand-logo tech-gradient-text">F.O.G.O.</h1>
            <span class="badge-live">Auditoria Tática</span>
        </div>
        <div>
            <a href="index.php" class="btn-outline">← Voltar ao Painel</a>
        </div>
    </header>

    <!-- Resumo Técnico do Dispositivo -->
    <div class="tech-card" style="padding: 24px; margin-bottom: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
            <div>
                <span style="font-size: 0.8rem; color: var(--cyan-glow); text-transform: uppercase; letter-spacing: 1px;">
                    <?= strtoupper(htmlspecialchars($equipamento['tipo'])) ?>
                </span>
                <h2 style="font-size: 1.4rem; color: var(--text-primary); margin: 4px 0;">
                    <?= htmlspecialchars($equipamento['marca']) ?> <?= htmlspecialchars($equipamento['modelo']) ?>
                </h2>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Serial: <strong style="color: #fff; font-family: monospace;"><?= htmlspecialchars($equipamento['numero_serie']) ?></strong> | 
                    Local: <?= htmlspecialchars($equipamento['logradouro'] ?: 'Posto Móvel') ?> (<?= htmlspecialchars($equipamento['cidade_uf']) ?>)
                </div>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <span class="status-tag st-<?= htmlspecialchars($equipamento['situacao']) ?>">
                    <?= str_replace('_', ' ', htmlspecialchars($equipamento['situacao'])) ?>
                </span>
                <span class="btn-outline" style="font-size: 0.75rem; padding: 4px 10px; pointer-events: none;">
                    Manutenção: <?= ucfirst(htmlspecialchars($equipamento['necessidade_manutencao'])) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Linha do Tempo de Ocorrências -->
    <div class="tech-card" style="padding: 30px;">
        <h3 style="font-size: 1.15rem; color: var(--purple-glow); margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            Linha do Tempo de Ocorrências e Atualizações
        </h3>

        <?php if (empty($eventos)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 40px 0;">
                Nenhum registro de manutenção ou alteração cadastrado para este equipamento até o momento.
            </p>
        <?php else: ?>
            <div class="timeline-container">
                <?php foreach ($eventos as $ev): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-card">
                            <div class="timeline-meta">
                                <span class="timeline-date">
                                    ⏱ <?= date('d/m/Y H:i:s', strtotime($ev['registrado_em'])) ?>
                                </span>
                                <span class="timeline-agent">
                                    <?= htmlspecialchars($ev['usuario_nome'] ?? 'Sistema / Excluído') ?> 
                                    (<?= htmlspecialchars($ev['usuario_matricula'] ?? 'N/D') ?>) 
                                    • <?= htmlspecialchars($ev['usuario_corporacao'] ?? 'Operações') ?>
                                </span>
                            </div>

                            <div class="timeline-state-change">
                                <span style="color: var(--text-muted);">Transição de Status:</span>
                                <?php if ($ev['situacao_anterior']): ?>
                                    <span class="status-tag st-<?= htmlspecialchars($ev['situacao_anterior']) ?>" style="font-size: 0.68rem;">
                                        <?= str_replace('_', ' ', htmlspecialchars($ev['situacao_anterior'])) ?>
                                    </span>
                                    <span style="color: var(--cyan-glow);">➔</span>
                                <?php endif; ?>
                                <span class="status-tag st-<?= htmlspecialchars($ev['situacao_nova']) ?>" style="font-size: 0.68rem;">
                                    <?= str_replace('_', ' ', htmlspecialchars($ev['situacao_nova'])) ?>
                                </span>
                                <span style="margin-left: auto; color: var(--text-secondary); font-size: 0.8rem;">
                                    Plano: <strong><?= ucfirst(htmlspecialchars($ev['manutencao_status'])) ?></strong>
                                </span>
                            </div>

                            <div class="timeline-observation">
                                <?= nl2br(htmlspecialchars($ev['observacao'] ?: 'Nenhuma observação informada.')) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>