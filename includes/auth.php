<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retorna os dados do usuário autenticado na sessão
 */
function getUsuarioLogado(): ?array {
    return $_SESSION['usuario'] ?? null;
}

/**
 * Exige login para acessar a página
 */
function exigirAutenticacao(): void {
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exige perfil de administrador (ex: para cadastrar agentes, excluir itens)
 */
function exigirAdmin(): void {
    exigirAutenticacao();
    if ($_SESSION['usuario']['perfil'] !== 'admin') {
        http_response_code(403);
        die('Acesso negado: apenas administradores podem realizar esta operação.');
    }
}

/**
 * Valida se os campos enviados na requisição podem ser alterados pelo perfil atual
 */
function validarPermissaoEdicao(array $usuarioLogado, array $camposParaAlterar): bool {
    // Admin tem passe livre para alterar qualquer campo
    if ($usuarioLogado['perfil'] === 'admin') {
        return true;
    }

    // Campos restritos que o agente tem permissão de alterar
    $camposPermitidosAgente = [
        'situacao',
        'necessidade_manutencao',
        'proxima_manutencao',
        'observacao'
    ];

    foreach (array_keys($camposParaAlterar) as $campo) {
        if (!in_array($campo, $camposPermitidosAgente, true)) {
            return false; // Tentou alterar campos estruturais (ex: número de série, local, modelo)
        }
    }

    return true;
}