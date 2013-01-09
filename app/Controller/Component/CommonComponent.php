<?php
/**
 * CommonComponentクラス
 *
 * <pre>
 * 共通で使うメソッド群
 * </pre>
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       App.Controllers.Components
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */
class CommonComponent extends Component {
/**
 * Controller
 *
 * @var     object
 */
	protected $_controller = null;

/**
 * Other components utilized by Component
 *
 * @var     array
 */
	public $components = array('Cookie');

/**
  * Start InitComponent for use in the controller
 *
 * @param Controller $controller
 * @return  void
 * @since   v 3.0.0.0
 */
	public function initialize(Controller $controller) {
		$this->_controller = $controller;
	}

	public function initializeAuth() {
		//ログイン処理を行うactionを指定
		$this->_controller->Auth->loginAction = '/users/login';//array('controller' => 'users', 'action' => 'login');

		$this->_controller->Auth->logoutRedirect = '/';

		//ユーザーIDとパスワードのフィールドを指定
		$this->_controller->Auth->authenticate = array('MyForm' =>
			array(
				'fields' => array('username' => 'login_id'),
				'scope' => array('User.is_active' => NC_USER_IS_ACTIVE_ON),
				'findFields' => array(
									'id', 'login_id', 'handle', 'username', 'authority_id',
									'permalink', 'myportal_page_id', 'private_page_id', 'avatar',
									'lang', 'timezone_offset', 'email', 'mobile_email', 'last_login',
									'Authority.hierarchy', 'Authority.myportal_use_flag', 'Authority.private_use_flag',
									'Authority.public_createroom_flag', 'Authority.group_createroom_flag', 'Authority.myportal_createroom_flag',
									'Authority.private_createroom_flag', 'Authority.allow_htmltag_flag', 'Authority.allow_layout_flag',
									'Authority.allow_attachment', 'Authority.allow_video', 'Authority.change_leftcolumn_flag',
									'Authority.change_rightcolumn_flag', 'Authority.change_headercolumn_flag', 'Authority.change_footercolumn_flag',
								),
			)
		);
		//$this->_controller->Auth->allow('login');

		//権限が無いactionを実行した際のエラーメッセージ
        $this->_controller->Auth->authError = __('Forbidden permission to access the page.');
        //ログイン後にリダイレクトするURL
        //$this->_controller->Auth->loginRedirect = '/users/index';
	}

