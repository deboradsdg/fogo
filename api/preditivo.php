<?php
// api/preditivo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/connection.php';

if (!getUsuarioLogado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$pdo = getFogoDatabase();

// 1. Coleta dados consolidados
$equipamentos = $pdo->query("SELECT * FROM equipamentos")->fetchAll(PDO::FETCH_ASSOC);
$agentes = $pdo->query("SELECT * FROM usuarios WHERE em_servico = 1")->fetchAll(PDO::FETCH_ASSOC);
$historicos = $pdo->query("SELECT * FROM historico_manutencoes")->fetchAll(PDO::FETCH_ASSOC);

// 2. Análise por Região / Bairro para o IVOR
$regioes = [];

foreach ($equipamentos as $eq) {
    $reg = $eq['bairro'] ?? 'Outros';
    if (!isset($regioes[$reg])) {
        $regioes[$reg] = [
            'nome' => $reg,
            'total_equipamentos' => 0,
            'inoperantes' => 0,
            'imediata' => 0,
            'agentes_presentes' => 0,
            'historico_falhas' => 0
        ];
    }
    $regioes[$reg]['total_equipamentos']++;
    if ($eq['situacao'] !== 'operacional') {
        $regioes[$reg]['inoperantes']++;
    }
    if ($eq['necessidade_manutencao'] === 'imediata') {
        $regioes[$reg]['imediata']++;
    }
}

// Vincula agentes por região declarada
foreach ($agentes as $ag) {
    $regAg = $ag['regiao'] ?? 'Centro';
    if (isset($regioes[$regAg])) {
        $regioes[$regAg]['agentes_presentes']++;
    }
}

// Histórico de panes por equipamento
$falhasPorEquipamento = [];
foreach ($historicos as $h) {
    $id = $h['equipamento_id'];
    $falhasPorEquipamento[$id] = ($falhasPorEquipamento[$id] ?? 0) + 1;
}

// 3. Cálculo do IVOR e Nível de Risco por Região
$analiseRegioes = [];
$recomendacoes = [];

foreach ($regioes as $nome => $r) {
    // Fórmula ponderada: peso por indisponibilidade, criticidade de manutenção e ausência de agentes
    $fatorIndisponibilidade = ($r['total_equipamentos'] > 0) ? ($r['inoperantes'] / $r['total_equipamentos']) * 50 : 0;
    $fatorUrgencia = $r['imediata'] * 20;
    $fatorEfetivo = ($r['agentes_presentes'] == 0 && $r['inoperantes'] > 0) ? 30 : 0;

    $scoreRisco = min(100, round($fatorIndisponibilidade + $fatorUrgencia + $fatorEfetivo));

    if ($scoreRisco >= 65) {
        $classificacao = 'Crítico';
        $cor = '#ef4444';
        $recomendacoes[] = [
            'tipo' => 'urgente',
            'regiao' => $nome,
            'acao' => "Deslocar reforço operacional imediato para {$nome}. Déficit de cobertura com {$r['inoperantes']} ativos inoperantes."
        ];
    } elseif ($scoreRisco >= 35) {
        $classificacao = 'Moderado';
        $cor = '#f59e0b';
    } else {
        $classificacao = 'Baixo';
        $cor = '#10b981';
    }

    $analiseRegioes[] = [
        'regiao' => $nome,
        'score' => $scoreRisco,
        'classificacao' => $classificacao,
        'cor' => $cor,
        'total' => $r['total_equipamentos'],
        'inoperantes' => $r['inoperantes'],
        'agentes' => $r['agentes_presentes']
    ];
}

// 4. Previsão de Risco de Falha Individual de Equipamento
$previsaoEquipamentos = [];
$dataAtual = new DateTime();

foreach ($equipamentos as $eq) {
    $falhasPassadas = $falhasPorEquipamento[$eq['id']] ?? 0;
    $probabilidadeFalha = 10; // Risco base 10%

    // Ajusta probabilidade baseado em histórico e próxima manutenção
    $probabilidadeFalha += ($falhasPassadas * 25);
    if ($eq['necessidade_manutencao'] === 'imediata') {
        $probabilidadeFalha = 95;
    } elseif ($eq['proxima_manutencao']) {
        $dataManut = new DateTime($eq['proxima_manutencao']);
        $diff = $dataAtual->diff($dataManut);
        if ($diff->invert === 0 && $diff->days <= 10) {
            $probabilidadeFalha += (10 - $diff->days) * 4;
        }
    }

    $probabilidadeFalha = min(99, $probabilidadeFalha);

    if ($probabilidadeFalha >= 60) {
        $previsaoEquipamentos[] = [
            'id' => $eq['id'],
            'modelo' => $eq['modelo'],
            'tipo' => $eq['tipo'],
            'serie' => $eq['numero_serie'],
            'local' => "{$eq['logradouro']} ({$eq['bairro']})",
            'probabilidade' => $probabilidadeFalha,
            'motivo' => ($eq['necessidade_manutencao'] === 'imediata') 
                ? 'Equipamento em estado crítico requerendo intervenção imediata.' 
                : "Recorrência histórica ({$falhasPassadas} falhas) e aproximação de revisão preventiva."
        ];
    }
}

// Ordena regiões por risco decrescente
usort($analiseRegioes, fn($a, $b) => $b['score'] <=> $a['score']);
usort($previsaoEquipamentos, fn($a, $b) => $b['probabilidade'] <=> $a['probabilidade']);

echo json_encode([
    'gerado_em' => date('d/m/Y H:i:s'),
    'indice_geral_cidade' => count($analiseRegioes) > 0 ? round(array_sum(array_column($analiseRegioes, 'score')) / count($analiseRegioes)) : 0,
    'regioes' => $analiseRegioes,
    'equipamentos_risco' => $previsaoEquipamentos,
    'recomendacoes' => $recomendacoes
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);