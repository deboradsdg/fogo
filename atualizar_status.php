<?php
// atualizar_status.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

// 1. Exige que o usuário esteja logado
exigirAutenticacao();
$usuario = getUsuarioLogado();

// 2. Aceita exclusivamente requisições do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido.');
}

// 3. Captura e sanitização dos dados recebidos
$equipamentoId         = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$novaSituacao          = trim($_POST['situacao'] ?? '');
$necessidadeManutencao = trim($_POST['necessidade_manutencao'] ?? '');
$proximaManutencao     = !empty($_POST['proxima_manutencao']) ? trim($_POST['proxima_manutencao']) : null;
$observacao            = trim($_POST['observacao'] ?? '');

if (!$equipamentoId) {
    http_response_code(400);
    die('Identificador do equipamento inválido ou ausente.');
}

// 4. Validação RBAC: verifica se os campos enviados são permitidos para o perfil
$dadosParaValidar = [
    'situacao'               => $novaSituacao,
    'necessidade_manutencao' => $necessidadeManutencao,
    'proxima_manutencao'     => $proximaManutencao,
    'observacao'             => $observacao
];

if (!validarPermissaoEdicao($usuario, $dadosParaValidar)) {
    http_response_code(403);
    die('Acesso negado: você não tem permissão para alterar estes campos.');
}

// 5. Validação dos domínios aceitos pelo Schema
$situacoesValidas = [
    'ainda_nao_instalado',
    'operacional',
    'mau_funcionamento',
    'desativado',
    'furtado'
];

$manutencoesValidas = [
    'nenhuma',
    'imediata',
    'agendada'
];

if (!in_array($novaSituacao, $situacoesValidas, true)) {
    http_response_code(422);
    die('Situação operacional inválida.');
}

if (!in_array($necessidadeManutencao, $manutencoesValidas, true)) {
    http_response_code(422);
    die('Status de manutenção inválido.');
}

// 6. Conexão e Execução Transacional
$pdo = getFogoDatabase();

try {
    $pdo->beginTransaction();

    // 6.1. Busca o estado anterior para auditoria
    $stmtAntigo = $pdo->prepare('
        SELECT situacao, necessidade_manutencao 
        FROM equipamentos 
        WHERE id = :id 
        LIMIT 1
    ');
    $stmtAntigo->execute([':id' => $equipamentoId]);
    $equipamentoAtual = $stmtAntigo->fetch();

    if (!$equipamentoAtual) {
        $pdo->rollBack();
        http_response_code(404);
        die('Equipamento não encontrado no sistema.');
    }

    $situacaoAnterior = $equipamentoAtual['situacao'];

    // 6.2. Atualiza a tabela de equipamentos
    $stmtUpdate = $pdo->prepare('
        UPDATE equipamentos 
        SET situacao = :situacao,
            necessidade_manutencao = :necessidade_manutencao,
            proxima_manutencao = :proxima_manutencao,
            atualizado_por = :usuario_id,
            atualizado_em = CURRENT_TIMESTAMP
        WHERE id = :id
    ');

    $stmtUpdate->execute([
        ':situacao'               => $novaSituacao,
        ':necessidade_manutencao' => $necessidadeManutencao,
        ':proxima_manutencao'     => $proximaManutencao,
        ':usuario_id'             => $usuario['id'],
        ':id'                     => $equipamentoId
    ]);

    // 6.3. Insere o registro de histórico para auditoria completa
    $stmtHistorico = $pdo->prepare('
        INSERT INTO historico_manutencoes (
            equipamento_id,
            usuario_id,
            situacao_anterior,
            situacao_nova,
            manutencao_status,
            observacao
        ) VALUES (
            :equipamento_id,
            :usuario_id,
            :situacao_anterior,
            :situacao_nova,
            :manutencao_status,
            :observacao
        )
    ');

    $stmtHistorico->execute([
        ':equipamento_id'   => $equipamentoId,
        ':usuario_id'       => $usuario['id'],
        ':situacao_anterior'=> $situacaoAnterior,
        ':situacao_nova'    => $novaSituacao,
        ':manutencao_status'=> $necessidadeManutencao,
        ':observacao'       => !empty($observacao) ? $observacao : 'Atualização de rotina pelo operador.'
    ]);

    // Confirma as duas operações juntas
    $pdo->commit();

    // Redireciona de volta com feedback positivo
    header('Location: index.php?msg=sucesso');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    die('Falha ao processar atualização: ' . htmlspecialchars($e->getMessage()));
}