	public function autoLogin($configs) {
		if($configs['autologin_use'] == NC_AUTOLOGIN_ON) {
			//認証でなかったユーザー。
			$this->Cookie->name = $configs['autologin_cookie_name'];
			$cookiePassport = $this->Cookie->Read('User');
			if(isset($cookiePassport['passport'])){
				//クッキーに記録したパスポートでログインしてみる
				$deadline = gmdate(NC_DB_DATE_FORMAT, strtotime("-".$configs['autologin_expires']));
				$options=array('conditions' => array('Passport.passport'=>$cookiePassport['passport'],'Passport.modified >'=>" $deadline"));
				$passport = $this->_controller->Passport->find("first",$options);
				// 100回に一度の確率で、有効期限がきれたパスポートの削除処理を行う。
				if(rand(0, 100) == 0) {
					$del_conditions = array('Passport.passport'=>$cookiePassport['passport'],'Passport.modified <='=>" $deadline");
					$this->_controller->Passport->deleteAll($del_conditions);
				}
				if($passport){
					//該当するパスポートが見つかった
					$conditions = array(
						'User.id' => $passport['Passport']['user_id']
					);
					if (!empty($this->_controller->Auth->authenticate['MyForm']['scope'])) {
						$conditions = array_merge($conditions, $this->_controller->Auth->authenticate['MyForm']['scope']);
					}
					$result = $this->_controller->User->find('first', array(
						'fields' => $this->_controller->Auth->authenticate['MyForm']['findFields'],
						'conditions' => $conditions
					));

					if (!empty($result['User'])) {
						$user = $result['User'];
						if(isset($result['Authority'])) {
							// 権限関連をAdd
							$user = array_merge($user, $result['Authority']);
						}

						if($this->_controller->Auth->login($user)){
							//ログインできたので、クッキーを更新してリダイレクトする
							$this->passportWrite($user, $passport, $configs);

							$this->_controller->Auth->loginRedirect = $this->redirectStartpage($this->configs);
							$this->_controller->flash(__('The automatic login.'), $this->_controller->Auth->redirect());
							return true;
						}
					}
				}
			}
		}
		return false;
	}

/**
 * 自動ログインパスポートキー削除
 * @param   array  $user
 * @param   array  $configs
 * @return  void
 * @since   v 3.0.0.0
 */
	public function passportDelete($user, $configs = array()) {
		$configs = isset($configs['autologin_cookie_name']) ? $configs : Configure::read(NC_CONFIG_KEY);
		$this->Cookie->name = $configs['autologin_cookie_name'];
		$cookiePassport = $this->Cookie->Read('User');

        $this->Cookie->delete('User');
        $this->_controller->Passport->passportDelete($user, $cookiePassport);
    }

/**
 * 自動ログインパスポートキー書き込み
 * @param   array  $user
 * @param   array  $passport
 * @param   array  $configs
 * @return  void
 * @since   v 3.0.0.0
 */
    public function passportWrite($user, $passport = array(), $configs = array()) {
    	$configs = isset($configs['autologin_expires']) ? $configs : Configure::read(NC_CONFIG_KEY);
        $this->Cookie->name = $configs['autologin_cookie_name'];
        $passport = $this->_controller->Passport->passportWrite($user, $passport);

        $cookie = array('passport' => $passport);
        $this->Cookie->write('User', $cookie, true,"+ ".$configs['autologin_expires']);
    }

/**
 * リダイレクトのURLを取得
 * @param   array  $configs
 * @return  string   $url
 * @since   v 3.0.0.0
 */
    public function redirectStartpage($configs) {
    	$redirect_url = $this->_redirectStartpage($configs['first_startpage_id']);
		if(!$redirect_url)
			$redirect_url = $this->_redirectStartpage($configs['second_startpage_id']);
		if(!$redirect_url)
			$redirect_url = $this->_redirectStartpage($configs['third_startpage_id']);
		if(!$redirect_url)
			$redirect_url = $this->_redirectStartpage($configs['fourth_startpage_id']);
		if(!$redirect_url)
			$redirect_url = '/';
		return $redirect_url;
    }
	protected function _redirectStartpage($page_id) {
    	$ret_url = false;
    	$user = $this->_controller->Auth->user();//認証済みユーザーを取得
		$user_id = isset($user['id']) ? intval($user['id']) : 0;

		if($user_id > 0 && $page_id < 0) {
			$buf_user = $this->_controller->User->findById($user_id);
			if($page_id == -2) {
				// マイポータル
				$page_id = $buf_user['User']['myportal_page_id'];
				$use_column = 'myportal_use_flag';
			} else if($page_id == -1) {
				// マイルーム
				$page_id = $buf_user['User']['private_page_id'];
				$use_column = 'private_use_flag';
			}
		}
		if($page_id == 0) {
			// パブリック
			$ret_url = '/';
		} else {
			$page = $this->_controller->Page->findAuthById($page_id, $user_id);
    		if($page['Authority']['hierarchy'] != NC_AUTH_OTHER) {
    			if(isset($use_column) && !$page['Authority'][$use_column]) {
    				// マイポータル OR マイルーム使用不可
    				return false;
    			}
				$ret_url = '/' . $this->_controller->Page->getPermalink($page['Page']['permalink'], $page['Page']['space_type']);
			}
		}

		return $ret_url;
    }
/**
 * URLのURLエンコード処理
 * (phpマニュアルより抜粋)
 * @param   string  $p_url
 * @return  string  $url
 * @since   v 3.0.0.0
 */
    public function linkEncode($p_url) {
    	$ta = array_merge (array(
    			'scheme' => '',
    			'user' => '',
    			'pass' => '',
    			'host' => '',
    			'port' => '',
    			'path' => '',
    			'query' => '',
    			'fragment' => '',
    	), parse_url($p_url));
    	if (!empty($ta['scheme'])) { $ta['scheme'].='://'; }
    	if (!empty($ta['pass']) and !empty($ta['user'])) {
    		$ta['user'].=':';
    		$ta['pass']=rawurlencode($ta['pass']).'@';
    	} elseif (!empty($ta['user'])) {
    		$ta['user'].='@';
    	}
    	if (!empty($ta['port']) and !empty($ta['host'])) {
    		$ta['host']=''.$ta['host'].':';
    	} elseif    (!empty($ta['host'])) {
    		$ta['host']=$ta['host'];
    	}
    	if (!empty($ta['path'])) {
    		$tu='';
    		$tok=strtok($ta['path'], "\\/");
    		while (strlen($tok)) {
    			$tu.=rawurlencode($tok).'/';
    			$tok=strtok("\\/");
    		}
    		$ta['path']='/'.trim($tu, '/');
    	}
    	if (!empty($ta['query'])) { $ta['query']='?'.$ta['query']; }
    	if (!empty($ta['fragment'])) { $ta['fragment']='#'.$ta['fragment']; }
    	return implode('', array($ta['scheme'], $ta['user'], $ta['pass'], $ta['host'], $ta['port'], $ta['path'], $ta['query'], $ta['fragment']));
    }
}