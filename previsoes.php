<?php
// previsoes.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

$usuario = getUsuarioLogado();
if (!$usuario) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROMETHEUS // Análise Preditiva & Apoio à Decisão</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .preditivo-container {
            max-width: 1500px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .card-metrica {
            background: rgba(14, 20, 44, 0.85);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .painel-duplo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .painel-duplo { grid-template-columns: 1fr; }
        }

        .tabela-preditiva {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        .tabela-preditiva th, .tabela-preditiva td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .tabela-preditiva th {
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .barra-risco {
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
            margin-top: 4px;
        }

        .barra-risco-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease-in-out;
        }

        .recomendacao-item {
            background: rgba(239, 68, 68, 0.08);
            border-left: 4px solid #ef4444;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <header class="app-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid var(--border-color); background: rgba(7, 10, 24, 0.9);">
        <div>
            <h1 class="tech-gradient-text" style="font-size: 1.4rem; margin: 0;">PROMETHEUS // MOTOR PREDITIVO</h1>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">INTELIGÊNCIA COMPUTACIONAL & PREVISÃO DE CENÁRIOS OPERACIONAIS</span>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="mapa.php" class="btn-tech" style="padding: 6px 14px; font-size: 0.8rem;">Mapa C.I.O.</a>
            <a href="index.php" class="btn-tech" style="padding: 6px 14px; font-size: 0.8rem;">Inventário</a>
            <span style="font-size: 0.85rem; color: var(--cyan-glow);">Agente: <?= htmlspecialchars($usuario['nome']) ?></span>
            <a href="logout.php" style="color: #ef4444; font-size: 0.8rem; text-decoration: none;">Sair</a>
        </div>
    </header>

    <div class="preditivo-container">

        <!-- Banner com a Metodologia Explicável -->
        <div class="card-metrica" style="border-left: 4px solid var(--cyan-glow);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <strong style="color: #fff; font-size: 1rem;">Critérios e Explicabilidade do Modelo Preditivo</strong>
                <span id="data-geracao" style="font-size: 0.75rem; color: var(--text-secondary);">Atualizando...</span>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0; line-height: 1.4;">
                O modelo estima a vulnerabilidade calculando a proporção de ativos inoperantes, a urgência de manutenção cadastrada, a proximidade da janela de revisão e a cobertura de agentes em serviço. O objetivo é subsidiar decisões táticas antes que pontos cegos de fiscalização ou segurança afetem as vias urbanas.
            </p>
        </div>

        <!-- Recomendações Táticas do PROMETHEUS -->
        <div class="card-metrica">
            <h3 style="font-size: 0.95rem; color: var(--cyan-glow); text-transform: uppercase; margin: 0 0 10px 0;">Recomendações e Despachos Táticos Sugeridos</h3>
            <div id="container-recomendacoes">
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Analisando telemetria...</span>
            </div>
        </div>

        <!-- Painel Duplo: Regiões de Risco vs Hardware Degradado -->
        <div class="painel-duplo">
            
            <!-- Índice de Risco por Região -->
            <div class="card-metrica">
                <h3 style="font-size: 0.95rem; color: #fff; text-transform: uppercase; margin: 0 0 8px 0;">Índice de Vulnerabilidade por Região (IVOR)</h3>
                <table class="tabela-preditiva">
                    <thead>
                        <tr>
                            <th>Região / Bairro</th>
                            <th>Efetivo</th>
                            <th>Inoperantes</th>
                            <th style="width: 140px;">Nível de Risco</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-regioes">
                        <tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">Carregando dados...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Previsão de Falha em Equipamentos -->
            <div class="card-metrica">
                <h3 style="font-size: 0.95rem; color: #fff; text-transform: uppercase; margin: 0 0 8px 0;">Risco de Pane / Intervenção Necessária (7 Dias)</h3>
                <table class="tabela-preditiva">
                    <thead>
                        <tr>
                            <th>Ativo / Local</th>
                            <th>Probabilidade</th>
                            <th>Diagnóstico Sugerido</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-equipamentos">
                        <tr><td colspan="3" style="text-align: center; color: var(--text-secondary);">Calculando probabilidades...</td></tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <script>
        async function carregarPrevisoes() {
            try {
                const res = await fetch('api/preditivo.php');
                const data = await res.json();

                document.getElementById('data-geracao').textContent = 'Última inferência: ' + data.gerado_em;

                // 1. Renderiza Recomendações
                const recBox = document.getElementById('container-recomendacoes');
                recBox.innerHTML = '';
                if (data.recomendacoes.length === 0) {
                    recBox.innerHTML = '<div style="color: #10b981; font-size: 0.85rem;">Nenhum remanejamento crítico necessário no momento. Operação equilibrada.</div>';
                } else {
                    data.recomendacoes.forEach(r => {
                        const div = document.createElement('div');
                        div.className = 'recomendacao-item';
                        div.innerHTML = `<strong>[RECOMENDAÇÃO TÁTICA // ${r.regiao.toUpperCase()}]</strong><br>${r.acao}`;
                        recBox.appendChild(div);
                    });
                }

                // 2. Renderiza Regiões
                const tbodyReg = document.getElementById('tbody-regioes');
                tbodyReg.innerHTML = '';
                data.regioes.forEach(reg => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${reg.regiao}</strong></td>
                        <td>${reg.agentes} agente(s)</td>
                        <td>${reg.inoperantes} / ${reg.total}</td>
                        <td>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem;">
                                <span style="color: ${reg.cor}; font-weight: bold;">${reg.classificacao}</span>
                                <span>${reg.score}%</span>
                            </div>
                            <div class="barra-risco">
                                <div class="barra-risco-fill" style="width: ${reg.score}%; background: ${reg.cor};"></div>
                            </div>
                        </td>
                    `;
                    tbodyReg.appendChild(tr);
                });

                // 3. Renderiza Equipamentos em Risco
                const tbodyEq = document.getElementById('tbody-equipamentos');
                tbodyEq.innerHTML = '';
                if (data.equipamentos_risco.length === 0) {
                    tbodyEq.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #10b981;">Nenhum equipamento em limiar crítico de falha.</td></tr>';
                } else {
                    data.equipamentos_risco.forEach(eq => {
                        const tr = document.createElement('tr');
                        const cor = eq.probabilidade >= 80 ? '#ef4444' : '#f59e0b';
                        tr.innerHTML = `
                            <td>
                                <strong>${eq.modelo}</strong><br>
                                <span style="font-size: 0.7rem; color: var(--text-secondary);">${eq.local}</span>
                            </td>
                            <td>
                                <span style="color: ${cor}; font-weight: bold;">${eq.probabilidade}%</span>
                            </td>
                            <td style="font-size: 0.8rem; color: #d1d5db;">${eq.motivo}</td>
                        `;
                        tbodyEq.appendChild(tr);
                    });
                }

            } catch (err) {
                console.error("Erro ao carregar previsões:", err);
            }
        }

        carregarPrevisoes();
        setInterval(carregarPrevisoes, 15000);
    </script>
</body>
</html>