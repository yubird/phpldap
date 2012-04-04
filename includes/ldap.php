<?php
class ldap {
	// LDAPバインド
	private static function connect($bind = false) {
		global $config;
		if (($c = ldap_connect($config['ldap']['uri'])) === false) {
			return false;
		}
		ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
		if ($bind) {
			ldap_bind($c, $config['ldap']['binddn'], $config['ldap']['bindpw']);
		}
		return $c;
	}

	// SSHAハッシュっぽかったらtrue
	private static function is_hashed($password) {
		if (strpos($password, '{SSHA}') !== false) {
			return true;
		}
		return false;
	}

	// plainパスワードをSSHAハッシュ化
	private static function hash($password) {
		global $config;
		$cmd = $config['passwd']['cmd'].' '.$password.' 2>&1';
		exec($cmd, $out, $ret);
		if ($ret !== 0) {
			error_log("ldap::hash($password); "
				."exec($cmd); returned $ret");
			return false;
		}
		return $out[0];
	}

	// 使用されていない最小UIDを取得
	private static function uid() {
		global $config;
		$attrs = array('uidNumber');
		$ld = self::connect();
		$filter = '(&(objectClass=posixAccount)(uidNumber>='
			.$config['user']['minUid'].'))';
		$dn = $config['ldap']['userdn'];
		$res = ldap_search($ld, $dn, $filter, $attrs);
		if ($res === false) {
			error_log("ldap::uid(); "
				."ldap_search($ld, $dn, $filter); returned false");
			ldap_close($ld);
			return false;
		}
		$values = ldap_get_entries($ld, $res);
		ldap_close($ld);
		if ($values === false) {
			error_log("ldap::uid(); "
				."ldap_get_entries($ld, $res); returned false");
			return false;
		}
		$used = array();
		for ($i = 0; $i < $values['count']; ++$i) {
			$used[] = intval($values[$i]['uidnumber'][0]);
		}
		$min = $config['user']['minUid'];
		for ($i = $min; $i < 65535; ++$i) {
			if (!in_array($i, $used)) {
				return $i;
			}
		}
		return $min;
	}

	// 使用されていない最小GIDを取得
	private static function gid() {
		global $config;
		$attrs = array('gidNumber');
		$ld = self::connect();
		$filter = '(&(objectClass=posixGroup)(gidNumber>='
			.$config['user']['minGid'].'))';
		$dn = $config['ldap']['groupdn'];
		$res = ldap_search($ld, $dn, $filter, $attrs);
		if ($res === false) {
			error_log("ldap::gid(); "
				."ldap_search($ld, $dn, $filter); returned false");
			ldap_close($ld);
			return false;
		}
		$values = ldap_get_entries($ld, $res);
		ldap_close($ld);
		if ($values === false) {
			error_log("ldap::gid(); "
				."ldap_get_entries($ld, $res); returned false");
			return false;
		}
		$used = array();
		for ($i = 0; $i < $values['count']; ++$i) {
			$used[] = intval($values[$i]['gidnumber'][0]);
		}
		$min = $config['user']['minGid'];
		for ($i = $min; $i < 65535; ++$i) {
			if (!in_array($i, $used)) {
				return $i;
			}
		}
		return $min;
	}

	// ユーザが存在するか調べる
	private static function user_exists($user) {
		global $config;
		$ld = self::connect();
		$filter = '(&(objectClass=posixAccount)(uid='.$user.'))';
		$dn = $config['ldap']['userdn'];
		$res = ldap_search($ld, $dn, $filter, array('cn'));
		if ($res === false) {
			error_log("ldap::user_exists($user); "
				."ldap_search($ld, $dn, $filter); returned false");
			return false;
		}
		$values = ldap_get_entries($ld, $res);
		ldap_close($ld);
		if ($values === false) {
			error_log("ldap::user_exists($user); "
				."ldap_get_entries($ld, $res); returned false");
			return false;
		}
		if ($values['count'] > 0) {
			return true;
		}
		return false;
	}

