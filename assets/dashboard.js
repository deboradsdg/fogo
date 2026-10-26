// assets/dashboard.js
// Centro Integrado de Operações (C.I.O.) // F.O.G.O.

// 1. Inicializa o mapa com visão focada na área operacional de Marília
const map = L.map('mapa-fogo').setView([-22.2139, -49.9458], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors | F.O.G.O.',
    maxZoom: 19
}).addTo(map);

let markersLayer = L.layerGroup().addTo(map);
let dadosTelemetria = null;

// Cores dos marcadores por situação operacional
const getIcone = (tipo, situacao) => {
    let cor = '#10b981'; // Verde operacional padrão
    if (situacao === 'mau_funcionamento') cor = '#f59e0b';
    if (situacao === 'furtado') cor = '#ef4444';
    if (situacao === 'ainda_nao_instalado' || situacao === 'desativado') cor = '#64748b';
    if (tipo === 'agente') cor = '#a855f7';

    return L.divIcon({
        className: 'custom-pin',
        html: `<div style="background-color: ${cor}; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #ffffff; box-shadow: 0 0 10px ${cor};"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });
};

// 2. Carrega os dados da API
async function carregarTelemetria() {
    try {
        const res = await fetch('api/telemetria.php');
        if (!res.ok) throw new Error('Falha ao comunicar com a API');
        dadosTelemetria = await res.json();

        atualizarKPIs(dadosTelemetria.kpis);
        renderizarAlertas(dadosTelemetria.alertas || []);
        renderizarMarcadores();
    } catch (err) {
        console.error("Erro ao carregar telemetria:", err);
    }
}

// 3. Atualiza os cartões de indicadores
function atualizarKPIs(kpis) {
    if (!kpis) return;
    if (document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = kpis.total_equipamentos ?? '--';
    if (document.getElementById('kpi-operacionais')) document.getElementById('kpi-operacionais').textContent = kpis.operacionais ?? '--';
    if (document.getElementById('kpi-indisponiveis')) document.getElementById('kpi-indisponiveis').textContent = kpis.indisponiveis ?? '--';
    if (document.getElementById('kpi-taxa')) document.getElementById('kpi-taxa').textContent = (kpis.taxa_disponibilidade ?? '--') + '%';
    if (document.getElementById('kpi-agentes')) document.getElementById('kpi-agentes').textContent = kpis.agentes_ativos ?? kpis.agentes_em_servico ?? '--';
}

// 4. Renderiza a lista de alertas críticos
function renderizarAlertas(alertas) {
    const box = document.getElementById('container-alertas');
    if (!box) return;
    box.innerHTML = '';

    if (!alertas || alertas.length === 0) {
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

// 5. Renderiza Marcadores com Formulário de Atualização Operacional
function renderizarMarcadores() {
    if (!markersLayer || !dadosTelemetria) return;
    markersLayer.clearLayers();

    const filtroTipo = document.getElementById('filtro-tipo')?.value || 'todos';
    const filtroSituacao = document.getElementById('filtro-situacao')?.value || 'todas';

    // 5.1. Renderiza Equipamentos
    if (filtroTipo !== 'agente' && dadosTelemetria.equipamentos) {
        dadosTelemetria.equipamentos.forEach(eq => {
            if (!eq.latitude || !eq.longitude) return;

            if (filtroTipo !== 'todos' && eq.tipo !== filtroTipo) return;
            if (filtroSituacao !== 'todas' && eq.situacao !== filtroSituacao) return;

            const popupContent = `
                <div style="font-family: system-ui, sans-serif; min-width: 250px; color: #0c1024;">
                    <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 8px;">
                        <strong style="color: #0c1024; font-size: 0.95rem;">${eq.modelo}</strong> 
                        <span style="font-size: 0.75rem; color: #6b7280;">(${eq.tipo.toUpperCase()})</span><br>
                        <small style="color: #64748b;">S/N: ${eq.numero_serie}</small>
                    </div>

                    <div style="font-size: 0.8rem; line-height: 1.4; margin-bottom: 8px;">
                        <b>Local:</b> ${eq.logradouro || 'N/D'}, ${eq.referencia || ''}<br>
                        <b>Bairro:</b> ${eq.bairro || 'N/D'} (${eq.cidade_uf || 'Marília/SP'})<br>
                        <b>Situação Atual:</b> <span class="badge-status status-${eq.situacao}">${eq.situacao.replace('_', ' ')}</span><br>
                        <b>Manutenção:</b> ${eq.necessidade_manutencao.toUpperCase()}
                    </div>

                    <!-- Formulário Direto de Atualização de Status -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-top: 6px;">
                        <form method="POST" action="atualizar_status.php" onsubmit="return validarEnvioStatus(this);">
                            <input type="hidden" name="id" value="${eq.id}">
                            <input type="hidden" name="origem" value="mapa">

                            <label style="font-size: 0.7rem; font-weight: bold; color: #475569; display: block; margin-bottom: 3px;">MUDAR SITUAÇÃO:</label>
                            <select name="situacao" style="width: 100%; padding: 4px; font-size: 0.75rem; border-radius: 4px; border: 1px solid #cbd5e1; margin-bottom: 6px; background: #fff; color: #000;">
                                <option value="operacional" ${eq.situacao === 'operacional' ? 'selected' : ''}>Operacional</option>
                                <option value="mau_funcionamento" ${eq.situacao === 'mau_funcionamento' ? 'selected' : ''}>Mau Funcionamento</option>
                                <option value="furtado" ${eq.situacao === 'furtado' ? 'selected' : ''}>Furtado / Violado</option>
                                <option value="desativado" ${eq.situacao === 'desativado' ? 'selected' : ''}>Desativado</option>
                                <option value="ainda_nao_instalado" ${eq.situacao === 'ainda_nao_instalado' ? 'selected' : ''}>Ainda Não Instalado</option>
                            </select>

                            <select name="necessidade_manutencao" style="width: 100%; padding: 4px; font-size: 0.75rem; border-radius: 4px; border: 1px solid #cbd5e1; margin-bottom: 6px; background: #fff; color: #000;">
                                <option value="nenhuma" ${eq.necessidade_manutencao === 'nenhuma' ? 'selected' : ''}>Sem Manutenção</option>
                                <option value="imediata" ${eq.necessidade_manutencao === 'imediata' ? 'selected' : ''}>Manutenção Imediata</option>
                                <option value="agendada" ${eq.necessidade_manutencao === 'agendada' ? 'selected' : ''}>Manutenção Agendada</option>
                            </select>

                            <input type="text" name="observacao" placeholder="Justificativa da alteração..." required style="width: 95%; padding: 5px; font-size: 0.75rem; border-radius: 4px; border: 1px solid #cbd5e1; margin-bottom: 8px; background: #fff; color: #000;">

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <button type="submit" style="background: #f59e0b; color: #ffffff; border: none; padding: 5px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; cursor: pointer;">
                                    ⚡ Salvar Status
                                </button>
                                <a href="historico.php?id=${eq.id}" style="color: #6366f1; font-size: 0.75rem; text-decoration: underline; font-weight: bold;">
                                    Histórico &rarr;
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            L.marker([eq.latitude, eq.longitude], { icon: getIcone(eq.tipo, eq.situacao) })
                .bindPopup(popupContent)
                .addTo(markersLayer);
        });
    }

    // 5.2. Renderiza Agentes Operacionais
    if ((filtroTipo === 'todos' || filtroTipo === 'agente') && dadosTelemetria.agentes) {
        dadosTelemetria.agentes.forEach(ag => {
            if (!ag.latitude || !ag.longitude) return;

            const popupAgente = `
                <div style="font-family: system-ui, sans-serif; font-size: 0.85rem; color: #0c1024;">
                    <strong style="color: #7c3aed;">[AGENTE OPERACIONAL]</strong><br>
                    <b>Nome:</b> ${ag.nome}<br>
                    <b>Matrícula:</b> ${ag.matricula}<br>
                    <b>Corporação:</b> ${ag.corporacao}<br>
                    <b>Região de Patrulha:</b> ${ag.regiao || 'Centro'}
                </div>
            `;

            L.marker([ag.latitude, ag.longitude], { icon: getIcone('agente', 'operacional') })
                .bindPopup(popupAgente)
                .addTo(markersLayer);
        });
    }
}

// 6. Validação da justificativa antes de enviar
function validarEnvioStatus(form) {
    const obs = form.querySelector('input[name="observacao"]').value.trim();
    if (!obs) {
        alert('A justificativa da alteração é obrigatória para a auditoria.');
        return false;
    }
    return confirm('Confirmar alteração de status deste equipamento?');
}

// 7. Eventos de Filtros e Inicialização
document.getElementById('filtro-tipo').addEventListener('change', renderizarMarcadores);
document.getElementById('filtro-situacao').addEventListener('change', renderizarMarcadores);

// Força redimensionamento correto do mapa caso haja delay de layout
setTimeout(() => { map.invalidateSize(); }, 200);

carregarTelemetria();
setInterval(carregarTelemetria, 10000);