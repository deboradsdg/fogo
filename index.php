<?php
// index.php - Painel de Controle Integrado F.O.G.O.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

$usuario = getUsuarioLogado();
if (!$usuario) {
    header('Location: login.php');
    exit;
}

$pdo = getFogoDatabase();

// Consulta inicial para listagem
$stmt = $pdo->query("
    SELECT e.*, u.nome AS responsavel_nome 
    FROM equipamentos e
    LEFT JOIN usuarios u ON e.atualizado_por = u.id
    ORDER BY e.id DESC
");
$equipamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Métricas rápidas
$totalEquipamentos = count($equipamentos);
$operacionais = count(array_filter($equipamentos, fn($e) => $e['situacao'] === 'operacional'));
$criticos = count(array_filter($equipamentos, fn($e) => $e['necessidade_manutencao'] === 'imediata'));
$agentesContagem = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE em_servico = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F.O.G.O. // Painel Integrado de Comando</title>
    <link rel="stylesheet" href="assets/style.css">

</head>
<body>

    <div class="layout-hub">
        
        <!-- Barra de Navegação Global -->
        <header class="global-nav">
            <div>
                <h1 class="tech-gradient-text" style="font-size: 1.4rem; margin: 0; letter-spacing: 1px;">F.O.G.O. // SISTEMA INTEGRADO</h1>
                <span style="font-size: 0.75rem; color: var(--text-secondary);">MUNICIPAL URBAN SAFETY & MOBILITY INTELLIGENCE</span>
            </div>

            <nav class="nav-links">
                <a href="index.php" class="nav-btn active">Visão Geral & Hub</a>
                <a href="mapa.php" class="nav-btn">Mapa C.I.O. (Leaflet)</a>
                <a href="previsoes.php" class="nav-btn">Previsões PROMETHEUS</a>
            </nav>

            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="text-align: right;">
                    <div style="font-size: 0.85rem; color: #fff; font-weight: 600;"><?= htmlspecialchars($usuario['nome']) ?></div>
                    <div style="font-size: 0.7rem; color: var(--cyan-glow); text-transform: uppercase;"><?= htmlspecialchars($usuario['perfil']) ?> (<?= htmlspecialchars($usuario['corporacao']) ?>)</div>
                </div>
                <a href="logout.php" style="color: #ef4444; font-size: 0.8rem; text-decoration: none; border: 1px solid rgba(239, 68, 68, 0.3); padding: 6px 12px; border-radius: 6px;">Sair</a>
            </div>
        </header>

        <!-- Feedback de Ação -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_atualizado'): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border-left: 4px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 6px; font-size: 0.85rem;">
                ✓ Situação operacional e registro de auditoria gravados com sucesso!
            </div>
        <?php endif; ?>

        <!-- Cards com Atalhos -->
        <section class="hub-cards">
            <div class="hub-action-card mapa">
                <div>
                    <span style="font-size: 0.75rem; color: #38bdf8; text-transform: uppercase; font-weight: 700;">Telemetria Geoespacial</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">Centro de Operações (C.I.O.)</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Visualização cartográfica dos <?= $totalEquipamentos ?> ativos e <?= $agentesContagem ?> agentes patrulhando Marília.
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: #10b981;"><strong><?= $operacionais ?></strong> operacionais</span>
                    <a href="mapa.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 14px;">Abrir Mapa &rarr;</a>
                </div>
            </div>

            <div class="hub-action-card previsoes">
                <div>
                    <span style="font-size: 0.75rem; color: #c084fc; text-transform: uppercase; font-weight: 700;">Inteligência Preditiva</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">PROMETHEUS Engine</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Cálculo do IVOR por região, antecipação de falhas e recomendações táticas de despacho.
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: <?= $criticos > 0 ? '#ef4444' : '#10b981' ?>;">
                        <strong><?= $criticos ?></strong> críticas
                    </span>
                    <a href="previsoes.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 14px; background: linear-gradient(135deg, #a855f7, #ec4899);">Ver Previsões &rarr;</a>
                </div>
            </div>

<div class="hub-action-card gestao">
                <div>
                    <span style="font-size: 0.75rem; color: #34d399; text-transform: uppercase; font-weight: 700;">Administração do Sistema</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">Gestão Operacional</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Gestão do efetivo de agentes de campo e registo de novos equipamentos urbanos.
                    </p>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                    <?php if ($usuario['perfil'] === 'admin'): ?>
                        <a href="cadastrar_agente.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 12px; background: linear-gradient(135deg, #06b6d4, #3b82f6); border-color: var(--cyan-glow);">
                            👥 Gerir Agentes
                        </a>
                        <a href="cadastrar_equipamento.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 12px;">
                            + Equipamento
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Barra de Filtros -->
        <section class="filter-bar">
            <div class="filter-group">
                <input type="text" id="filtro-texto" class="filter-input" placeholder="Buscar por série, modelo, rua ou bairro..." style="min-width: 280px;">
                
                <select id="filtro-tipo" class="filter-select">
                    <option value="">Todos os Tipos</option>
                    <option value="radar">Radares</option>
                    <option value="camera">Câmeras PTZ</option>
                    <option value="viatura">Viaturas</option>
                    <option value="patinete">Patinetes</option>
                    <option value="balanca">Balanças</option>
                </select>

                <select id="filtro-situacao" class="filter-select">
                    <option value="">Todas as Situações</option>
                    <option value="operacional">Operacional</option>
                    <option value="mau_funcionamento">Mau Funcionamento</option>
                    <option value="furtado">Furtado</option>
                    <option value="ainda_nao_instalado">Ainda Não Instalado</option>
                    <option value="desativado">Desativado</option>
                </select>
            </div>

            <div style="font-size: 0.8rem; color: var(--text-secondary);">
                Exibindo <span id="contador-linhas" style="color: #fff; font-weight: bold;"><?= $totalEquipamentos ?></span> equipamentos
            </div>
        </section>

        <!-- Tabela com Ação de Status Liberada para Agente -->
        <main class="tabela-container">
            <table class="tabela-dados" id="tabela-equipamentos">
                <thead>
                    <tr>
                        <th>Equipamento / Modelo</th>
                        <th>Tipo</th>
                        <th>Nº Série</th>
                        <th>Localização / Referência</th>
                        <th>Situação</th>
                        <th>Manutenção</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipamentos as $eq): ?>
                    <tr class="linha-equipamento" 
                        data-tipo="<?= htmlspecialchars($eq['tipo']) ?>" 
                        data-situacao="<?= htmlspecialchars($eq['situacao']) ?>"
                        data-busca="<?= htmlspecialchars(strtolower($eq['modelo'] . ' ' . $eq['numero_serie'] . ' ' . $eq['logradouro'] . ' ' . $eq['bairro'])) ?>">
                        
                        <td>
                            <strong><?= htmlspecialchars($eq['modelo']) ?></strong><br>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($eq['marca']) ?></span>
                        </td>
                        <td><span style="text-transform: uppercase; font-size: 0.75rem; color: var(--cyan-glow);"><?= htmlspecialchars($eq['tipo']) ?></span></td>
                        <td><code><?= htmlspecialchars($eq['numero_serie']) ?></code></td>
                        <td>
                            <?= htmlspecialchars($eq['logradouro']) ?><br>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($eq['bairro']) ?> - <?= htmlspecialchars($eq['referencia']) ?></span>
                        </td>
                        <td>
                            <span class="badge-status status-<?= htmlspecialchars($eq['situacao']) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $eq['situacao'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($eq['necessidade_manutencao'] === 'imediata'): ?>
                                <span style="color: #ef4444; font-weight: bold; font-size: 0.75rem;">IMEDIATA</span>
                            <?php elseif ($eq['necessidade_manutencao'] === 'agendada'): ?>
                                <span style="color: #f59e0b; font-size: 0.75rem;">Agendada: <?= htmlspecialchars($eq['proxima_manutencao']) ?></span>
                            <?php else: ?>
                                <span style="color: #10b981; font-size: 0.75rem;">Em dia</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Botão Operacional de Status: Disponível para Agente e Admin -->
                            <button type="button" 
                                    class="btn-acao" 
                                    style="border-color: #f59e0b; color: #f59e0b;" 
                                    onclick="abrirModalStatus(<?= $eq['id'] ?>, '<?= htmlspecialchars(addslashes($eq['modelo'])) ?>', '<?= htmlspecialchars(addslashes($eq['numero_serie'])) ?>', '<?= $eq['situacao'] ?>', '<?= $eq['necessidade_manutencao'] ?>', '<?= $eq['proxima_manutencao'] ?? '' ?>')">
                                ⚡ Status
                            </button>

                            <a href="historico.php?id=<?= $eq['id'] ?>" class="btn-acao" title="Linha do Tempo de Auditoria">Histórico</a>
                            <a href="mapa.php?lat=<?= $eq['latitude'] ?>&lng=<?= $eq['longitude'] ?>" class="btn-acao" style="border-color: var(--cyan-glow); color: var(--cyan-glow);">Ver no Mapa</a>

                        <?php if ($usuario['perfil'] === 'admin'): ?>
                            <a href="editar_equipamento.php?id=<?= $eq['id'] ?>" class="btn-acao">Editar</a>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Modal Operacional para Atualizar Status via Agente -->
    <div id="modal-status" class="modal-overlay">
        <div class="modal-content">
            <h2 class="tech-gradient-text" style="margin: 0 0 6px 0; font-size: 1.3rem;">Atualizar Situação Operacional</h2>
            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 18px;">
                Ativo: <strong id="modal-eq-nome" style="color: #fff;">--</strong> (<code id="modal-eq-serie">--</code>)
            </p>

            <form method="POST" action="atualizar_status.php">
                <input type="hidden" name="id" id="modal-eq-id">
                <input type="hidden" name="origem" value="index">

                <div style="margin-bottom: 14px; display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Nova Situação</label>
                    <select name="situacao" id="modal-situacao" class="filter-select" style="width: 100%;" required>
                        <option value="operacional">Operacional</option>
                        <option value="mau_funcionamento">Mau Funcionamento</option>
                        <option value="furtado">Furtado / Violado</option>
                        <option value="ainda_nao_instalado">Ainda Não Instalado</option>
                        <option value="desativado">Desativado</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px; display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Necessidade de Manutenção</label>
                    <select name="necessidade_manutencao" id="modal-manutencao" class="filter-select" style="width: 100%;" required>
                        <option value="nenhuma">Nenhuma</option>
                        <option value="imediata">Imediata (Urgente)</option>
                        <option value="agendada">Agendada / Preventiva</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px; display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Próxima Manutenção Prevista</label>
                    <input type="date" name="proxima_manutencao" id="modal-data-manutencao" class="filter-input" style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Justificativa Operacional (Auditoria)</label>
                    <textarea name="observacao" id="modal-observacao" class="filter-input" rows="3" style="width: 100%; resize: vertical;" placeholder="Informe a avaria encontrada, vistoria ou reparo..." required></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModalStatus()" class="btn-acao" style="padding: 8px 16px;">Cancelar</button>
                    <button type="submit" class="btn-tech" style="padding: 8px 18px;">Gravar no Histórico</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Lógica de Filtro Instantâneo
        const inputTexto = document.getElementById('filtro-texto');
        const selectTipo = document.getElementById('filtro-tipo');
        const selectSituacao = document.getElementById('filtro-situacao');
        const linhas = document.querySelectorAll('.linha-equipamento');
        const contador = document.getElementById('contador-linhas');

        function aplicarFiltros() {
            const texto = inputTexto.value.toLowerCase().trim();
            const tipo = selectTipo.value;
            const situacao = selectSituacao.value;
            let visiveis = 0;

            linhas.forEach(linha => {
                const linhaTipo = linha.getAttribute('data-tipo');
                const linhaSituacao = linha.getAttribute('data-situacao');
                const linhaBusca = linha.getAttribute('data-busca');

                const bateTexto = !texto || linhaBusca.includes(texto);
                const bateTipo = !tipo || linhaTipo === tipo;
                const bateSituacao = !situacao || linhaSituacao === situacao;

                if (bateTexto && bateTipo && bateSituacao) {
                    linha.style.display = '';
                    visiveis++;
                } else {
                    linha.style.display = 'none';
                }
            });

            contador.textContent = visiveis;
        }

        inputTexto.addEventListener('input', aplicarFiltros);
        selectTipo.addEventListener('change', aplicarFiltros);
        selectSituacao.addEventListener('change', aplicarFiltros);

        // Lógica do Modal de Status
        const modal = document.getElementById('modal-status');
        function abrirModalStatus(id, modelo, serie, situacao, manutencao, dataManut) {
            document.getElementById('modal-eq-id').value = id;
            document.getElementById('modal-eq-nome').textContent = modelo;
            document.getElementById('modal-eq-serie').textContent = serie;
            document.getElementById('modal-situacao').value = situacao;
            document.getElementById('modal-manutencao').value = manutencao;
            document.getElementById('modal-data-manutencao').value = dataManut || '';
            document.getElementById('modal-observacao').value = '';
            
            modal.style.display = 'flex';
        }

        function fecharModalStatus() {
            modal.style.display = 'none';
        }

        // Fecha ao clicar fora do cartão
        window.addEventListener('click', (e) => {
            if (e.target === modal) fecharModalStatus();
        });
    </script>
</body>
</html>