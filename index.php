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
    <style>
        .layout-hub {
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Barra de Navegação Global */
        .global-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(10, 15, 35, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        .nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: #d1d5db;
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            color: #fff;
            border-color: var(--cyan-glow);
            background: rgba(6, 182, 212, 0.15);
            box-shadow: 0 0 10px rgba(6, 182, 212, 0.3);
        }

        .nav-btn.active {
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            color: #fff;
            border-color: transparent;
        }

        /* Banner de Acesso Rápido às Telas Especializadas */
        .hub-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }

        .hub-action-card {
            background: rgba(14, 20, 44, 0.85);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            position: relative;
            overflow: hidden;
        }

        .hub-action-card:hover {
            border-color: rgba(6, 182, 212, 0.5);
        }

        .hub-action-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
        }

        .hub-action-card.mapa::before { background: linear-gradient(90deg, #06b6d4, #3b82f6); }
        .hub-action-card.previsoes::before { background: linear-gradient(90deg, #ec4899, #8b5cf6); }
        .hub-action-card.gestao::before { background: linear-gradient(90deg, #10b981, #06b6d4); }

        /* Barra de Filtros Rápida */
        .filter-bar {
            background: rgba(14, 20, 44, 0.7);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-input, .filter-select {
            background: #090d20;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--cyan-glow);
        }

        /* Tabela de Equipamentos */
        .tabela-container {
            background: rgba(14, 20, 44, 0.85);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .tabela-dados {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .tabela-dados th, .tabela-dados td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .tabela-dados th {
            background: rgba(7, 10, 24, 0.6);
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-operacional { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .status-mau_funcionamento { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .status-furtado { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .status-ainda_nao_instalado { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid #9ca3af; }

        .btn-acao {
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            color: #fff;
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.05);
        }
        .btn-acao:hover {
            border-color: var(--cyan-glow);
            color: var(--cyan-glow);
        }
    </style>
</head>
<body>

    <div class="layout-hub">
        
        <!-- 1. Barra de Navegação Global Unificada -->
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

        <!-- 2. Atalhos para Telas Especializadas com Indicadores -->
        <section class="hub-cards">
            
            <!-- Card Mapa -->
            <div class="hub-action-card mapa">
                <div>
                    <span style="font-size: 0.75rem; color: #38bdf8; text-transform: uppercase; font-weight: 700;">Fase 2 // Telemetria Geoespacial</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">Centro de Operações (C.I.O.)</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Visualização cartográfica em tempo real dos <?= $totalEquipamentos ?> ativos e <?= $agentesContagem ?> agentes patrulhando a cidade de Marília.
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: #10b981;"><strong><?= $operacionais ?></strong> ativos online</span>
                    <a href="mapa.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 14px;">Abrir Mapa &rarr;</a>
                </div>
            </div>

            <!-- Card Previsões -->
            <div class="hub-action-card previsoes">
                <div>
                    <span style="font-size: 0.75rem; color: #c084fc; text-transform: uppercase; font-weight: 700;">Fase 3 // Inteligência Preditiva</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">PROMETHEUS Engine</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Cálculo do Índice IVOR por região, antecipação de panes de hardware nos próximos 7 dias e recomendações táticas de despacho.
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: <?= $criticos > 0 ? '#ef4444' : '#10b981' ?>;">
                        <strong><?= $criticos ?></strong> manutenções críticas
                    </span>
                    <a href="previsoes.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 14px; background: linear-gradient(135deg, #a855f7, #ec4899);">Ver Previsões &rarr;</a>
                </div>
            </div>

            <!-- Card Ações Administrativas -->
            <div class="hub-action-card gestao">
                <div>
                    <span style="font-size: 0.75rem; color: #34d399; text-transform: uppercase; font-weight: 700;">Fase 1 // Cadastro & Auditoria</span>
                    <h2 style="font-size: 1.2rem; color: #fff; margin: 6px 0;">Controle de Ativos</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                        Gestão detalhada do catálogo de hardware IoT, configurações de radar/câmeras em JSON e trilha de auditoria.
                    </p>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <?php if ($usuario['perfil'] === 'admin'): ?>
                        <a href="equipamento_form.php" class="btn-tech" style="font-size: 0.8rem; padding: 6px 12px;">+ Equipamento</a>
                    <?php endif; ?>
                    <a href="setup.php" onclick="return confirm('Deseja recriar o banco com os dados padrões?');" class="btn-acao" style="font-size: 0.8rem; padding: 6px 12px;">Resetar Dados</a>
                </div>
            </div>

        </section>

        <!-- 3. Barra de Filtros Rápidos Integrados -->
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
                </select>
            </div>

            <div style="font-size: 0.8rem; color: var(--text-secondary);">
                Exibindo <span id="contador-linhas" style="color: #fff; font-weight: bold;"><?= $totalEquipamentos ?></span> equipamentos cadastrados
            </div>
        </section>

        <!-- 4. Tabela de Equipamentos Filtrável -->
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
                        <th style="text-align: right;">Ações Detalhadas</th>
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
                            <a href="historico.php?id=<?= $eq['id'] ?>" class="btn-acao" title="Linha do Tempo de Auditoria">Histórico</a>
                            <a href="mapa.php?lat=<?= $eq['latitude'] ?>&lng=<?= $eq['longitude'] ?>" class="btn-acao" style="border-color: var(--cyan-glow); color: var(--cyan-glow);" title="Centralizar no Mapa C.I.O.">Ver no Mapa</a>
                            <?php if ($usuario['perfil'] === 'admin'): ?>
                                <a href="equipamento_form.php?id=<?= $eq['id'] ?>" class="btn-acao">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

    </div>

    <!-- Script de Filtro Instantâneo sem recarregar tela -->
    <script>
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
    </script>
</body>
</html>