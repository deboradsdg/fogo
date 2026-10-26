<?php
// mapa.php
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
    <title>C.I.O. Mapa Operacional | F.O.G.O.</title>
    <link rel="stylesheet" href="assets/style.css">
    
    <!-- Leaflet.js CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .kpi-card {
            background: rgba(14, 20, 44, 0.85);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .kpi-card .titulo {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .kpi-card .valor {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .map-section {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
            height: 650px;
        }

        .sidebar-controls {
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: rgba(14, 20, 44, 0.85);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            overflow-y: auto;
        }

        #mapa-fogo {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 25px rgba(0,0,0,0.4);
        }

        .alert-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-operacional { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .status-mau_funcionamento { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .status-furtado { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .status-ainda_nao_instalado { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid #9ca3af; }
    </style>
</head>
<body>

    <!-- Header / Navbar -->
    <header class="app-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid var(--border-color); background: rgba(7, 10, 24, 0.9);">
        <div>
            <h1 class="tech-gradient-text" style="font-size: 1.5rem; margin: 0;">F.O.G.O. // C.I.O.</h1>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">CENTRO INTEGRADO DE OPERAÇÕES & TELEMETRIA</span>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="index.php" class="btn-tech" style="padding: 6px 14px; font-size: 0.8rem;">Visualização Tabela</a>
            <span style="font-size: 0.85rem; color: var(--cyan-glow);">Agente: <?= htmlspecialchars($usuario['nome']) ?></span>
            <a href="logout.php" style="color: #ef4444; font-size: 0.8rem; text-decoration: none;">Sair</a>
        </div>
    </header>

    <div class="dashboard-container">
        
        <!-- KPIs Principais -->
        <div class="kpi-row">
            <div class="kpi-card">
                <span class="titulo">Equipamentos Totais</span>
                <span class="valor" id="kpi-total">--</span>
            </div>
            <div class="kpi-card">
                <span class="titulo">Operacionais</span>
                <span class="valor" style="color: #10b981;" id="kpi-operacionais">--</span>
            </div>
            <div class="kpi-card">
                <span class="titulo">Indisponíveis / Atenção</span>
                <span class="valor" style="color: #ef4444;" id="kpi-indisponiveis">--</span>
            </div>
            <div class="kpi-card">
                <span class="titulo">Disponibilidade</span>
                <span class="valor" style="color: var(--cyan-glow);" id="kpi-taxa">--%</span>
            </div>
            <div class="kpi-card">
                <span class="titulo">Agentes em Campo</span>
                <span class="valor" style="color: #a855f7;" id="kpi-agentes">--</span>
            </div>
        </div>

        <!-- Painel Central com Filtros e Mapa -->
        <div class="map-section">
            
            <!-- Painel Lateral de Filtros e Alertas Críticos -->
            <aside class="sidebar-controls">
                <h3 style="font-size: 0.9rem; color: var(--cyan-glow); text-transform: uppercase;">Filtros de Visibilidade</h3>
                
                <div class="form-group">
                    <label style="font-size: 0.75rem;">Tipo de Ativo</label>
                    <select id="filtro-tipo" style="width: 100%; padding: 6px; background: #0c1024; border: 1px solid var(--border-color); color: #fff; border-radius: 4px;">
                        <option value="todos">Todos os Ativos</option>
                        <option value="radar">Radares de Fiscalização</option>
                        <option value="camera">Câmeras de Videomonitoramento</option>
                        <option value="viatura">Viaturas Policiais / Guarda</option>
                        <option value="patinete">Micromobilidade / Patinetes</option>
                        <option value="balanca">Balanças Rodoviárias</option>
                        <option value="agente">Apenas Agentes Operacionais</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.75rem;">Situação Operacional</label>
                    <select id="filtro-situacao" style="width: 100%; padding: 6px; background: #0c1024; border: 1px solid var(--border-color); color: #fff; border-radius: 4px;">
                        <option value="todas">Todas as Situações</option>
                        <option value="operacional">Operacional</option>
                        <option value="mau_funcionamento">Mau Funcionamento</option>
                        <option value="furtado">Furtado / Violado</option>
                        <option value="ainda_nao_instalado">Ainda Não Instalado</option>
                    </select>
                </div>

                <h3 style="font-size: 0.85rem; color: #ef4444; text-transform: uppercase; margin-top: 10px;">Alertas do Sistema (PROMETHEUS)</h3>
                <div id="container-alertas" style="max-height: 250px; overflow-y: auto;">
                    <!-- Preenchido via JavaScript -->
                </div>
            </aside>

            <!-- Elemento do Mapa -->
            <main id="mapa-fogo"></main>
        </div>
    </div>

    <!-- Script de Inicialização do Mapa -->
    <script src="assets/dashboard.js"></script>
</body>
</html>