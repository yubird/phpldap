<?php
class util {
	public $type = '';
	public $options = array();
	public $target = false;
	
	// 必要な変数を定義しておく
	public function __construct($type) {
		global $argv;
		$s_opts = '';
		$l_opts = array();
		$this->setType($type);
		switch ($this->type) {
			case 'useradd':
				$s_opts ='g:hp:u:';
				$l_opts = array('gid:', 'help', 'password:', 'uid:');
				break;
			case 'userdel':
			case 'groupdel':
				$s_opts = 'h';
				$l_opts = array('help');
				break;
			case 'chgroup':
				$s_opts = 'g:h';
				$l_opts = array('gid:', 'help');
				break;
			case 'chpasswd':
				$s_opts = 'hp:';
				$l_opts = array('help', 'password:');
				break;
			case 'chsh':
				$s_opts = 'hs:';
				$l_opts = array('help', 'shell:');
				break;
			case 'groupadd':
				$s_opts = 'g:h';
				$l_opts = array('gid:', 'help');
				break;
			case 'adduser':
			case 'deluser':
				$s_opts = 'G:h';
				$l_opts = array('group:', 'help');
				break;
			case 'mailadd':
				$s_opts = 'g:hp:u:m:';
				$l_opts = array('gid:', 'help', 'password:', 'uid:', 'mail:');
				break;
		}
		$this->options = getopt($s_opts, $l_opts);
		$this->setTarget();
	}

	public function getOption($key) {
		switch ($key) {
			case 'help':
				if (isset($this->options['h']) ||
						isset($this->options['help'])) {
					return true;
				}
				break;
			case 'gid':
				if (isset($this->options['g'])) {
					return is_numeric($this->options['g']) ?
						intval($this->options['g']) : false;
				}
				if (isset($this->options['gid'])) {
					return is_numeric($this->options['gid']) ?
						intval($this->options['gid']) : false;
				}
				break;
			case 'group':
				if (isset($this->options['G'])) {
					return $this->options['G'];
				}
				if (isset($this->options['group'])) {
					return $this->options['group'];
				}
				break;
			case 'uid':
				if (isset($this->options['u'])) {
					return is_numeric($this->options['u']) ?
						intval($this->options['u']) : false;
				}
				if (isset($this->options['uid'])) {
					return is_numeric($this->options['uid']) ?
						intval($this->options['uid']) : false;
				}
				break;
			case 'password':
				if (isset($this->options['p'])) {
					return $this->options['p'];
				}
				if (isset($this->options['password'])) {
					return $this->options['password'];
				}
				break;
			case 'mail':
				if (isset($this->options['m'])) {
					return $this->options['m'];
				}
				if (isset($this->options['mail'])) {
					return $this->options['mail'];
				}
				break;
			case 'shell':
				if (isset($this->options['s'])) {
					return $this->options['s'];
				}
				if (isset($this->options['shell'])) {
					return $this->options['shell'];
				}
				break;
		}
		return false;
	}
	
	/**
	 * コマンドライン引数から不用な文字列を除去してUSERNAMEらしき物を返す
	 * 複数ある場合は配列で
	 * @return mixed
	 */
	private function setTarget() {
		global $argv;
		$ignore = array(
			'/--help/', '/--gid \d+/', '/-g \d+/',
			'/--group \S+/', '/-G \S+/',
			'/--uid \d+/', '/-u \d+/', '/--password \S+/', '/-p \S+/',
			'/--shell \S+/', '/-s \S+/', '/--mail \S+/', '/-m \S+/'
		);
		$tmp = $argv;
		array_shift($tmp);
		$tmp = implode(' ', $tmp);
		$tmp = trim(preg_replace($ignore, '', $tmp));
		if (preg_match('/-\S+ /', $tmp)) {
			$this->target = false;
		}
		else if (strlen($tmp)) {
			if (strpos($tmp, ' ') !== false) {
				$tmp = explode(' ', $tmp);
			}
			$this->target = $tmp;
		}
		else {
			$this->target = false;
		}
		return $this->target;
	}

	public function setType($type) {
		if ($type) {
			switch ($type) {
				case 'useradd':	case 'userdel':	case 'adduser':
				case 'deluser': case 'groupdel': case 'chgroup':
				case 'chpasswd': case 'chsh': case 'groupadd': case 'mailadd':
					$this->type = $type;
					break;
				case 'help':
				default:
					$this->type = 'help';
					break;
			}
		}
		else {
			$this->type = 'help';
		}
		return $this->type;
	}

	// ヘルプメッセージを出力
	public function help() {
		$messages = array(
			'g' => " -g, --gid\tGID\t\tset group number by GID\n",
			'G' => " -G, --group\t GROUPNAME\tadd user in GROUPNAME\n",
			'h' => " -h, --help\t\t\tdisplay this help message\n",
			'p' => " -p, --password\tPASSWORD\tset password for account\n"
				." \t\t\t\tPASSWORD is plain or {SSHA}********* hashed\n",
			'u' => " -u, --uid\tUID\t\tset UID number for new account\n",
			's' => " -s, --shell\tSHELL\t\tset login shell for USERNAME\n",
			'm' => " -m, --mail \tMAIL\t\tset mailaddress to MAIL\n",
		);
		switch ($this->type) {
			case 'useradd':
				echo "Usage: useradd [options] USERNAME\n",
					"create new user by USERNAME\n",
					$messages['g'],
					$messages['h'],
					$messages['p'],
					$messages['u'];
				break;
			case 'userdel':
				echo "Usage: userdel [options] USERNAME\n",
					"delete user by USERNAME\n",
					$messages['h'];
				break;
			case 'groupadd':
				echo "Usage: groupadd [options] GROUPNAME\n",
					"create new group by GROUPNAME\n",
					$messages['g'],
					$messages['h'];
				break;
			case 'groupdel':
				echo "Usage: groupdel [options] GROUPNAME\n",
					"delete group by GROUPNAME\n",
					$messages['h'];
				break;
			case 'chgroup':
				echo "Usage: chgroup [options] USERNAME\n",
					"change primary group number for USERNAME\n",
					$messages['g'],
					$messages['h'];
				break;
			case 'chmail':
				break;
			case 'chsh':
				echo "Usage: chsh [options] USERNAME\n",
					"change login shell for USERNAME\n",
					$messages['h'],
					$messages['s'];
				break;
			case 'chpasswd':
				echo "Usage: chpasswd [options] USERNAME\n",
					"change password for USERNAME\n",
					$messages['h'],
					$messages['p'];
				break;
			case 'adduser':
				echo "Usage: adduser [options] USERNAME\n",
					"add USERNAME in group\n",
					$messages['G'],
					$messages['h'];
				break;
			case 'deluser':
				echo "Usage: deluser [options] USERNAME\n",
					$messages['G'],
					$messages['h'];
				break;
			case 'mailadd':
				echo "Usage: mailadd [options] USERNAME\n",
					$messages['g'],
					$messages['h'],
					$messages['m'],
					$messages['p'],
					$messages['u'];
				break;
			case 'userlist':
				break;
			case 'grouplist':
				break;
		}
	}
}
?>
