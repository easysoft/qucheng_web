<?php
/**
 * The control file of system module of QuCheng.
 *
 * @copyright Copyright 2021-2022 北京渠成软件有限公司(BeiJing QuCheng Software Co,LTD, www.qucheng.com)
 * @license   ZPL (http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author    Jianhua Wang <wangjianhua@easycorp.ltd>
 * @package   system
 * @version   $Id$
 * @link      https://www.qucheng.com
 */
class system extends control
{
    /**
     * System index.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->view->title = $this->lang->system->common;

        $this->display();
    }

    /**
     * Show database list.
     *
     * @access public
     * @return void
     */
    public function dbList()
    {
        $this->app->loadLang('instance');

        $this->view->title  = $this->lang->system->dbManagement;
        $this->view->dbList = $this->loadModel('cne')->allDBList();

        $this->display();
    }

    /**
     * Test the connection of LDAP by post parameters.
     *
     * @access public
     * @return void
     */
    public function testLDAPConnection()
    {
        $settings = fixer::input('post')
            ->setDefault('host', '')
            ->setDefault('port', '')
            ->setDefault('bindDN', '')
            ->setDefault('bindPass', '')
            ->setDefault('baseDN', '')
            ->get();

        $success = $this->system->testLDAPConnection($settings);
        if($success) return $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->verifyLDAPSuccess));

        return $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->verifyLDAPFailed));
    }

    /**
     * Install LDAP
     *
     * @access public
     * @return void
     */
    public function installLDAP()
    {
        if($this->system->hasSystemLDAP()) return print(js::locate($this->inLink('ldapView')));

        $channel = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;
        $ldapApp = $this->loadModel('store')->getAppInfoByChart('openldap', $channel, false);
        if($_POST)
        {
            $postData = fixer::input('post')->setDefault('source', 'qucheng')->get();
            if($postData->source == 'qucheng')
            {
                $this->system->installQuchengLDAP($ldapApp, $channel);
            }
            else if($postData->source == 'extra')
            {
                $this->system->configExtraLDAP((object)$postData->extra);
            }
            else
            {
                dao::$errors[] = $this->lang->system->notSupportedLDAP;
            }

            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->ldapInstallSuccess, 'locate' => $this->inLink('ldapView')));
        }

        $this->lang->switcherMenu = $this->system->getLDAPSwitcher();

        $this->view->title        = $this->lang->system->ldapManagement;
        $this->view->ldapApp      = $ldapApp;
        $this->view->activeLDAP   = $this->system->getActiveLDAP();
        $this->view->ldapSettings = $this->system->getExtraLDAPSettings();

        $this->display();
    }

    /**
     * Edit extra LDAP.
     *
     * @access public
     * @return void
     */
    public function editLDAP()
    {
        $channel = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;
        $ldapApp = $this->loadModel('store')->getAppInfoByChart('openldap', $channel, false);
        if($_POST)
        {
            session_write_close();
            $this->system->updateLDAP($ldapApp, $channel);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->ldapUpdateSuccess, 'locate' => $this->inLink('ldapView')));
        }

        $this->view->title = $this->lang->system->editLDAP;


        $this->view->ldapApp      = $ldapApp;
        $this->view->activeLDAP   = $this->system->getActiveLDAP();
        $this->view->ldapSettings = $this->system->getExtraLDAPSettings();
        $this->display();
    }

    /**
     * ajaxUpdatingLDAPProgress
     *
     * @access public
     * @return void
     */
    public function ajaxUpdatingLDAPProgress()
    {
        session_write_close();

        $number = $this->loadModel('setting')->getItem('owner=system&module=common&section=ldap&key=updatingProgress');
        echo sprintf($this->lang->system->LDAP->updatingProgress, intval($number));
    }

    /**
     * LDAP view.
     *
     * @access public
     * @return void
     */
    public function ldapView()
    {
        $this->loadModel('instance');
        $this->app->loadLang('instance');

        $ldapInstance = new stdclass;
        $ldapInstance->id = 0;

        $activeLDAP = $this->loadModel('setting')->getItem('owner=system&module=common&section=ldap&key=active');
        if($activeLDAP == 'qucheng')
        {
            $instanceID   = $this->loadModel('setting')->getItem('owner=system&module=common&section=ldap&key=instanceID');
            $ldapInstance = $this->instance->getByID($instanceID);
            if(empty($ldapInstance)) return print js::alert($this->lang->system->notices->noLDAP);

            $ldapInstance = $this->instance->freshStatus($ldapInstance);
            $ldapSettings = json_decode($ldapInstance->ldapSettings);
        }
        else if($activeLDAP == 'extra')
        {
            $ldapSettings = $this->system->getExtraLDAPSettings();
        }
        else
        {
            return print js::alert($this->lang->system->notices->noLDAP);
        }

        $this->lang->switcherMenu = $this->system->getLDAPSwitcher();

        $this->view->title = $this->lang->system->ldapManagement;

        $this->view->activeLDAP   = $activeLDAP;
        $this->view->instanceID   = $ldapInstance->id;
        $this->view->ldapInstance = $ldapInstance;
        $this->view->ldapSettings = $ldapSettings;

        $this->display();
    }

    /**
     * Uninstall all LDAP in system. (This function is only for debug and test.)
     *
     * @access public
     * @return void
     */
    public function uninstallLDAP()
    {
        if(!$this->config->debug) return; // Only run in debug mode.

        /* 1. uninstall QuCheng LDAP. */
        if($this->system->uninstallQuChengLDAP())
        {
            echo date('Y-m-d H:i:s') . ": Uninstall QuCheng LDAP success.<br/>";
        }
        else
        {
            echo date('Y-m-d H:i:s') . ": Uninstall QuCheng LDAP fail.<br/>";
            $errors = dao::getError();
            foreach($errors as $error) echo $error . '<br/>';
        }

        /* 2. uninstall extra LDAP. */
        if($this->system->uninstallExtraLDAP())
        {
            echo date('Y-m-d H:i:s') . ": Uninstall extra LDAP success.<br/>";
        }
        else{
            echo date('Y-m-d H:i:s') . ": Uninstall extra LDAP fail.<br/>";
            $errors = dao::getError();
            foreach($errors as $error) echo $error . '<br/>';
        }
    }

    /**
     * OSS view.
     *
     * @access public
     * @return void
     */
    public function ossView()
    {
        $this->loadModel('cne');

        $minioInstance = new stdclass;
        $minioInstance->k8name    = 'cne-operator';
        $minioInstance->spaceData = new stdclass;
        $minioInstance->spaceData->k8space = $this->config->k8space;

        $ossAccount = $this->cne->getDefaultAccount($minioInstance, '', 'minio');
        $ossDomain  = $this->cne->getDomain($minioInstance, '', 'minio');
        $this->lang->switcherMenu = $this->system->getOssSwitcher();

        $this->view->title      = $this->lang->system->oss->common;
        $this->view->ossAccount = $ossAccount;
        $this->view->ossDomain  = $ossDomain;

        $this->display();
    }

    /**
     * Install SMTP.
     *
     * @access public
     * @return void
     */
    public function installSMTP()
    {
        if($this->system->smtpSnippetName()) return print(js::locate($this->inLink('smtpView')));

        if($_POST)
        {
            $channel = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;
            $this->system->installSysSMTP($channel);

            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->smtpInstallSuccess, 'locate' => $this->inLink('smtpView')));
        }

        $this->lang->switcherMenu = $this->system->getSMTPSwitcher();

        $this->view->title        = $this->lang->system->SMTP->common;
        $this->view->smtpSettings = $this->system->getSMTPSettings();
        $this->view->smtpLinked   = false;
        $this->view->activeSMTP   = false;

        $this->display();
    }

    /**
     * Edit SMTP.
     *
     * @access public
     * @return void
     */
    public function editSMTP()
    {
        $this->loadModel('instance');
        $this->app->loadLang('instance');

        if($_POST)
        {
            $channel  = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;
            $postData = fixer::input('post')->setDefault('source', 'qucheng')->get();
            $this->system->updateSMTPSettings();

            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->smtpUpdateSuccess, 'locate' => $this->inLink('smtpView')));
        }

        $this->lang->switcherMenu = $this->system->getSMTPSwitcher();

        $this->view->title        = $this->lang->system->SMTP->editSMTP;
        $this->view->smtpSettings = $this->system->getSMTPSettings();
        $this->view->smtpLinked   = $this->instance->countSMTP();
        $this->view->activeSMTP   = zget($this->view->smtpSettings, 'enabled', false);

        $this->display();
    }

    /**
     * Show SMTP setting detail.
     *
     * @access public
     * @return void
     */
    public function smtpView()
    {
        $this->loadModel('instance');
        $this->app->loadLang('instance');

        $instanceID   = $this->loadModel('setting')->getItem('owner=system&module=common&section=smtp&key=instanceID');
        $smtpInstance = $this->instance->getByID($instanceID);
        if(empty($smtpInstance)) return print js::alert($this->lang->system->notices->notFoundSMTPService);


        $this->lang->switcherMenu = $this->system->getSMTPSwitcher();

        $this->view->title        = $this->lang->system->SMTP->common;
        $this->view->smtpSettings = $this->system->getSMTPSettings();
        $this->view->smtpInstance = $smtpInstance;

        $this->display();
    }

    /**
     * Uninstall SMTP. (This function is only for debug and test.)
     *
     * @access public
     * @return void
     */
    public function uninstallSMTP()
    {
        if(!$this->config->debug) return; // Only run in debug mode.

        /* Uninstall system SMTP proxy APP. */
        if($this->system->uninstallSysSMTP())
        {
            echo date('Y-m-d H:i:s') . ": Uninstall system SMTP success.<br/>";
        }
        else
        {
            echo date('Y-m-d H:i:s') . ": Uninstall system SMTP fail.<br/>";
            $errors = dao::getError();
            foreach($errors as $error) echo $error . '<br/>';
        }
    }

    /**
     * Config customer's domain.
     *
     * @access public
     * @return void
     */
    public function configDomain()
    {
        $domainSettings = $this->system->getDomainSettings();
        if($domainSettings->customDomain) return print(js::locate($this->inLink('domainView')));

        return print(js::locate($this->inLink('editDomain')));
    }

    /**
     * Edit customer's domain.
     *
     * @access public
     * @return void
     */
    public function editDomain()
    {
        $this->loadModel('instance');

        if($_POST)
        {
            session_write_close();
            $this->system->saveDomainSettings();
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::$errors));

            return $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->updateDomainSuccess, 'locate' => $this->inlink('domainView')));
        }

        $this->view->title          = $this->lang->system->domain->common;
        $this->view->domainSettings = $this->system->getDomainSettings();

        $this->display();
    }

    /**
     * AjaxValidCert
     *
     * @access public
     * @return void
     */
    public function ajaxValidateCert()
    {
        $certData = fixer::input('post')->get();

        $this->dao->select('*')->from('system')->data($certData)
            ->batchCheck('customDomain,certPem,certKey', 'notempty');
        if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::$errors));

        if(!validater::checkREG($certData->customDomain, '/^((?!-)[a-z0-9-]{1,63}(?<!-)\\.)+[a-z]{2,6}$/'))
        {
            return $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->invalidDomain));
        }

        $certName = 'tls-' . str_replace('.', '-',$certData->customDomain);
        $result = $this->loadModel('cne')->validateCert($certName, $certData->certPem, $certData->certKey, $certData->customDomain);
        if($result->code == 200) return $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->validCert));

        return $this->send(array('result' => 'fail', 'message' => $result->message));
    }

    /**
     * Show progress of updating domains.
     *
     * @access public
     * @return void
     */
    public function ajaxUpdatingDomainProgress()
    {
        session_write_close();

        $oldDomainQty  = $this->loadModel('instance')->countOldDomain();
        return print(sprintf($this->lang->system->domain->updatingProgress, $oldDomainQty));
    }

    /**
     * Domain settings view.
     *
     * @access public
     * @return void
     */
    public function domainView()
    {
        $domainSettings = $this->system->getDomainSettings();
        $certName       = 'tls-' . str_replace('.', '-', $domainSettings->customDomain);
        $cert           = $this->loadModel('cne')->certInfo($certName);

        $notAfter = zget($cert, 'not_after', '');
        if($notAfter) $cert->expiredDate = date('Y-m-d H:i:s', $notAfter);

        $this->view->title          = $this->lang->system->domain->common;
        $this->view->domainSettings = $domainSettings;
        $this->view->cert           = $cert;

        $this->display();
    }

    /**
     * Config Server Load Balancing.
     *
     * @access public
     * @return void
     */
    public function configSLB()
    {
        $slbSettings = $this->system->getSLBSettings();
        if($slbSettings->name) return print(js::locate($this->inLink('slbView')));

        return print(js::locate($this->inLink('editSLB')));
    }

    /**
     * Edit SLB.
     *
     * @access public
     * @return void
     */
    public function editSLB()
    {
        if($_POST)
        {
            $this->system->saveSLBSettings();
            if(dao::isError())
            {
                return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            }

            return $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->configSLBSuccess, 'locate' => $this->inLink('SLBView')));
        }

        $this->view->title       = $this->lang->system->SLB->common;
        $this->view->SLBSettings = $this->system->getSLBSettings();

        $this->display();
    }

    /**
     * SLB View.
     *
     * @access public
     * @return void
     */
    public function SLBView()
    {
        $this->view->title       = $this->lang->system->SLB->common;
        $this->view->slbSettings = $this->system->getSLBSettings();

        $this->display();
    }

    /**
     * Verify SMTP account by ajax.
     *
     * @access public
     * @return void
     */
    public function ajaxVerifySMTPAccount()
    {
        $accountInfo= fixer::input('post')->get();

        $passed = $this->loadModel('cne')->validateSMTP($accountInfo);
        if($passed) $this->send(array('result' => 'success', 'message' => $this->lang->system->notices->verifySMTPSuccess));

        $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->verifySMTPFailed));
    }

    /**
     * Generate database auth parameters and jump to login page.
     *
     * @access public
     * @return void
     */
    public function ajaxDBAuthUrl()
    {
        $post = fixer::input('post')
            ->setDefault('namespace', 'default')
            ->get();
        if(empty($post->dbName)) return $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->dbNameIsEmpty));

        $detail = $this->loadModel('cne')->dbDetail($post->dbName, $post->namespace);
        if(empty($detail)) return $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->notFoundDB));

        $this->app->loadConfig('instance');
        $dbAuth = array();
        $dbAuth['driver']   = zget($this->config->instance->adminer->dbTypes, $post->dbType, '');
        $dbAuth['server']   = $detail->host . ':' . $detail->port;
        $dbAuth['username'] = $detail->username;
        $dbAuth['db']       = $detail->database;
        $dbAuth['password'] = $detail->password;

        $url = '/adminer?'. http_build_query($dbAuth);
        $this->send(array('result' => 'success', 'message' => '', 'data' => array('url' => $url)));
    }

    /**
     * Get LDAP info by ajax.
     *
     * @access public
     * @return void
     */
    public function ajaxLdapInfo()
    {
        $instanceID   = $this->loadModel('setting')->getItem('owner=system&module=common&section=ldap&key=instanceID');
        $ldapInstance = $this->loadModel('instance')->getByID($instanceID);
        if(empty($ldapInstance)) return $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->notFoundLDAP));

        $ldapSetting = json_decode($ldapInstance->ldapSettings);

        $secretKey = helper::readKey();
        $password = openssl_decrypt($ldapSetting->auth->password, 'DES-ECB', $secretKey);
        if(!$password) $password = openssl_decrypt($ldapSetting->auth->password, 'DES-ECB', $ldapInstance->createdAt); // Secret key was createdAt field value less v2.2.

        $this->send(array('result' => 'success', 'message' => '', 'data' => array('domain' => $this->instance->url($ldapInstance), 'account' => $ldapSetting->auth->username, 'pass' => $password)));
    }

    /**
     * Get oss account and domain by ajax.
     *
     * @access public
     * @return void
     */
    public function ajaxOssInfo()
    {
        $minioInstance = new stdclass;
        $minioInstance->k8name    = 'cne-operator';
        $minioInstance->spaceData = new stdclass;
        $minioInstance->spaceData->k8space = $this->config->k8space;

        $ossAccount = $this->loadModel('cne')->getDefaultAccount($minioInstance, '', 'minio');

        $ossDomain  = $this->cne->getDomain($minioInstance, '', 'minio');
        $ossDomain->domain = $ossDomain->access_host;

        $url = $this->loadModel('instance')->url($ossDomain);

        if($ossAccount and $ossAccount) return  $this->send(array('result' => 'success', 'message' => '', 'data' => array('account' => $ossAccount, 'url' => $url)));

        $this->send(array('result' => 'fail', 'message' => $this->lang->system->errors->failGetOssAccount));
    }
}
