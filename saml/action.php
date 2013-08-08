<?php

/**
 * DokuWiki SAML plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     This version by Sixto Pablo Martin Garcia (smartin@yaco.es)
 * @version    1.0.0
 */

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2, 
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * The license for this software can likely be found here: 
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_saml extends DokuWiki_Action_Plugin {

	/**
	 * Return some info
	 */
	function getInfo()
	{
		return array(
			'author' => 'Sixto Pablo Martin Garcia',
			'email'  => 'smartin@yaco.es',
			'date'   => '2011-11-05',
			'name'   => 'SAML plugin',
			'desc'   => 'Authenticate on a DokuWiki with SAML (simplesamlphp SP required)',
			'url'    => '',
		);
	}

	/**
	 * Register the eventhandlers
	 */
	function register(&$controller)
	{
		$controller->register_hook('HTML_LOGINFORM_OUTPUT',
			'BEFORE',
			$this,
			'handle_login_form',
			array());
		$controller->register_hook('ACTION_ACT_PREPROCESS',
			'BEFORE',
			$this,
			'handle_act_preprocess',
			array());
	}


	/* Returns the Consumer URL
	 */
	function _self($do)
	{
		global $ID;
		return wl($ID, 'do=' . $do, true, '&');
	}

	/**
	 * Redirect the user
	 */
	function _redirect($url)
	{
		header('Location: ' . $url);
		exit; 
	}

	/**
	 * Insert link to SAML SP 
	 */
	function handle_login_form(&$event, $param)
	{
		$fieldset  = '<fieldset height="400px" style="margin-bottom:20px;"><legend padding-top:-5px">'.$this->getLang('saml_connect').'</legend>';
		$fieldset .= '<center><a href="'.$this->_self('samllogin').'"><img src="lib/plugins/saml/logo.gif" alt="uniquid - saml"></a><br>';
		$fieldset .= $this->getLang('login_link').'</center></fieldset>';
		$pos = $event->data->findElementByAttribute('type', 'submit');
		$event->data->insertElement($pos-4, $fieldset);
	}

	/**
	 * Handles the saml action
	 */
	function handle_act_preprocess(&$event, $param)
	{
		global $ID, $conf, $auth;

		require_once(DOKU_PLUGIN.'saml/config.php');

		if ($event->data == 'samllogin') {
			
			// not sure this if it's useful there
			$event->stopPropagation();
			$event->preventDefault();
			

			include_once($simplesaml_path.'/lib/_autoload.php');

			$as = new SimpleSAML_Auth_Simple($simplesaml_authsource);
                        if(!$as->isAuthenticated()) {
                        	$as->login();
				exit();
                        }
			$saml_attributes = $as->getAttributes();
			$username = $saml_attributes[$simplesaml_uid][0];
			$user = $auth->getUserData($username);

			if(!$user) {
				if($auth->cando['addUser']) {
					if(!$this->register_user($username, $saml_attributes)) {
						$auth->sucess = false;
						//Exception error creating
					}
					else {
						$user = $auth->getUserData($username);
					}
				}
				else {
					$auth->sucess = false;
					//Exception not exist and cant create
				}
			}
			else {
				$this->update_user($username, $saml_attributes);
			}
			$this->login ($username, $user);
			$this->_redirect(wl($ID));
		}
		else if ($event->data == 'samllogout') {
                        include_once($simplesaml_path.'/lib/_autoload.php');
                        $as = new SimpleSAML_Auth_Simple($simplesaml_authsource);
                        if($as->isAuthenticated()) {
				$as->logout();
			}
			else {
				$this->logout();
			}
			$this->_redirect(wl($ID));
		}
        
		return; // fall through to what ever action was called
	}


	function login($username, $user) {
 		global $auth;
		// we must to change password
		$pass = auth_pwgen();
		$changes = array();
		$changes['pass'] = $pass;
		$auth->modifyUser($username, $changes);
		auth_login($username, $pass);
	}

	function register_user($username, $saml_attributes) {
		global $auth, $conf;
		$user = $username;
		$pass = auth_pwgen();
		$name = $saml_attributes['cn'][0];
		$mail = $saml_attributes['mail'][0];
		$grps = array($conf['defaultgroup']);
		return $auth->createUser($user, $pass, $name, $mail, $grps=null);
	}

	function update_user($username, $saml_attributes) {
		$changes = array();	
		if($auth->cando['modName']) {
			if(isset($saml_attributes['cn']) && !empty($saml_attributes['cn'][0])) {
				$changes['name'] = $saml_attributes['cn'][0];
			}
		}
		if($auth->cando['modMail']) {
                        if(isset($saml_attributes['mail']) && !empty($saml_attributes['mail'][0])) {
                                $changes['mail'] = $saml_attributes['mail'][0];
                        }
                }
		if(!empty($changes)) {
			$auth->modifyUser($username, $changes);
		}
	}

	function logout() {
		auth_logoff();
	}
}
