<?php
// excluir_equipamento.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connection.php';

// 1. Restrição estrita: apenas administradores podem executar exclusões
exigirAdmin();

// 2. Validação e sanitização do identificador recebido via GET
$idEquipamento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idEquipamento) {
    header('Location: index.php?msg=erro_id_invalido');
    exit;
}

$pdo = getFogoDatabase();

try {
    // 3. Garante que as restrições de chave estrangeira do SQLite estão ativas nesta conexão
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->beginTransaction();

    // 4. Verifica se o equipamento realmente existe antes de deletar
    $stmtCheck = $pdo->prepare('SELECT id, numero_serie, tipo FROM equipamentos WHERE id = :id LIMIT 1');
    $stmtCheck->execute([':id' => $idEquipamento]);
    $equipamento = $stmtCheck->fetch();

    if (!$equipamento) {
        $pdo->rollBack();
        header('Location: index.php?msg=erro_nao_encontrado');
        exit;
    }

    // 5. Executa a remoção do equipamento
    // A tabela historico_manutencoes possui FOREIGN KEY ... ON DELETE CASCADE,
    // garantindo a limpeza automática dos registros vinculados sem violação de integridade.
    $stmtDelete = $pdo->prepare('DELETE FROM equipamentos WHERE id = :id');
    $stmtDelete->execute([':id' => $idEquipamento]);

    $pdo->commit();

    header('Location: index.php?msg=equipamento_excluido');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Tratamento específico de erro de integridade referencial (SQLSTATE 23000)
    if ($e->getCode() === '23000') {
        http_response_code(409);
        die('Erro de integridade referencial: Este equipamento possui dependências bloqueadas no sistema e não pôde ser excluído.');
    }

    http_response_code(500);
    die('Falha ao processar exclusão no banco de dados: ' . htmlspecialchars($e->getMessage()));
}