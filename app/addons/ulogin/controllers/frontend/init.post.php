<?php

if(!defined('BOOTSTRAP')) {
	die('Access denied');
}
use Tygh\Registry;
/*
 * Выводит в форму html для генерации виджета
 */

$authpanel = fn_ulogin_authpanel(0);
Tygh::$app['view']->assign('ulogin_authpanel', $authpanel);

$syncpanel = fn_ulogin_syncpanel();
Tygh::$app['view']->assign('ulogin_syncpanel', $syncpanel);