<?php
// api/telemetria.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/connection.php';

if (!getUsuarioLogado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$pdo = getFogoDatabase();

// 1. Busca todos os equipamentos com coordenadas válidas
$stmtEq = $pdo->query("
    SELECT e.*, u.nome AS responsavel_nome 
    FROM equipamentos e
    LEFT JOIN usuarios u ON e.atualizado_por = u.id
    WHERE e.latitude IS NOT NULL AND e.longitude IS NOT NULL
");
$equipamentos = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

// Decodifica especificações JSON
foreach ($equipamentos as &$eq) {
    $eq['especificacoes'] = json_decode($eq['especificacoes'] ?? '{}', true);
}

// 2. Busca agentes em serviço com localização
$stmtAg = $pdo->query("
    SELECT id, nome, matricula, corporacao, email, perfil, latitude, longitude, em_servico, regiao 
    FROM usuarios 
    WHERE em_servico = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL
");
$agentes = $stmtAg->fetchAll(PDO::FETCH_ASSOC);

// 3. Cálculo de Indicadores Gerais (KPIs)
$totalEquipamentos = count($equipamentos);
$operacionais = 0;
$criticos = 0;
$alertas = [];

$dataAtual = new DateTime();

foreach ($equipamentos as $eq) {
    if ($eq['situacao'] === 'operacional') {
        $operacionais++;
    } else {
        $criticos++;
    }

    // Alertas de Manutenção Crítica e Imediata
    if ($eq['necessidade_manutencao'] === 'imediata') {
        $alertas[] = [
            'tipo' => 'critico',
            'titulo' => "Manutenção Imediata: {$eq['modelo']} ({$eq['numero_serie']})",
            'mensagem' => "Situação: {$eq['situacao']} em {$eq['logradouro']} ({$eq['bairro']})."
        ];
    } elseif ($eq['proxima_manutencao']) {
        $dataManut = new DateTime($eq['proxima_manutencao']);
        $intervalo = $dataAtual->diff($dataManut);
        if ($intervalo->invert === 0 && $intervalo->days <= 7) {
            $alertas[] = [
                'tipo' => 'aviso',
                'titulo' => "Revisão Próxima: {$eq['modelo']}",
                'mensagem' => "Manutenção agendada para {$eq['proxima_manutencao']} ({$intervalo->days} dias restantes)."
            ];
        }
    }
}

$taxaDisponibilidade = $totalEquipamentos > 0 ? round(($operacionais / $totalEquipamentos) * 100, 1) : 0;

echo json_encode([
    'kpis' => [
        'total_equipamentos' => $totalEquipamentos,
        'operacionais' => $operacionais,
        'indisponiveis' => $criticos,
        'taxa_disponibilidade' => $taxaDisponibilidade,
        'agentes_ativos' => count($agentes)
    ],
    'alertas' => $alertas,
    'equipamentos' => $equipamentos,
    'agentes' => $agentes
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);