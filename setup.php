<?php
// setup.php - Instalador Oficial e População F.O.G.O. (com Suporte ao C.I.O. & Mapa)

$dbFile = __DIR__ . '/fogo.db';

// 1. Conexão PDO com SQLite
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    die("Falha ao inicializar banco SQLite: " . $e->getMessage());
}

// 2. Estrutura DDL das Tabelas
$pdo->exec("
    DROP TABLE IF EXISTS historico_manutencoes;
    DROP TABLE IF EXISTS equipamentos;
    DROP TABLE IF EXISTS usuarios;

    CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        matricula TEXT NOT NULL UNIQUE,
        corporacao TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        senha_hash TEXT NOT NULL,
        perfil TEXT NOT NULL CHECK(perfil IN ('admin', 'agente')),
        ativo INTEGER NOT NULL DEFAULT 1,
        latitude REAL,
        longitude REAL,
        em_servico INTEGER NOT NULL DEFAULT 1,
        regiao TEXT DEFAULT 'Centro',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE equipamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        numero_serie TEXT NOT NULL UNIQUE,
        marca TEXT NOT NULL,
        modelo TEXT NOT NULL,
        cep TEXT,
        logradouro TEXT,
        referencia TEXT,
        bairro TEXT,
        cidade_uf TEXT NOT NULL,
        latitude REAL,
        longitude REAL,
        situacao TEXT NOT NULL CHECK(situacao IN ('ainda_nao_instalado', 'operacional', 'mau_funcionamento', 'desativado', 'furtado')),
        necessidade_manutencao TEXT NOT NULL CHECK(necessidade_manutencao IN ('nenhuma', 'imediata', 'agendada')),
        proxima_manutencao DATE,
        especificacoes JSON DEFAULT '{}',
        atualizado_por INTEGER,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
    );

    CREATE TABLE historico_manutencoes (
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
");

// 3. Usuários / Agentes Georreferenciados
$senhaHash = password_hash('senha123', PASSWORD_DEFAULT);

$stmtUsuario = $pdo->prepare("
    INSERT INTO usuarios (nome, matricula, corporacao, email, senha_hash, perfil, ativo, latitude, longitude, em_servico, regiao)
    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
");

$usuarios = [
    ['Comandante Silva', 'ADM001', 'Centro Integrado de Operações', 'admin@fogo.gov.br', $senhaHash, 'admin', -22.21394, -49.95011, 1, 'Centro'],
    ['Agente Souza', 'GT1042', 'Agente de Trânsito', 'souza@fogo.gov.br', $senhaHash, 'agente', -22.21550, -49.94800, 1, 'Centro'],
    ['Guarda Ferreira', 'GM5520', 'Guarda Municipal', 'ferreira@fogo.gov.br', $senhaHash, 'agente', -22.22100, -49.93900, 1, 'Fragata']
];

foreach ($usuarios as $u) {
    $stmtUsuario->execute($u);
}

// 4. Equipamentos com Coordenadas para o Mapa de Operações
$stmtEq = $pdo->prepare("
    INSERT INTO equipamentos (
        tipo, numero_serie, marca, modelo,
        cep, logradouro, referencia, bairro, cidade_uf, latitude, longitude,
        situacao, necessidade_manutencao, proxima_manutencao, especificacoes, atualizado_por
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
");

$equipamentos = [
    [
        'radar', 'RAD-2026-8841', 'FiscalTech', 'Velocis-Pro',
        '17500-001', 'Av. das Esmeraldas', 'Oposto ao nº 181', 'Jardim Tangará', 'Marília/SP', -22.22812, -49.94582,
        'operacional', 'nenhuma', '2026-12-15',
        json_encode(['modalidade' => 'velocidade_e_semaforo', 'limite_kmh' => 60, 'cameras_acopladas' => 2])
    ],
    [
        'camera', 'CAM-PTZ-00392', 'Axis', 'Q6135-LE',
        '17500-100', 'Rua Nove de Julho', 'Cruzamento com Rua São Luiz', 'Centro', 'Marília/SP', -22.21394, -49.95011,
        'mau_funcionamento', 'imediata', '2026-10-25',
        json_encode(['resolucao' => '4K', 'zoom_optico' => '32x', 'visao_noturna' => true, 'alcance_ir_metros' => 250])
    ],
    [
        'viatura', 'VTR-GM-014', 'Renault', 'Duster 2025 Policial',
        '17509-020', 'Av. Tiradentes', 'Base da Guarda Municipal', 'Fragata', 'Marília/SP', -22.23550, -49.93880,
        'operacional', 'agendada', '2026-11-05',
        json_encode(['placa' => 'FGO-2A26', 'radio_tetra' => true, 'giroflex_led' => true])
    ],
    [
        'patinete', 'PAT-MICROMOB-108', 'Segway-Ninebot', 'Max Plus IoT',
        '17515-000', 'Av. Vicente Ferreira', 'Pista de cooper', 'Cascata', 'Marília/SP', -22.22410, -49.93240,
        'furtado', 'imediata', null,
        json_encode(['bateria_pct' => 12, 'rastreamento_gps_ativo' => false, 'trava_remota' => true])
    ],
    [
        'balanca', 'BAL-ROD-002', 'Toledo', 'PesaCaminhão 900',
        '17530-000', 'Rodovia SP-294', 'KM 451 Sentido Interior', 'Zona Rural', 'Marília/SP', -22.18950, -49.98210,
        'operacional', 'agendada', '2026-11-20',
        json_encode(['capacidade_toneladas' => 100, 'pesagem_em_movimento' => true])
    ]
];

foreach ($equipamentos as $eq) {
    $stmtEq->execute($eq);
}

// 5. Histórico Inicial de Auditoria
$stmtHist = $pdo->prepare("
    INSERT INTO historico_manutencoes (equipamento_id, usuario_id, situacao_anterior, situacao_nova, manutencao_status, observacao)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmtHist->execute([2, 2, 'operacional', 'mau_funcionamento', 'imediata', 'Câmera perdeu comunicação após descarga elétrica.']);
$stmtHist->execute([4, 3, 'operacional', 'furtado', 'imediata', 'Patinete retirado do perímetro de tolerância com sinal cortado.']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Setup Concluído | F.O.G.O.</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .setup-card { max-width: 520px; padding: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="tech-card setup-card">
        <h1 class="tech-gradient-text" style="font-size: 1.8rem; margin-bottom: 10px;">F.O.G.O. Instalado!</h1>
        <p style="color: var(--text-secondary); margin-bottom: 20px;">
            Banco <code>fogo.db</code> recriado com tabelas de equipamentos, histórico e agentes com suporte a GPS e telemetria.
        </p>

        <div style="background: rgba(12, 16, 36, 0.8); border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; text-align: left; font-size: 0.85rem; margin-bottom: 25px;">
            <p><strong>Admin:</strong> <code>admin@fogo.gov.br</code></p>
            <p><strong>Agente:</strong> <code>souza@fogo.gov.br</code></p>
            <p><strong>Senha:</strong> <code>senha123</code></p>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="login.php" class="btn-tech" style="flex: 1; justify-content: center;">Ir para Login</a>
            <a href="mapa.php" class="btn-tech" style="flex: 1; justify-content: center; background: linear-gradient(135deg, #06b6d4, #3b82f6);">Abrir Mapa C.I.O.</a>
        </div>
    </div>
</body>
</html>