	// ランダムなパスワード生成
	private static function password() {
		global $config;
		$min = $config['passwd']['min'];
		$max = $config['passwd']['max'];
		$use = 'abcdefgehjklmnopqrstuvwxyz'
			.'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
			.'0123456789';
		$len = mt_rand($min, $max);
		for ($i = 0, $pass = ''; $i <= $len; ++$i) {
			$pass .= $use[mt_rand(0, strlen($use) - 1)];
		}
		return $pass;
	}

	// ユーザ追加
	public static function useradd($user, $pass = false, $uid = false) {
		global $config;
		if (self::user_exists($user)) {
			error_log("ldap::useradd($user); "
				."user already exists");
			return false;
		}
		$ld = self::connect(true);
		if ($pass === false) {
			$pass = self::password();
		}
		if (self::is_hashed($pass)) {
			$hashed = $pass;
		}
		else {
			$hashed = self::hash($pass);
		}
		if ($uid === false) {
			$uid = self::uid();
		}
		$values = array(
			'cn' => $user,
			'givenName' => $user,
			'sn' => $user,
			'uid' => $user,
			'uidNumber' => $uid,
			'gidNumber' => $config['user']['gid'],
			'objectClass' => array(
				'inetOrgPerson',
				'posixAccount',
				'shadowAccount'
			),
			'homeDirectory' => $config['user']['homeDir'].'/'.$user,
			'loginShell' => $config['user']['shell'],
			'shadowExpire' => -1,
			'shadowFlag' => 0,
			'shadowLastChange' => time(),
			'shadowMax' => 65535,
			'shadowMin' => -1,
			'shadowWarning' => -1,
			'userPassword' => $hashed
		);
		$dn = 'uid='.$user.','.$config['ldap']['userdn'];
		if (ldap_add($ld, $dn, $values) === false) {
			error_log("ldap::useradd($user, $pass, $uid); "
				."ldap_add($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		echo "useradd:\n",
			"\tUsername: $user\n",
			"\tPassword: $pass\n";
		ldap_close($ld);
		return true;
	}

	// ユーザ削除
	public static function userdel($user) {
		global $config;
		if (self::user_exists($user)) {
			$dn = "uid=$user,".$config['ldap']['userdn'];
			$ld = self::connect(true);
			if (ldap_delete($ld, $dn) === false) {
				error_log("ldap::userdel($user); "
					."ldap_delete($ld, $dn); returned false");
				return false;
			}
			echo "userdel:\n",
				"\t$user deleted\n";
			return true;
		}
		error_log("ldap::userdel($user); "
			."user is not exists");
		return false;
	}

	// グループが存在するか判定
	private static function group_exists($group) {
		global $config;
		$ld = self::connect();
		$filter = '(&(objectClass=posixGroup)(cn='.$group.'))';
		$dn = $config['ldap']['groupdn'];
		$res = ldap_search($ld, $dn, $filter, array('cn', 'gidNumber'));
		if ($res === false) {
			error_log("ldap::group_exists($group); "
				."ldap_search($ld, $dn, $filter); returned false");
			return false;
		}
		$values = ldap_get_entries($ld, $res);
		if ($values === false) {
			error_log("ldap::group_exists($group); "
				."ldap_get_entries($ld, $res); returned false");
			return false;
		}
		if ($values['count'] > 0) {
			return true;
		}
		return false;
	}

	// プライマリグループを変更
	public static function chgroup($user, $gid) {
		global $config;
		if (!self::user_exists($user)) {
			error_log("ldap::chgroup($user, $gid); "
				."user is not exists");
			return false;
		}
		$ld = self::connect(true);
		$dn = 'uid='.$user.','.$config['ldap']['userdn'];
		$values = array('gidNumber' => $gid);
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::chgroup($user, $gid); "
				."ldap_modify($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		echo "chgroup:\n",
			"\t$user's primary group is changed to $gid\n";
		ldap_close($ld);
		return true;
	}

	// グループにメンバー追加
	public static function adduser($group, $user) {
		global $config;
		if (!self::group_exists($group)) {
			error_log("ldap::adduser($group, $user); "
				."group is not exists");
			return false;
		}
		$users = self::group_users($group);
		if (in_array($user, $users)) {
			error_log("ldap::adduser($group, $user); "
				."user already exists in this group");
			return false;
		}
		$ld = self::connect(true);
		$users[] = $user;
		$dn = 'cn='.$group.','.$config['ldap']['groupdn'];
		$values = array('memberUid' => $users);
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::adduser($group, $user); "
				."ldap_modify($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		ldap_close($ld);
		echo "adduser:\n",
			"\t$user added in $group\n";
		return true;
	}

	// グループからメンバー削除
	public static function deluser($group, $user) {
		global $config;
		if (!self::group_exists($group)) {
			error_log("ldap::deluser($group, $user); "
				."group is not exists");
			return false;
		}
		$users = self::group_users($group);
		if (!in_array($user, $users)) {
			error_log("ldap::deluser($group, $user); "
				."user not found in this group");
			return false;
		}
		$ld = self::connect(true);
		$dn = 'cn='.$group.','.$config['ldap']['groupdn'];
		$n_users = array();
		foreach ($users as $val) {
			if ($val !== $user) {
				$n_users[] = $val;
			}
		}
		$values = array('memberUid' => $n_users);
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::deluser($group, $user); "
				."ldap_modify($ld, $dn, $values; returned false");
			ldap_close($ld);
			return false;
		}
		ldap_close($ld);
		echo "deluser:\n",
			"\t$user deleted in $group\n";
		return true;
	}

