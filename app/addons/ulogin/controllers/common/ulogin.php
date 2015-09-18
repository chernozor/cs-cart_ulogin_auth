<?php
use Tygh\Registry;
use Tygh\Http;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if($mode == 'login') {
		if($_REQUEST['token']) {
			$u_user = uloginGetUserFromToken($_REQUEST['token']);
			if(!$u_user) {
				fn_set_notification('E', __('ulogin_error'), __('ulogin_error_token'));

				return false;
			}
			$u_user = json_decode($u_user, true);
			$check = uloginCheckTokenError($u_user);
			if(!$check) {
				return false;
			}
			$user_id = getUserIdByIdentity($u_user['identity']);
			if(isset($user_id) && !empty($user_id)) {
				$d = fn_get_user_short_info($user_id);
				if($user_id > 0 && $d['user_id'] > 0) {
					uloginCheckUserId($user_id);
				} else {
					$user_id = ulogin_registration_user($u_user, 1);
				}
			} else $user_id = ulogin_registration_user($u_user);
			if($user_id > 0) {
				fn_login_user($user_id);
				unset($_REQUEST['token']);
			}
			$redirect_url = $_GET['backurl'];
			fn_redirect($redirect_url, true);
		}
		$redirect_url = $_GET['backurl'];
		fn_redirect($redirect_url, true);
	}
}
/**
 * Обменивает токен на пользовательские данные
 * @param bool $token
 * @return bool|mixed|string
 */
function uloginGetUserFromToken($token = false) {
	$response = false;
	if($token) {
		$data = array('cms' => 'cs-cart', 'version' => constant('PRODUCT_VERSION'));
		$request = 'https://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'] . '&data=' . base64_encode(json_encode($data));
		$response = Http::get($request);
	}

	return $response;
}

/**
 * Проверка пользовательских данных, полученных по токену
 * @param $u_user - пользовательские данные
 * @return bool
 */
function uloginCheckTokenError($u_user) {
	if(!is_array($u_user)) {
		fn_set_notification('E', __('ulogin_error'), __('ulogin_error_data'));

		return 0;
	}
	if(isset($u_user['error'])) {
		$strpos = strpos($u_user['error'], 'host is not');
		if($strpos) {
			fn_set_notification('E', __('ulogin_error'), __('ulogin_error_host'));

			return 0;
		}
		switch($u_user['error']) {
			case 'token expired':
				fn_set_notification('E', __('ulogin_error'), __('ulogin_error_timeout'));

				return 0;
			case 'invalid token':
				fn_set_notification('E', __('ulogin_error'), __('ulogin_error_token_invalid'));

				return 0;
			default:
				fn_set_notification('E', __('ulogin_error'), $u_user['error']);

				return 0;
		}
	}
	if(!isset($u_user['identity'])) {
		fn_set_notification('E', __('ulogin_error'), __('ulogin_error_data_identity'));

		return 0;
	}

	return 1;
}

function getUserIdByIdentity($identity) {
	$user_data = db_get_field("SELECT user_id FROM ?:ulogin WHERE identity = ?s", $identity);
	if($user_data)
		return $user_data;

	return false;
}

function getUserById($user_id) {
	$user_data = db_get_row("SELECT * FROM ?:users WHERE user_id = ?i", $user_id);
	if($user_data)
		return $user_data;

	return false;
}

function getUserInfoByEmail($email) {
	$user_data = db_get_field("SELECT user_id FROM ?:users WHERE email = ?s", $email);
	if($user_data)
		return $user_data;

	return false;
}

/**
 * Регистрация на сайте и в таблице uLogin
 * @param Array $u_user - данные о пользователе, полученные от uLogin
 * @param int $in_db - при значении 1 необходимо переписать данные в таблице ?:ulogin
 * @return bool|int|Error
 */
