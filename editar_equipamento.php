<?php
// editar_equipamento.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

// Apenas administradores podem editar a totalidade das características
exigirAdmin();

$pdo = getFogoDatabase();
$usuario = getUsuarioLogado();

$idEquipamento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$idEquipamento) {
    header('Location: index.php');
    exit;
}

$erro = null;

// 1. Processamento da Atualização (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $numeroSerie = trim($_POST['numero_serie'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');

    $cep = trim($_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidadeUf = trim($_POST['cidade_uf'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    $situacao = trim($_POST['situacao'] ?? '');
    $necessidadeManutencao = trim($_POST['necessidade_manutencao'] ?? '');
    $proximaManutencao = !empty($_POST['proxima_manutencao']) ? trim($_POST['proxima_manutencao']) : null;
    $observacaoAuditoria = trim($_POST['observacao_auditoria'] ?? '');

    // Montagem do JSON dinâmico
    $chavesSpec = $_POST['spec_chave'] ?? [];
    $valoresSpec = $_POST['spec_valor'] ?? [];
    $especificacoesArray = [];

    if (is_array($chavesSpec) && is_array($valoresSpec)) {
        for ($i = 0; $i < count($chavesSpec); $i++) {
            $chave = trim($chavesSpec[$i]);
            $valor = trim($valoresSpec[$i]);

            if ($chave !== '') {
                if (strtolower($valor) === 'true') {
                    $especificacoesArray[$chave] = true;
                } elseif (strtolower($valor) === 'false') {
                    $especificacoesArray[$chave] = false;
                } elseif (is_numeric($valor)) {
                    $especificacoesArray[$chave] = strpos($valor, '.') !== false ? (float)$valor : (int)$valor;
                } else {
                    $especificacoesArray[$chave] = $valor;
                }
            }
        }
    }

    $especificacoesJson = json_encode($especificacoesArray, JSON_UNESCAPED_UNICODE);

    if (empty($tipo) || empty($numeroSerie) || empty($marca) || empty($modelo) || empty($cidadeUf) || empty($situacao)) {
        $erro = 'Preencha todos os campos obrigatórios (*).';
    } else {
        try {
            $pdo->beginTransaction();

            // Busca estado anterior para histórico
            $stmtAntigo = $pdo->prepare('SELECT situacao, necessidade_manutencao FROM equipamentos WHERE id = :id');
            $stmtAntigo->execute([':id' => $idEquipamento]);
            $estadoAnterior = $stmtAntigo->fetch();

            $stmtUpdate = $pdo->prepare('
                UPDATE equipamentos SET
                    tipo = :tipo,
                    numero_serie = :numero_serie,
                    marca = :marca,
                    modelo = :modelo,
                    cep = :cep,
                    logradouro = :logradouro,
                    referencia = :referencia,
                    bairro = :bairro,
                    cidade_uf = :cidade_uf,
                    latitude = :latitude,
                    longitude = :longitude,
                    situacao = :situacao,
                    necessidade_manutencao = :necessidade_manutencao,
                    proxima_manutencao = :proxima_manutencao,
                    especificacoes = :especificacoes,
                    atualizado_por = :atualizado_por,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = :id
            ');

            $stmtUpdate->execute([
                ':tipo'                   => $tipo,
                ':numero_serie'           => $numeroSerie,
                ':marca'                  => $marca,
                ':modelo'                 => $modelo,
                ':cep'                    => $cep,
                ':logradouro'             => $logradouro,
                ':referencia'             => $referencia,
                ':bairro'                 => $bairro,
                ':cidade_uf'              => $cidadeUf,
                ':latitude'               => $latitude,
                ':longitude'              => $longitude,
                ':situacao'               => $situacao,
                ':necessidade_manutencao' => $necessidadeManutencao,
                ':proxima_manutencao'     => $proximaManutencao,
                ':especificacoes'         => $especificacoesJson,
                ':atualizado_por'         => $usuario['id'],
                ':id'                     => $idEquipamento
            ]);

            // Registra auditoria caso tenha havido alteração de status/manutenção ou inclusão de observação
            $stmtHist = $pdo->prepare('
                INSERT INTO historico_manutencoes (
                    equipamento_id, usuario_id, situacao_anterior, situacao_nova, manutencao_status, observacao
                ) VALUES (
                    :equipamento_id, :usuario_id, :situacao_anterior, :situacao_nova, :manutencao_status, :observacao
                )
            ');

            $stmtHist->execute([
                ':equipamento_id'    => $idEquipamento,
                ':usuario_id'        => $usuario['id'],
                ':situacao_anterior' => $estadoAnterior['situacao'] ?? null,
                ':situacao_nova'     => $situacao,
                ':manutencao_status' => $necessidadeManutencao,
                ':observacao'        => !empty($observacaoAuditoria) ? $observacaoAuditoria : 'Edição estrutural realizada pelo administrador.'
            ]);

            $pdo->commit();
            header('Location: index.php?msg=equipamento_atualizado');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                $erro = 'O número de série fornecido já pertence a outro equipamento.';
            } else {
                $erro = 'Falha ao salvar modificações: ' . $e->getMessage();
            }
        }
    }
}

// 2. Busca os dados atuais do equipamento
$stmtEq = $pdo->prepare('SELECT * FROM equipamentos WHERE id = :id LIMIT 1');
$stmtEq->execute([':id' => $idEquipamento]);
$equipamento = $stmtEq->fetch();

if (!$equipamento) {
    die('Equipamento não encontrado.');
}

// Decodifica JSON de especificações para popular o editor
$specsExistentes = json_decode($equipamento['especificacoes'] ?? '{}', true);
if (!is_array($specsExistentes)) {
    $specsExistentes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Equipamento #<?= htmlspecialchars($equipamento['id']) ?> | F.O.G.O.</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0b1329;
            color: #f1f5f9;
            padding: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: #131d38;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #1e293b;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #1e293b;
        }
        .title { font-size: 1.5rem; font-weight: 700; color: #f97316; }
        .back-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            border: 1px solid #334155;
            padding: 6px 12px;
            border-radius: 6px;
        }
        .back-link:hover { background-color: #1e293b; color: #fff; }
        .alert-error {
            background-color: #7f1d1d;
            border: 1px solid #dc2626;
            color: #fecaca;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .section-header {
            font-size: 1.1rem;
            color: #38bdf8;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 6px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 0.85rem; color: #cbd5e1; }
        input, select, textarea {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #334155;
            background-color: #0b1329;
            color: #f1f5f9;
            font-size: 0.9rem;
            outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: #f97316; }
        .spec-row {
            display: grid;
            grid-template-columns: 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .btn-remove {
            background-color: #ef4444;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            height: 38px;
            font-size: 1.1rem;
        }
        .btn-secondary {
            background-color: #1e293b;
            border: 1px dashed #475569;
            color: #cbd5e1;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 6px;
            display: inline-block;
        }
        .btn-secondary:hover { background-color: #334155; }
        .btn-submit {
            background-color: #f97316;
            color: #fff;
            border: none;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
        }
        .btn-submit:hover { background-color: #ea580c; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Editar Equipamento #<?= htmlspecialchars($equipamento['id']) ?></h1>
        <a href="index.php" class="back-link">← Cancelar e Voltar</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" action="editar_equipamento.php">
        <input type="hidden" name="id" value="<?= htmlspecialchars($equipamento['id']) ?>">

        <div class="section-header">Identificação do Equipamento</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Tipo de Equipamento *</label>
                <select name="tipo" required>
                    <?php
                    $tipos = [
                        'radar' => 'Radar de Fiscalização',
                        'camera' => 'Câmera de Videomonitoramento',
                        'balanca' => 'Balança de Caminhão',
                        'veiculo_fiscalizacao' => 'Veículo Autônomo de Fiscalização',
                        'cancela' => 'Cancela Automatizada',
                        'viatura' => 'Viatura (Polícia Militar / GM)',
                        'moto_agente' => 'Moto de Agente de Trânsito',
                        'patinete' => 'Patinete Elétrico Compartilhado',
                        'totem' => 'Totem de Estacionamento',
                        'transporte_publico' => 'Veículo de Transporte'
                    ];
                    foreach ($tipos as $val => $rotulo):
                    ?>
                        <option value="<?= $val ?>" <?= $equipamento['tipo'] === $val ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Número de Série *</label>
                <input type="text" name="numero_serie" required value="<?= htmlspecialchars($equipamento['numero_serie']) ?>">
            </div>

            <div class="form-group">
                <label>Marca *</label>
                <input type="text" name="marca" required value="<?= htmlspecialchars($equipamento['marca']) ?>">
            </div>

            <div class="form-group">
                <label>Modelo *</label>
                <input type="text" name="modelo" required value="<?= htmlspecialchars($equipamento['modelo']) ?>">
            </div>
        </div>

        <div class="section-header">Localização e Georreferenciamento</div>
        <div class="form-grid">
            <div class="form-group">
                <label>CEP</label>
                <input type="text" name="cep" value="<?= htmlspecialchars($equipamento['cep'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Logradouro (Rua/Avenida/Rodovia)</label>
                <input type="text" name="logradouro" value="<?= htmlspecialchars($equipamento['logradouro'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Bairro</label>
                <input type="text" name="bairro" value="<?= htmlspecialchars($equipamento['bairro'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Cidade/UF *</label>
                <input type="text" name="cidade_uf" required value="<?= htmlspecialchars($equipamento['cidade_uf']) ?>">
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label>Referência de Localização</label>
                <input type="text" name="referencia" value="<?= htmlspecialchars($equipamento['referencia'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Latitude (GPS)</label>
                <input type="number" step="0.0000001" name="latitude" value="<?= htmlspecialchars($equipamento['latitude'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Longitude (GPS)</label>
                <input type="number" step="0.0000001" name="longitude" value="<?= htmlspecialchars($equipamento['longitude'] ?? '') ?>">
            </div>
        </div>

        <div class="section-header">Estado Operacional & Manutenção</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Situação Atual *</label>
                <select name="situacao" required>
                    <?php
                    $situacoes = [
                        'operacional' => 'Operacional',
                        'ainda_nao_instalado' => 'Ainda Não Instalado',
                        'mau_funcionamento' => 'Mau Funcionamento',
                        'desativado' => 'Desativado',
                        'furtado' => 'Furtado'
                    ];
                    foreach ($situacoes as $val => $rotulo):
                    ?>
                        <option value="<?= $val ?>" <?= $equipamento['situacao'] === $val ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Necessidade de Manutenção *</label>
                <select name="necessidade_manutencao" required>
                    <?php
                    $manuts = ['nenhuma' => 'Nenhuma', 'imediata' => 'Imediata', 'agendada' => 'Agendada'];
                    foreach ($manuts as $val => $rotulo):
                    ?>
                        <option value="<?= $val ?>" <?= $equipamento['necessidade_manutencao'] === $val ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Data da Próxima Manutenção</label>
                <input type="date" name="proxima_manutencao" value="<?= htmlspecialchars($equipamento['proxima_manutencao'] ?? '') ?>">
            </div>

            <div class="form-group" style="grid-column: span 3;">
                <label>Motivo / Observação da Alteração</label>
                <input type="text" name="observacao_auditoria" placeholder="Ex: Substituição de sensor e readequação de coordenadas GPS">
            </div>
        </div>

        <div class="section-header">Propriedades Específicas (JSON Dinâmico)</div>
        <div id="container-especificacoes">
            <?php if (empty($specsExistentes)): ?>
                <div class="spec-row">
                    <input type="text" name="spec_chave[]" placeholder="Propriedade (ex: resolucao)">
                    <input type="text" name="spec_valor[]" placeholder="Valor (ex: 4K)">
                    <button type="button" class="btn-remove" onclick="removerLinha(this)">×</button>
                </div>
            <?php else: ?>
                <?php foreach ($specsExistentes as $k => $v): ?>
                    <div class="spec-row">
                        <input type="text" name="spec_chave[]" value="<?= htmlspecialchars($k) ?>" placeholder="Propriedade">
                        <input type="text" name="spec_valor[]" value="<?= htmlspecialchars(is_bool($v) ? ($v ? 'true' : 'false') : $v) ?>" placeholder="Valor">
                        <button type="button" class="btn-remove" onclick="removerLinha(this)">×</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="btn-secondary" onclick="adicionarLinhaSpec()">+ Adicionar Propriedade</button>

        <button type="submit" class="btn-submit">Salvar Alterações do Equipamento</button>
    </form>
</div>

<script>
function adicionarLinhaSpec() {
    const container = document.getElementById('container-especificacoes');
    const div = document.createElement('div');
    div.className = 'spec-row';
    div.innerHTML = `
        <input type="text" name="spec_chave[]" placeholder="Propriedade">
        <input type="text" name="spec_valor[]" placeholder="Valor">
        <button type="button" class="btn-remove" onclick="removerLinha(this)">×</button>
    `;
    container.appendChild(div);
}

function removerLinha(botao) {
    const linhas = document.querySelectorAll('.spec-row');
    if (linhas.length > 1) {
        botao.parentElement.remove();
    } else {
        const inputs = botao.parentElement.querySelectorAll('input');
        inputs.forEach(input => input.value = '');
    }
}
</script>

</body>
</html>