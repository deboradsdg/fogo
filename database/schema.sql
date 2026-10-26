-- Habilita integridade referencial
PRAGMA foreign_keys = ON;

-- 1. TABELA DE AGENTES E OPERADORES
-- Níveis de acesso: 'admin' (acesso total) ou 'agente' (leitura e atualização de status/manutenção)
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    matricula TEXT NOT NULL UNIQUE,
    corporacao TEXT NOT NULL, -- Ex: 'Agente de Trânsito', 'Guarda Municipal', 'Polícia Militar'
    email TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    perfil TEXT NOT NULL CHECK(perfil IN ('admin', 'agente')),
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABELA DE EQUIPAMENTOS
CREATE TABLE IF NOT EXISTS equipamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo TEXT NOT NULL, -- 'radar', 'camera', 'viatura', 'patinete', 'totem', 'balanca', etc.
    numero_serie TEXT NOT NULL UNIQUE,
    marca TEXT NOT NULL,
    modelo TEXT NOT NULL,
    
    -- Localização (física ou base de operação)
    cep TEXT,
    logradouro TEXT,
    referencia TEXT,    -- 'KM 203', 'Oposto ao 181', 'Base Central'
    bairro TEXT,
    cidade_uf TEXT NOT NULL,
    latitude REAL,
    longitude REAL,

    -- Estado operacional e controle
    situacao TEXT NOT NULL CHECK(situacao IN (
        'ainda_nao_instalado', 
        'operacional', 
        'mau_funcionamento', 
        'desativado', 
        'furtado'
    )),
    necessidade_manutencao TEXT NOT NULL CHECK(necessidade_manutencao IN (
        'nenhuma', 
        'imediata', 
        'agendada'
    )),
    proxima_manutencao DATE,

    -- Características particulares (Payload JSON dinâmico)
    -- Ex.: {"resolucao": "4K", "visao_noturna": true} ou {"tipo_radar": "velocidade", "limite_kmh": 60}
    especificacoes JSON DEFAULT '{}',

    atualizado_por INTEGER,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
);

-- 3. HISTÓRICO DE AUDITORIA E ATUALIZAÇÕES
CREATE TABLE IF NOT EXISTS historico_manutencoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    equipamento_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    situacao_anterior TEXT,
    situacao_nova TEXT,
    manutencao_status TEXT,
    observacao TEXT,
    registrado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipamento_id) REFERENCES equipamentos (id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
);

-- ==========================================
-- POPULAÇÃO INICIAL (SEMENTES DO SISTEMA)
-- ==========================================

-- Inserindo Administrador e Agentes (senhas de exemplo usando hash padrão)
INSERT INTO usuarios (nome, matricula, corporacao, email, senha_hash, perfil) VALUES
('Comandante Silva', 'ADM001', 'Centro Integrado de Operações', 'admin@fogo.gov.br', '$2y$10$wN0mK.T9c8dD1Gz.tP9e2.Jj1L6YjW6Z2mJ1sYh6D8r7M2HqKz2iW', 'admin'),
('Agente Souza', 'GT1042', 'Agente de Trânsito', 'souza@fogo.gov.br', '$2y$10$wN0mK.T9c8dD1Gz.tP9e2.Jj1L6YjW6Z2mJ1sYh6D8r7M2HqKz2iW', 'agente'),
('Guarda Ferreira', 'GM5520', 'Guarda Municipal', 'ferreira@fogo.gov.br', '$2y$10$wN0mK.T9c8dD1Gz.tP9e2.Jj1L6YjW6Z2mJ1sYh6D8r7M2HqKz2iW', 'agente');

-- Inserindo Equipamentos Variados
INSERT INTO equipamentos (
    tipo, numero_serie, marca, modelo,
    cep, logradouro, referencia, bairro, cidade_uf, latitude, longitude,
    situacao, necessidade_manutencao, proxima_manutencao, especificacoes
) VALUES 
(
    'radar', 'RAD-2026-8841', 'FiscalTech', 'Velocis-Pro',
    '17500-001', 'Av. das Esmeraldas', 'Oposto ao nº 181', 'Jardim Tangará', 'Marília/SP', -22.22812, -49.94582,
    'operacional', 'nenhuma', '2026-12-15',
    '{"modalidade": "velocidade_e_semaforo", "limite_kmh": 60, "cameras_acopladas": 2}'
),
(
    'camera', 'CAM-PTZ-00392', 'Axis', 'Q6135-LE',
    '17500-100', 'Rua Nove de Julho', 'Cruzamento central com Rua São Luiz', 'Centro', 'Marília/SP', -22.21394, -49.95011,
    'mau_funcionamento', 'imediata', '2026-10-25',
    '{"resolucao": "4K", "zoom_optico": "32x", "visao_noturna": true, "alcance_ir_metros": 250}'
),
(
    'viatura', 'VTR-GM-014', 'Renault', 'Duster 2025 Policial',
    '17509-020', 'Av. Tiradentes', 'Base da Guarda Municipal', 'Fragata', 'Marília/SP', -22.23550, -49.93880,
    'operacional', 'agendada', '2026-11-05',
    '{"placa": "FGO-2A26", "radio_tetra": true, "giroflex_led": true, "combustivel": "Flex"}'
),
(
    'patinete', 'PAT-MICROMOB-108', 'Segway-Ninebot', 'Max Plus IoT',
    '17515-000', 'Av. Vicente Ferreira', 'Em frente à pista de cooper', 'Cascata', 'Marília/SP', -22.22410, -49.93240,
    'furtado', 'imediata', NULL,
    '{"bateria_pct": 12, "rastreamento_gps_ativo": false, "trava_remota": true}'
),
(
    'balanca', 'BAL-ROD-002', 'Toledo', 'PesaCaminhão Dynamic 900',
    '17530-000', 'Rodovia SP-294', 'KM 451 Sentido Interior', 'Zona Rural', 'Marília/SP', -22.18950, -49.98210,
    'operacional', 'agendada', '2026-11-20',
    '{"capacidade_toneladas": 100, "pesagem_em_movimento": true, "calibracao_inmetro": "2026-04-10"}'
);