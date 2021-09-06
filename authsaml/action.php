<?php

/**
 * DokuWiki Plugin authsaml (Action Component)
 *
 * Can intercepts the 'login' action and redirects the user to the IdP
 * instead of showing the login form. 
 * 
 * @author  Sixto Martin <sixto.martin.garcia@gmail.com>
 * @author  Andreas Aakre Solberg, UNINETT, http://www.uninett.no
 * @author  François Kooman
 * @author  Thijs Kinkhorst, Universiteit van Tilburg
 * @author  Jorge Hervás <jordihv@gmail.com>, Lukas Slansky <lukas.slansky@upce.cz>

 * @license GPL2 http://www.gnu.org/licenses/gpl.html
 * @link https://github.com/pitbulk/dokuwiki-saml
 */

// must be run within Dokuwiki
if (! defined('DOKU_INC'))
    die();

if (! defined('DOKU_LF'))
    define('DOKU_LF', "\n");
if (! defined('DOKU_TAB'))
    define('DOKU_TAB', "\t");
if (! defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once DOKU_PLUGIN . 'action.php';

class action_plugin_authsaml extends DokuWiki_Action_Plugin
{

    protected $saml;

    /**
     * Register SAML event handlers
     */

    public function register(Doku_Event_Handler $controller)
    {

        require_once('saml.php');
        $this->loadConfig();
        $this->saml = new saml_handler($this->conf);


        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handle_login_form');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_login');
    }

    /**
     * Redirect Login Handler. Redirect to the IdP if force_saml_login is True
     */
    public function handle_login($event, $param)
    {
        global $ACT, $auth;
        
        $this->saml->get_ssp_instance();

        if ('login' == $ACT) {
            $force_saml_login = $this->getConf('force_saml_login');
            if ($force_saml_login) {
                $this->saml->ssp->requireAuth();
            }

            if ($this->saml->ssp->isAuthenticated()) {
                $username = $this->saml->getUsername();
                $this->saml->login($username);
            }
        }
        if ('logout' == $ACT) {
            if ($this->saml->ssp->isAuthenticated()) {
                $this->saml->slo();
            }
        }
    }

    /**
     * Insert link to SAML SP 
     */
    function handle_login_form(&$event, $param)
    {
        global $auth;

        $this->saml->get_ssp_instance();

        $fieldset  = '<fieldset height="400px" style="margin-bottom:20px;"><legend padding-top:-5px">'.$this->getLang('saml_connect').'</legend>';
        $fieldset .= '<center><a href="'.$this->saml->ssp->getLoginURL().'"><img src="lib/plugins/authsaml/logo.gif" alt="uniquid - saml"></a><br>';
        $fieldset .= $this->getLang('login_link').'</center></fieldset>';
        $event->data->insertElement(0, $fieldset);
    }

}
