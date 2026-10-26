<?php
// cadastrar_equipamento.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

exigirAdmin();
$usuario = getUsuarioLogado();
$erro = null;

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

    $chavesSpec = $_POST['spec_chave'] ?? [];
    $valoresSpec = $_POST['spec_valor'] ?? [];
    $especificacoesArray = [];

    if (is_array($chavesSpec) && is_array($valoresSpec)) {
        for ($i = 0; $i < count($chavesSpec); $i++) {
            $chaveLimpa = trim($chavesSpec[$i]);
            $valorLimpo = trim($valoresSpec[$i]);

            if ($chaveLimpa !== '') {
                if (strtolower($valorLimpo) === 'true') {
                    $especificacoesArray[$chaveLimpa] = true;
                } elseif (strtolower($valorLimpo) === 'false') {
                    $especificacoesArray[$chaveLimpa] = false;
                } elseif (is_numeric($valorLimpo)) {
                    $especificacoesArray[$chaveLimpa] = strpos($valorLimpo, '.') !== false ? (float)$valorLimpo : (int)$valorLimpo;
                } else {
                    $especificacoesArray[$chaveLimpa] = $valorLimpo;
                }
            }
        }
    }

    $especificacoesJson = json_encode($especificacoesArray, JSON_UNESCAPED_UNICODE);

    if (empty($tipo) || empty($numeroSerie) || empty($marca) || empty($modelo) || empty($cidadeUf) || empty($situacao)) {
        $erro = 'Preencha todos os campos obrigatórios (*).';
    } else {
        $pdo = getFogoDatabase();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO equipamentos (
                    tipo, numero_serie, marca, modelo,
                    cep, logradouro, referencia, bairro, cidade_uf,
                    latitude, longitude,
                    situacao, necessidade_manutencao, proxima_manutencao,
                    especificacoes, atualizado_por
                ) VALUES (
                    :tipo, :numero_serie, :marca, :modelo,
                    :cep, :logradouro, :referencia, :bairro, :cidade_uf,
                    :latitude, :longitude,
                    :situacao, :necessidade_manutencao, :proxima_manutencao,
                    :especificacoes, :atualizado_por
                )
            ');

            $stmt->execute([
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
                ':atualizado_por'         => $usuario['id']
            ]);

            header('Location: index.php?msg=equipamento_cadastrado');
            exit;
        } catch (PDOException $e) {
            $erro = ($e->getCode() === '23000') ? 'Número de série já cadastrado.' : 'Erro: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Equipamento | F.O.G.O.</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="form-container tech-card" style="margin-top: 30px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px;">
        <h1 class="tech-gradient-text" style="font-size: 1.5rem;">Cadastrar Novo Equipamento</h1>
        <a href="index.php" class="btn-outline">← Cancelar e Voltar</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert-banner alert-erro">
            <span>✖ <?= htmlspecialchars($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="cadastrar_equipamento.php">
        
        <div class="section-header">Identificação do Equipamento</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Tipo de Equipamento *</label>
                <select name="tipo" required>
                    <option value="">Selecione o tipo</option>
                    <option value="radar">Radar de Fiscalização</option>
                    <option value="camera">Câmera de Videomonitoramento</option>
                    <option value="balanca">Balança de Caminhão</option>
                    <option value="veiculo_fiscalizacao">Veículo Autônomo de Fiscalização</option>
                    <option value="cancela">Cancela Automatizada</option>
                    <option value="viatura">Viatura (Polícia Militar / GM)</option>
                    <option value="moto_agente">Moto de Agente de Trânsito</option>
                    <option value="patinete">Patinete Elétrico Compartilhado</option>
                    <option value="totem">Totem de Estacionamento</option>
                    <option value="transporte_publico">Veículo de Transporte Público</option>
                </select>
            </div>

            <div class="form-group">
                <label>Número de Série *</label>
                <input type="text" name="numero_serie" required placeholder="Ex: RAD-2026-993">
            </div>

            <div class="form-group">
                <label>Marca *</label>
                <input type="text" name="marca" required placeholder="Ex: FiscalTech, Axis, Renault">
            </div>

            <div class="form-group">
                <label>Modelo *</label>
                <input type="text" name="modelo" required placeholder="Ex: Q6135-LE, Velocis-Pro">
            </div>
        </div>

        <div class="section-header">Localização e Georreferenciamento</div>
        <div class="form-grid">
            <div class="form-group">
                <label>CEP</label>
                <input type="text" name="cep" placeholder="17500-000">
            </div>

            <div class="form-group">
                <label>Logradouro</label>
                <input type="text" name="logradouro" placeholder="Ex: Av. Tiradentes">
            </div>

            <div class="form-group">
                <label>Bairro</label>
                <input type="text" name="bairro" placeholder="Ex: Fragata">
            </div>

            <div class="form-group">
                <label>Cidade/UF *</label>
                <input type="text" name="cidade_uf" required value="Marília/SP">
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label>Referência de Localização</label>
                <input type="text" name="referencia" placeholder="Ex: Oposto ao nº 181, KM 451">
            </div>

            <div class="form-group">
                <label>Latitude (GPS)</label>
                <input type="number" step="0.0000001" name="latitude" placeholder="-22.228120">
            </div>

            <div class="form-group">
                <label>Longitude (GPS)</label>
                <input type="number" step="0.0000001" name="longitude" placeholder="-49.945820">
            </div>
        </div>

        <div class="section-header">Estado Operacional & Manutenção</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Situação Atual *</label>
                <select name="situacao" required>
                    <option value="operacional">Operacional</option>
                    <option value="ainda_nao_instalado">Ainda Não Instalado</option>
                    <option value="mau_funcionamento">Mau Funcionamento</option>
                    <option value="desativado">Desativado</option>
                    <option value="furtado">Furtado</option>
                </select>
            </div>

            <div class="form-group">
                <label>Necessidade de Manutenção *</label>
                <select name="necessidade_manutencao" required>
                    <option value="nenhuma">Nenhuma</option>
                    <option value="imediata">Imediata</option>
                    <option value="agendada">Agendada</option>
                </select>
            </div>

            <div class="form-group">
                <label>Data da Próxima Manutenção</label>
                <input type="date" name="proxima_manutencao">
            </div>
        </div>

        <div class="section-header">Propriedades Específicas (JSON Dinâmico)</div>
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 12px;">
            Adicione propriedades particulares (Ex: <code>resolucao: 4K</code>, <code>visao_noturna: true</code>).
        </p>

        <div id="container-especificacoes">
            <div class="spec-row">
                <input type="text" name="spec_chave[]" placeholder="Propriedade (ex: resolucao)">
                <input type="text" name="spec_valor[]" placeholder="Valor (ex: 4K)">
                <button type="button" class="btn-remove" onclick="removerLinha(this)">×</button>
            </div>
        </div>

        <button type="button" class="btn-outline" style="margin-top: 6px;" onclick="adicionarLinhaSpec()">+ Adicionar Propriedade</button>

        <button type="submit" class="btn-tech" style="width: 100%; justify-content: center; margin-top: 30px;">
            Salvar e Registrar Equipamento
        </button>
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