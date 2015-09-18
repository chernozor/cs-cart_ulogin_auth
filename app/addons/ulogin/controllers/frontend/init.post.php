<?php

if(!defined('BOOTSTRAP')) {
	die('Access denied');
}
use Tygh\Registry;
//вынести это в апи или функцию
/*
 * Выводит в форму html для генерации виджета
 */
//	$redirect_uri = urlencode(SITE . '/socialauth?backurl=' . urlencode(ULoginAuth::ulogin_get_current_page_url()));
$backurl= Registry::get('config.current_url');
$redirect_uri = urlencode('http://test2.ru/index.php?dispatch=ulogin.login&backurl='.$backurl);
$ulogin_default_options = array();
$ulogin_default_options['display'] = 'small';
$ulogin_default_options['providers'] = 'vkontakte,odnoklassniki,mailru,facebook';
$ulogin_default_options['fields'] = 'first_name,last_name,email,photo,photo_big';
$ulogin_default_options['optional'] = 'sex,bdate,country,city';
$ulogin_default_options['hidden'] = 'other';
$ulogin_options = array();
$options = Registry::get('addons.ulogin');
$ulogin_options['ulogin_id1'] = $options['ulogin_auth_id'];
$ulogin_options['ulogin_id2'] = $options['ulogin_sync_id'];
$default_panel = false;
$ulogin_id = $ulogin_options['ulogin_id1'];
if(empty($ulogin_id)) {
	$ul_options = $ulogin_default_options;
	$default_panel = true;
}
$panel = '';
$panel .= '<div class="ulogin_panel"';
if($default_panel) {
	$ul_options['redirect_uri'] = $redirect_uri;
	unset($ul_options['label']);
	$x_ulogin_params = '';
	foreach($ul_options as $key => $value)
		$x_ulogin_params .= $key . '=' . $value . ';';
	if($ul_options['display'] != 'window')
		$panel .= ' data-ulogin="' . $x_ulogin_params . '"></div>'; else
		$panel .= ' data-ulogin="' . $x_ulogin_params . '" href="#"><img src="https://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></div>';
} else
	$panel .= ' data-uloginid="' . $ulogin_id . '" data-ulogin="redirect_uri=' . $redirect_uri . '"></div>';
$panel = '<div class="ulogin_block">' . $panel . '</div><div style="clear:both"></div>';

Tygh::$app['view']->assign('ulogin_auth_panel', $panel);