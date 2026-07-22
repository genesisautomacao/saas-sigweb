<?php

namespace App\Filament\Cidadao\Pages\Auth;

use Filament\Pages\Auth\Login;

/**
 * Login do painel Cidadão SEM o link "registre-se" (PT-1).
 * O cadastro continua ativo (o Portal do município aponta direto para
 * /cidadao/register?prefeitura={slug}) — só o atalho na tela de login sai,
 * porque ele levava o cidadão ao passo de escolher o município.
 */
class LoginCidadao extends Login
{
    protected static string $view = 'filament.cidadao.pages.auth.login';
}