	// グループのメンバを取得
	private static function group_users($group) {
		global $config;
		$attrs = array('memberUid');
		$ld = self::connect();
		$filter = '(&(objectClass=posixGroup)(cn='.$group.'))';
		$dn = $config['ldap']['groupdn'];
		$res = ldap_search($ld, $dn, $filter, $attrs);
		if ($res === false) {
			error_log("ldap::group_users($group); "
				."ldap_search($ld, $dn, $filter); returned false");
			ldap_close($ld);
			return false;
		}
		$values = ldap_get_entries($ld, $res);
		ldap_close($ld);
		$users = array();
		if (isset($values[0]['memberuid'])) {
			for ($i = 0; $i < $values[0]['memberuid']['count']; ++$i) {
				$users[] = $values[0]['memberuid'][$i];
			}
		}
		return $users;
	}

	// グループを追加
	public static function groupadd($group, $gid = false) {
		global $config;
		if (self::group_exists($group)) {
			error_log("ldap::groupadd($group, $gid); "
				."group is already exists");	
			return false;
		}
		if ($gid === false) {
			$gid = self::gid();
		}
		$ld = self::connect(true);
		$values = array(
			'cn' => $group,
			'objectClass' => array('posixGroup', 'top'),
			'gidNumber' => $gid
		);
		$dn = 'cn='.$group.','.$config['ldap']['groupdn'];
		if (ldap_add($ld, $dn, $values) === false) {
			error_log("ldap::groupadd($group, $gid); "
				."ldap_add($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		echo "groupadd:\n",
			"\tGroupName: $group\n",
			"\tGidNumber: $gid\n";
		ldap_close($ld);
	}

	// グループを削除
	public static function groupdel($group) {
		global $config;
		if (!self::group_exists($group)) {
			error_log("ldap::groupdel($group); "
				."group is not exists");
			return false;
		}
		$ld = self::connect(true);
		$dn = 'cn='.$group.','.$config['ldap']['groupdn'];
		if (ldap_delete($ld, $dn) === false) {
			error_log("ldap::groupdel($group); "
				."ldap_delete($ld, $dn); returned false");
			ldap_close($ld);
			return false;
		}
		ldap_close($ld);
		echo "groupdel:\n",
			"\t$group is deleted\n";
		return true;
	}

	// パスワードを変更
	public static function chpasswd($user, $pass = false) {
		global $config;
		if (!self::user_exists($user)) {
			error_log("ldap::chpass($user, $pass); "
				."user is not exists");
			return false;
		}
		if ($pass === false) {
			$pass = self::password();
		}
		$ld = self::connect(true);
		if (self::is_hashed($pass)) {
			$hashed = $pass;
		}
		else {
			$hashed = self::hash($pass);
		}
		$values = array('userPassword' => $hashed);
		$dn = 'uid='.$user.','.$config['ldap']['userdn'];
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::chpass($user, $pass); "
				."ldap_modify($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		ldap_close($ld);
		echo "chpasswd:\n",
			"\t$user's password is changed to $pass\n";
		return true;
	}

	// シェルを変更
	public static function chsh($user, $shell) {
		global $config;
		if (!self::user_exists($user)) {
			error_log("ldap::chsh($user, $shell); "
				."user is not exists");
			return false;
		}
		$ld = self::connect(true);
		$dn = 'uid='.$user.','.$config['ldap']['userdn'];
		$values = array('loginShell' => $shell);
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::chsh($user, $shell); "
				."ldap_modify($ld, $dn, $values); returned false");
			ldap_close($ld);
			return false;
		}
		ldap_close($ld);
		echo "chsh:\n",
			"\t$user's login shell is changed to $shell\n";
		return true;
	}

