// assets/dashboard.js

// Inicializa o mapa com visão focada na área operacional
const map = L.map('mapa-fogo').setView([-22.22000, -49.94500], 14);

// Substitua o bloco atual do L.tileLayer por este:
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | F.O.G.O.',
    maxZoom: 19
}).addTo(map);

let markersLayer = L.layerGroup().addTo(map);
let dadosTelemetria = null;

// Ícones customizados com SVG
const getIcone = (tipo, situacao) => {
    let cor = '#10b981'; // Verde padrão operacional
    if (situacao === 'mau_funcionamento') cor = '#f59e0b';
    if (situacao === 'furtado') cor = '#ef4444';
    if (tipo === 'agente') cor = '#a855f7';

    return L.divIcon({
        className: 'custom-pin',
        html: `<div style="background-color: ${cor}; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #ffffff; box-shadow: 0 0 10px ${cor};"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });
};

// Carrega os dados da API
async function carregarTelemetria() {
    try {
        const res = await fetch('api/telemetria.php');
        dadosTelemetria = await res.json();

        atualizarKPIs(dadosTelemetria.kpis);
        renderizarAlertas(dadosTelemetria.alertas);
        renderizarMarcadores();
    } catch (err) {
        console.error("Erro ao carregar telemetria:", err);
    }
}

function atualizarKPIs(kpis) {
    document.getElementById('kpi-total').textContent = kpis.total_equipamentos;
    document.getElementById('kpi-operacionais').textContent = kpis.operacionais;
    document.getElementById('kpi-indisponiveis').textContent = kpis.indisponiveis;
    document.getElementById('kpi-taxa').textContent = kpis.taxa_disponibilidade + '%';
    document.getElementById('kpi-agentes').textContent = kpis.agentes_ativos;
}

function renderizarAlertas(alertas) {
    const box = document.getElementById('container-alertas');
    box.innerHTML = '';

    if (alertas.length === 0) {
        box.innerHTML = '<span style="font-size: 0.75rem; color: #10b981;">Nenhum evento crítico registrado.</span>';
        return;
    }

    alertas.forEach(a => {
        const div = document.createElement('div');
        div.className = 'alert-box';
        div.innerHTML = `<strong>${a.titulo}</strong><br><span style="font-size: 0.75rem;">${a.mensagem}</span>`;
        box.appendChild(div);
    });
}

function renderizarMarcadores() {
    markersLayer.clearLayers();

    const filtroTipo = document.getElementById('filtro-tipo').value;
    const filtroSituacao = document.getElementById('filtro-situacao').value;

    // 1. Renderiza Equipamentos
    if (filtroTipo !== 'agente') {
        dadosTelemetria.equipamentos.forEach(eq => {
            if (filtroTipo !== 'todos' && eq.tipo !== filtroTipo) return;
            if (filtroSituacao !== 'todas' && eq.situacao !== filtroSituacao) return;

            const popupContent = `
                <div style="font-family: sans-serif; min-width: 200px;">
                    <strong style="color: #0c1024; font-size: 0.95rem;">${eq.modelo} (${eq.tipo.toUpperCase()})</strong><br>
                    <small>S/N: ${eq.numero_serie}</small><br>
                    <hr style="margin: 6px 0;">
                    <b>Local:</b> ${eq.logradouro}, ${eq.referencia}<br>
                    <b>Bairro:</b> ${eq.bairro} - ${eq.cidade_uf}<br>
                    <b>Situação:</b> <span class="badge-status status-${eq.situacao}">${eq.situacao}</span><br>
                    <b>Manutenção:</b> ${eq.necessidade_manutencao}<br>
                    <div style="margin-top: 8px;">
                        <a href="historico.php?id=${eq.id}" style="color: #6366f1; text-decoration: none; font-weight: bold; font-size: 0.8rem;">Ver Histórico Completo &rarr;</a>
                    </div>
                </div>
            `;

            L.marker([eq.latitude, eq.longitude], { icon: getIcone(eq.tipo, eq.situacao) })
                .bindPopup(popupContent)
                .addTo(markersLayer);
        });
    }

    // 2. Renderiza Agentes em Atividade
    if (filtroTipo === 'todos' || filtroTipo === 'agente') {
        dadosTelemetria.agentes.forEach(ag => {
            const popupAgente = `
                <div style="font-family: sans-serif;">
                    <strong style="color: #6d28d9;">[AGENTE OPERACIONAL]</strong><br>
                    <b>Nome:</b> ${ag.nome}<br>
                    <b>Matrícula:</b> ${ag.matricula}<br>
                    <b>Corporação:</b> ${ag.corporacao}<br>
                    <b>Região de Patrulha:</b> ${ag.regiao}
                </div>
            `;

            L.marker([ag.latitude, ag.longitude], { icon: getIcone('agente', 'operacional') })
                .bindPopup(popupAgente)
                .addTo(markersLayer);
        });
    }
}

// Ouvintes de Filtros
document.getElementById('filtro-tipo').addEventListener('change', renderizarMarcadores);
document.getElementById('filtro-situacao').addEventListener('change', renderizarMarcadores);

// Carregamento inicial e atualização a cada 10 segundos
carregarTelemetria();
setInterval(carregarTelemetria, 10000);