function ulogin_registration_user($u_user, $in_db = 0) {
	if(!isset($u_user['email'])) {
		fn_set_notification('W', __('ulogin_auth_error_title'), __('ulogin_auth_error_msg'));

		return false;
	}
	$u_user['network'] = isset($u_user['network']) ? $u_user['network'] : '';
	$u_user['phone'] = isset($u_user['phone']) ? $u_user['phone'] : '';
	// данные о пользователе есть в ulogin_table, но отсутствуют в Базе
	if($in_db == 1) {
		db_query('DELETE FROM ?:ulogin WHERE identity = ?s', $u_user['identity']);
	}
	$user_id = getUserInfoByEmail($u_user['email']);
	// $check_m_user == 1 -> есть пользователь с таким email
	$check_m_user = !empty($user_id) ? 1 : 0;
	$auth = &$auth;
	$current_user = isset($auth['user_id']) ? $auth['user_id'] : 0;
	// $isLoggedIn == true -> ползователь онлайн
	$isLoggedIn = (!empty($current_user)) ? 1 : 0;
	if(!$check_m_user && !$isLoggedIn) { // отсутствует пользователь с таким email в базе -> регистрация
		$date = explode('.', $u_user['bdate']);
		$user_data = array();
		$user_data['email'] = $u_user['email'];
		$user_data['user_login'] = ulogin_generateNickname($u_user['first_name'], $u_user['last_name'], $u_user['nickname'], $u_user['bdate']);
		$user_data['user_type'] = 'C';
		$user_data['is_root'] = 'N';
		$user_data['password1'] = $user_data['password2'] = '';
		$user_data['title'] = ('mr');
		$user_data['firstname'] = $u_user['first_name'];
		$user_data['lastname'] = $u_user['last_name'];
		$user_data['phone'] = $u_user['phone'];
		$user_data['birthday'] = $date['2'] . '-' . $date['1'] . '-' . $date['0'];
		list($user_data['user_id'], $profile_id) = fn_update_user('', $user_data, $auth, true, true, false);
		$u_user_data = array('user_id' => $user_data['user_id'], 'identity' => $u_user['identity'], 'network' => $u_user['network']);
		db_query("INSERT INTO ?:ulogin ?e", $u_user_data);

		return $user_data['user_id'];
	} else { // существует пользователь с таким email или это текущий пользователь
		if(!isset($u_user["verified_email"]) || intval($u_user["verified_email"]) != 1) {
			fn_print_die('<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . $_POST['token'] . '")</script>' . ("Электронный адрес данного аккаунта совпадает с электронным адресом существующего пользователя. <br>Требуется подтверждение на владение указанным email.</br></br>") . ("Подтверждение аккаунта"));

			return false;
		}
		if(intval($u_user["verified_email"]) == 1) {
			$user_id = $isLoggedIn ? $current_user : $user_id;
			$other_u = db_get_row("SELECT identity FROM ?:ulogin WHERE user_id = ?i", $user_id);
			if($other_u) {
				if(!$isLoggedIn && !isset($u_user['merge_account'])) {
					fn_print_die('<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . $_REQUEST['token'] . '","' . $other_u['identity'] . '")</script>' . 'С данным аккаунтом уже связаны данные из другой социальной сети. Требуется привязка новой учётной записи социальной сети к этому аккаунту. Синхронизация аккаунтов');
					exit;
				}
			}
			$u_user_data = array('user_id' => $user_id, 'identity' => $u_user['identity'], 'network' => $u_user['network']);
			db_query("INSERT INTO ?:ulogin ?e", $u_user_data);

			return $user_id;
		}
	}

	return false;
}

/**
 * Гнерация логина пользователя
 * в случае успешного выполнения возвращает уникальный логин пользователя
 * @param $first_name
 * @param string $last_name
 * @param string $nickname
 * @param string $bdate
 * @param array $delimiters
 * @return string
 */
function ulogin_generateNickname($first_name, $last_name = "", $nickname = "", $bdate = "", $delimiters = array('.', '_')) {
	$delim = array_shift($delimiters);
	$first_name = ulogin_translitIt($first_name);
	$first_name_s = substr($first_name, 0, 1);
	$variants = array();
	if(!empty($nickname))
		$variants[] = $nickname;
	$variants[] = $first_name;
	if(!empty($last_name)) {
		$last_name = ulogin_translitIt($last_name);
		$variants[] = $first_name . $delim . $last_name;
		$variants[] = $last_name . $delim . $first_name;
		$variants[] = $first_name_s . $delim . $last_name;
		$variants[] = $first_name_s . $last_name;
		$variants[] = $last_name . $delim . $first_name_s;
		$variants[] = $last_name . $first_name_s;
	}
	if(!empty($bdate)) {
		$date = explode('.', $bdate);
		$variants[] = $first_name . $date[2];
		$variants[] = $first_name . $delim . $date[2];
		$variants[] = $first_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $date[2];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $date[2];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $date[2];
		$variants[] = $first_name_s . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $date[2];
		$variants[] = $last_name . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $delim . $date[0] . $date[1];
	}
	$i = 0;
	$exist = true;
	while(true) {
		if($exist = ulogin_userExist($variants[$i])) {
			foreach($delimiters as $del) {
				$replaced = str_replace($delim, $del, $variants[$i]);
				if($replaced !== $variants[$i]) {
					$variants[$i] = $replaced;
					if(!$exist = ulogin_userExist($variants[$i]))
						break;
				}
			}
		}
		if($i >= count($variants) - 1 || !$exist)
			break;
		$i++;
	}
	if($exist) {
		while($exist) {
			$nickname = $first_name . mt_rand(1, 100000);
			$exist = ulogin_userExist($nickname);
		}

		return $nickname;
	} else
		return $variants[$i];
}

/**
 * Проверка существует ли пользователь с заданным логином
 */
function ulogin_userExist($login) {
	$user_data = db_get_row("SELECT user_id FROM ?:users WHERE user_login = ?s", $login);
	if(!empty($user_data))
		return true;

	return false;
}

/**
 * Транслит
 */
function ulogin_translitIt($str) {
	$tr = array("А" => "a", "Б" => "b", "В" => "v", "Г" => "g", "Д" => "d", "Е" => "e", "Ж" => "j", "З" => "z", "И" => "i", "Й" => "y", "К" => "k", "Л" => "l", "М" => "m", "Н" => "n", "О" => "o", "П" => "p", "Р" => "r", "С" => "s", "Т" => "t", "У" => "u", "Ф" => "f", "Х" => "h", "Ц" => "ts", "Ч" => "ch", "Ш" => "sh", "Щ" => "sch", "Ъ" => "", "Ы" => "yi", "Ь" => "", "Э" => "e", "Ю" => "yu", "Я" => "ya", "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ж" => "j", "з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "y", "ы" => "y", "ь" => "", "э" => "e", "ю" => "yu", "я" => "ya");
	if(preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
		$str = strtr($str, $tr);
		$str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
	}

	return $str;
}

/**
 * @param $user_id
 * @return bool
 */
function uloginCheckUserId($user_id) {
	$current_user = &$auth['user_id'];
	if(($current_user > 0) && ($user_id > 0) && ($current_user != $user_id)) {
		fn_set_notification('W', __('ulogin_sync_error_title'), __('ulogin_sync_error_msg'));

		return false;
	}

	return true;
}