	// メールアドレスを変更
	public static function chmail($user, $mail) {
		global $config;
		if (!self::user_exists($user)) {
			error_log("ldap::chmail($user, $mail); "
				."user is not exists");
			return false;
		}
		$ld = self::connect(true);
		$dn = 'uid='.$user.','.$config['ldap']['maildn'];
		$values = array('mail' => $mail);
		if (ldap_modify($ld, $dn, $values) === false) {
			error_log("ldap::chmail($user, $mail); "
				."ldap_modify($ld, $dn, $values); returned false");
			return false;
		}
		ldap_close($ld);
		echo "chmail:\n",
			"\t$user's mail address is changed to $mail\n";
		return true;
	}

	// メールアドレス追加
	public static function mailadd($user, $mail, $pass, $uid, $gid) {
		global $config;
		if (self::mail_exists($user)) {
			error_log("ldap::mailadd($user); "
				."mail already exists");
			return false;
		}
		$ld = self::connect(true);
		if ($pass === false) {
		  $pass = self::password();
		}
		if (self::is_hashed($pass)) {
			$hashed = $pass;
		}
		else {
			$hashed = self::hash($pass);
		}
		if ($uid === false) {
			$uid = self::uid();
		}
		if ($gid === false) {
			$gid = $config['mail']['gid'];
		}
		$homeDir = $config['mail']['homeDir'];
		$values = array(
			'cn' => $user,
			'sn' => $user,
			'uid' => $user,
			'uidNumber' => $uid,
			'gidNumber' => $gid,
			'objectClass' => array('mailAccount'),
			'homeDirectory' => $homeDir.'/'.$user,
			'mailDir' => $homeDir.'/'.$user.'/Maildir',
			'mail' => $mail,
			'userPassword' => $hashed
		);
		$dn = 'uid='.$user.','.$config['ldap']['maildn'];
		if (ldap_add($ld, $dn, $values) === false) {
		  error_log("ldap::mailadd($user, $mail, $pass, $uid, $gid); "
				."ldap_add($ld, $dn, $values); returned false");
		  ldap_close($ld);
		  return false;
		}
		echo "mailadd:\n",
		  "\tMailaddr: $mail\n",
		  "\tPassword: $pass\n";
		ldap_close($ld);
		return true;
	}

	// ユーザが存在するか調べる
	private static function mail_exists($user) {
	  global $config;
	  $ld = self::connect(true);
	  $filter = '(&(objectClass=mailAccount)(uid='.$user.'))';
	  $dn = $config['ldap']['maildn'];
	  $res = ldap_search($ld, $dn, $filter, array('mail'));
	  if ($res === false) {
	    error_log("ldap::mail_exists($user); "
		      ."ldap_search($ld, $dn, $filter); returned false");
	    return false;
	  }
	  $values = ldap_get_entries($ld, $res);
	  ldap_close($ld);
	  if ($values === false) {
	    error_log("ldap::mail_exists($user); "
		      ."ldap_get_entries($ld, $res); returned false");
	    return false;
	  }
	  if ($values['count'] > 0) {
	    return true;
	  }
	  return false;
	}
	
}
?>
