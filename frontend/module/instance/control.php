<?php
/**
 * The control file of instance module of QuCheng.
 *
 * @copyright Copyright 2021-2022 北京渠成软件有限公司(BeiJing QuCheng Software Co,LTD, www.qucheng.com)
 * @license   ZPL (http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author    Jianhua Wang <wangjianhua@easycorp.ltd>
 * @package   instance
 * @version   $Id$
 * @link      https://www.qucheng.com
 */
class instance extends control
{
    /**
     * Construct function.
     *
     * @param  string $moduleName
     * @param  string $methodName
     * @access public
     * @return void
     */
    public function __construct($moduleName = '', $methodName = '')
    {
        parent::__construct($moduleName, $methodName);
        $this->loadModel('action');
        $this->loadModel('cne');
        $this->loadModel('store');
    }

    /**
     * Show instance view.
     *
     * @param  int $id
     * @param  int $recTotal
     * @param  int $recPerPage
     * @param  int $page
     * @access public
     * @return void
     */
    public function view($id, $recTotal = 0, $recPerPage = 20, $pageID = 1, $tab ='baseinfo' )
    {
        $this->loadModel('system');
        $this->app->loadLang('system');

        $instance = $this->instance->getByID($id);
        if(empty($instance)) return print(js::alert($this->lang->instance->instanceNotExists) . js::locate($this->createLink('space', 'browse')));

        $instance = $this->instance->freshStatus($instance);

        $instanceMetric = $this->cne->instancesMetrics(array($instance));
        $instanceMetric = $instanceMetric[$instance->id];
        $this->lang->switcherMenu = $this->instance->getSwitcher($instance);

        $this->app->loadClass('pager', true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $backupList   = array();
        $latestBackup = new stdclass;
        if($tab == 'backup') $backupList = $this->instance->backupList($instance);
        if(count($backupList)) $latestBackup = reset($backupList);

        $hasRestoreLog = false;
        foreach($backupList as $backup)
        {
            $backup->latest_restore_time   = 0;
            $backup->latest_restore_status = '';
            foreach($backup->restores as $restore)
            {
                $hasRestoreLog = true;
                if($restore->create_time > $backup->latest_restore_time)
                {
                    $backup->latest_restore_time   = $restore->create_time;
                    $backup->latest_restore_status = $restore->status;
                }
            }
        }

        $dbList          = new stdclass;
        $currentResource = new stdclass;
        $customItems     = array();
        if($tab == 'advance')
        {
            $dbList          = $this->cne->appDBList($instance);
            $currentResource = $this->cne->getAppConfig($instance);
            $customItems     = $this->cne->getCustomItems($instance);
        }

        $this->view->title           = $instance->appName;
        $this->view->instance        = $instance;
        $this->view->cloudApp        = $this->loadModel('store')->getAppInfoByChart($instance->chart, $instance->channel, false);
        $this->view->seniorAppList   = $tab == 'baseinfo' ? $this->instance->seniorAppList($instance, $instance->channel) :  array();
        $this->view->logs            = $this->action->getList('instance', $id, 'date desc', $pager);
        $this->view->defaultAccount  = $this->cne->getDefaultAccount($instance);
        $this->view->instanceMetric  = $instanceMetric;
        $this->view->currentResource = $currentResource;
        $this->view->customItems     = $customItems;
        $this->view->backupList      = $backupList;
        $this->view->hasRestoreLog   =  $hasRestoreLog;
        $this->view->latestBackup    = $latestBackup;
        $this->view->dbList          = $dbList;
        $this->view->domain          = $this->cne->getDomain($instance);
        $this->view->tab             = $tab;
        $this->view->pager           = $pager;

        $this->display();
    }

    /**
     * Display or save auto backup settings.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function backupSettings($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);

        if($_POST)
        {
            $this->instance->saveAutoBackupSettings($instance);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $backupSettings = $this->instance->getAutoBackupSettings($instanceID);

            $startTime = strtotime($backupSettings->backupTime);
            if($startTime < time()) $startTime = strtotime("+1 day $backupSettings->backupTime");

            if($backupSettings->autoBackup)
            {
                $startBackupMessage = sprintf($this->lang->instance->backup->firstStartTime, $instance->name, date('Y-m-d H:i:s', $startTime));
                return $this->send(array('result' => 'success', 'message' => $startBackupMessage));
            }

            return $this->send(array('result' => 'success', 'message' => $this->lang->instance->backup->disableAutoBackup));
        }

        $backupSettings = $this->instance->getAutoBackupSettings($instanceID);

        $this->view->title          = $this->lang->instance->backup->autoBackup;
        $this->view->instance       = $instance;
        $this->view->backupSettings = $backupSettings;

        $this->display();
    }

    /**
     * Display or save auto restore settings.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function restoreSettings($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);

        if($_POST)
        {
            $this->instance->saveAutoRestoreSettings($instance);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $restoreSettings = $this->instance->getAutoRestoreSettings($instanceID);

            $startTime = strtotime($restoreSettings->restoreTime);
            if($startTime < time()) $startTime = strtotime("+1 day $restoreSettings->restoreTime");

            if($restoreSettings->autoRestore)
            {
                $startRestoreMessage = sprintf($this->lang->instance->restore->firstStartTime, $instance->name, date('Y-m-d H:i:s', $startTime));
                return $this->send(array('result' => 'success', 'message' => $startRestoreMessage));
            }

            return $this->send(array('result' => 'success', 'message' => $this->lang->instance->restore->disableAutoRestore));
        }

        $this->view->title           = $this->lang->instance->restore->autoRestore;
        $this->view->instance        = $instance;
        $this->view->restoreSettings = $this->instance->getAutoRestoreSettings($instanceID);

        $this->display();
    }

    /**
     * Cron task of auto backup.
     *
     * @param  string $key
     * @access public
     * @return void
     */
    public function autoBackup($key)
    {
        if($this->config->instance->enableAutoRestore) return; // Only one of auto backup and auto restore can be enabled.

        $this->app->saveLog('Run auto backup at: ' . date('Y-md-d H:i:s'));

        if($key != helper::readKey()) return;

        $this->instance->autoBackup();
    }

    /**
     * Cron task of auto restore.
     *
     * @param  string $key
     * @access public
     * @return void
     */
    public function autoRestore($key)
    {
        if(!$this->config->instance->enableAutoRestore) return; // Only one of auto backup and auto restore can be enabled.

        $this->app->saveLog('Run auto restore at: ' . date('Y-md-d H:i:s'));

        if($key != helper::readKey()) return;

        $this->instance->autoRestore();
    }

    /**
     * Edit instance app name.
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function editName($id)
    {
        $instance = $this->instance->getByID($id);

        if(!empty($_POST))
        {
            $newInstance = fixer::input('post')->trim('name')->get();
            $this->instance->updateByID($id, $newInstance);
            if(dao::isError())
            {
                $this->action->create('instance', $instance->id, 'editname', '', json_encode(array('result' => array('result' => 'fail'), 'data' => array('oldName' => $instance->name, 'newName' => $newInstance->name))));
                return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            }

            $this->action->create('instance', $instance->id, 'editname', '', json_encode(array('result' => array('result' => 'success'), 'data' => array('oldName' => $instance->name, 'newName' => $newInstance->name))));
            return print(js::closeModal('parent.parent', 'this', "function(){parent.parent.location.reload();}"));
        }

        $this->view->title    = $instance->name;
        $this->view->instance = $instance;

        $this->display();
    }

    /**
     * Upgrade to senior serial.
     *
     * @param  int    $instanceID
     * @param  int    $seniorAppID
     * @param  string $confirm
     * @access public
     * @return void
     */
    public function toSenior($instanceID, $seniorAppID, $confirm = 'no')
    {
        $instance = $this->instance->getByID($instanceID);
        $cloudApp = $this->store->getAppInfo($seniorAppID, $instance->channel, false);
        if(empty($cloudApp)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->noAppInfo));

        if($confirm == 'yes')
        {
            $success = $this->instance->upgradeToSenior($instance, $cloudApp);
            if($success) $this->send(array('result' => 'success', 'message' => '', 'locate' => $this->inLink('view', "id=$instance->id")));

            $this->send(array('result' => 'fail', 'message' => dao::getError()));
        }

        $this->view->title    = $this->lang->instance->upgradeToSenior;
        $this->view->instance = $instance;
        $this->view->cloudApp = $cloudApp;

        $this->display();
    }

    /**
     * Upgrade instnace
     *
     * @param  int    $id
     * @access public
     * @return mixed
     */
    public function upgrade($id)
    {
        $instance = $this->instance->getByID($id);
        $instance->latestVersion = $this->store->appLatestVersion($instance->appID, $instance->version);

        if($_POST)
        {
            if(empty($instance->latestVersion)) $this->send(array('result' => 'fail', 'message' => $this->lang->instance->noHigherVersion));

            $postData = fixer::input('post')->get();

            if($postData->confirm == 'yes') $success = $this->instance->upgrade($instance, $instance->latestVersion->version, $instance->latestVersion->app_version);

            $logExtra = array('result' => 'success', 'data' => array('oldVersion' => $instance->appVersion, 'newVersion' => $instance->latestVersion->app_version));
            if(!$success)
            {
                $logExtra['result'] = 'fail';
                $this->action->create('instance', $instance->id, 'upgrade', '', json_encode($logExtra));
                $this->send(array('result' => 'fail', 'message' => $this->lang->instance->notices['upgradeFail']));
            }

            $this->action->create('instance', $instance->id, 'upgrade', '', json_encode($logExtra));
            $this->send(array('result' => 'success', 'message' => $this->lang->instance->notices['upgradeSuccess'], 'locate' => $this->createLink('space', 'browse'), 'target' => '_self'));
        }

        $this->view->title       = $this->lang->instance->upgrade . $instance->name;
        $this->view->instance    = $instance;

        $this->display();
    }

    /**
     * (Not used at present.) Install app by custom settings.
     *
     * @param int $id
     * @access public
     * @return void
     */
    public function customInstall($id)
    {
        // Disable custom installation in version 1.0.
        $storeUrl = $this->createLink('store', 'appview', "id=$id");
        return js::execute("window.parent.location.href='{$storeUrl}';");

        $cloudApp = $this->sotre->getAppInfo($id);
        if(empty($cloudApp)) return print(js::locate('back', 'parent'));

        $components = $this->store->getAppSettings($id);

        if(!empty($_POST))
        {
            $postSettings = fixer::input('post')->get();
            foreach($postSettings as $key => $value) if(strpos($key, 'replicas') !== false && $value < 1) $this->send(array('result' => 'fail', 'message' => $this->lang->instance->caplicasTooSmall));

            if(!$this->instance->install($cloudApp, $postSettings)) return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'installFail')));

            return $this->send(array('result'=>'success', 'message' => '', 'locate' => $this->createLink('space', 'browse')));
        }

        $this->lang->switcherMenu = $this->instance->getCustomInstallSwitcher($cloudApp);

        $this->view->position[] = $this->lang->instance->customInstall;

        $this->view->title      = $this->lang->instance->customInstall;
        $this->view->activeTab  = isset($components[0]) ? $components[0]->name : '';
        $this->view->components = $components;
        $this->view->appID      = $id;

        $this->display();
    }

    /**
     * Install app.
     *
     * @param  int    $appID
     * @access public
     * @return void
     */
    public function install($appID)
    {
        $cloudApp = $this->store->getAppInfo($appID);
        if(empty($cloudApp)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->noAppInfo));

        if(empty($this->config->demoAccounts))
        {
            $clusterResource = $this->cne->cneMetrics();
            $freeMemory = intval($clusterResource->metrics->memory->allocatable * 0.9); // Remain 10% memory for system.
            if($cloudApp->memory > $freeMemory)
            {
                $this->view->cloudApp       = $cloudApp;
                $this->view->gapMemory      = helper::formatKB(intval(($cloudApp->memory - $freeMemory) / 1024));
                $this->view->requiredMemory = helper::formatKB(intval($cloudApp->memory / 1024));
                $this->view->freeMemory     = helper::formatKB(intval($freeMemory / 1024));

                return $this->display('instance','resourceerror');
            }
        }

        $versionList = $this->store->appVersionList($cloudApp->id);
        $mysqlList   = $this->cne->sharedDBList('mysql');
        $pgList      = $this->cne->sharedDBList('postgresql');
        $customData  = new stdclass;
        if(!empty($_POST))
        {
            $customData = fixer::input('post')
                ->trim('customName')->setDefault('customName', '')
                ->trim('customDomain')->setDefault('customDomain', null)
                ->trim('version')->setDefault('version', '')
                ->trim('dbType')->setDefault('dbType', 'unsharedDB')
                ->trim('dbService')
                ->setDefault('app_version', '')
                ->get();
            if($customData->version && isset($versionList[$customData->version])) $customData->app_version = $versionList[$customData->version]->app_version;

            if(isset($this->config->instance->keepDomainList[$customData->customDomain]) || $this->instance->domainExists($customData->customDomain)) return $this->send(array('result' => 'fail', 'message' => $customData->customDomain . $this->lang->instance->errors->domainExists));

            if(!validater::checkLength($customData->customDomain, 20, 2))      return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->domainLength));
            if(!validater::checkREG($customData->customDomain, '/^[a-z\d]+$/')) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->wrongDomainCharacter));

            /* If select the version, replace the latest version of App by selected version. */
            if($customData->version)
            {
                $cloudApp->version     = $customData->version;
                $cloudApp->app_version = $customData->app_version;
            }

            $sharedDB = new stdclass;
            if(isset($cloudApp->dependencies->mysql) && $customData->dbType == 'sharedDB')
            {
                $sharedDB = zget($mysqlList, $customData->dbService);
            }
            elseif(isset($cloudApp->dependencies->postgresql)  && $customData->dbType == 'sharedDB')
            {
                $sharedDB = zget($pgList, $customData->dbService);
            }
            $instance = $this->instance->install($cloudApp, $sharedDB, $customData);
            if(!$instance) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->notices['installFail']));

            unset($_GET['onlybody']);
            $this->send(array('result' => 'success', 'message' => $this->lang->instance->notices['installSuccess'], 'locate' => $this->createLink('instance', 'view', "id=$instance->id", '', false)));
        }

        $this->lang->switcherMenu = $this->instance->getInstallSwitcher($cloudApp);

        $this->view->position[] = $this->view->title;

        $this->view->title       = $this->lang->instance->install . $cloudApp->alias;
        $this->view->cloudApp    = $cloudApp;

        $this->view->versionList = array();
        foreach($versionList as $version) $this->view->versionList[$version->version] = $version->app_version . " ({$version->version})";

        $this->view->thirdDomain = $this->instance->randThirdDomain();
        $this->view->mysqlList   = $this->instance->dbListToOptions($mysqlList);
        $this->view->pgList      = $this->instance->dbListToOptions($pgList);

        $this->display();
    }

    /**
     * Uninstall app instance.
     *
     * @param  int $instanceID
     * @access public
     * @return void
     */
    public function ajaxUninstall($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        if(!$instance) return $this->send(array('result' => 'success', 'message' => $this->lang->instance->notices['success']));

        $success = $this->instance->uninstall($instance);
        $this->action->create('instance', $instance->id, 'uninstall', '', json_encode(array('result' => $success, 'app' => array('alias' => $instance->appName, 'app_version' => $instance->version))));
        if($success) return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'uninstallSuccess'), 'locate' => $this->createLink('space', 'browse')));

        return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'uninstallFail')));
    }

    /**
     * Start app instance.
     *
     * @param  int $instanceID
     * @access public
     * @return void
     */
    public function ajaxStart($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        if(!$instance) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->instanceNotExists));

        $result = $this->instance->start($instance);
        $this->action->create('instance', $instance->id, 'start', '', json_encode(array('result' => $result, 'app' => array('alias' => $instance->appName, 'app_version' => $instance->version))));

        if($result->code == 200) return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'startSuccess')));

        return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'startFail')));
    }

    /**
     * Stop app instance.
     *
     * @param  int $instanceID
     * @access public
     * @return void
     */
    public function ajaxStop($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        if(!$instance) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->instanceNotExists));

        $result = $this->instance->stop($instance);
        $this->action->create('instance', $instance->id, 'stop', '', json_encode(array('result' => $result, 'app' => array('alias' => $instance->appName, 'app_version' => $instance->version))));
        if($result->code == 200) return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'stopSuccess')));

        return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'stopFail')));
    }

    /**
     * Query status of app instance.
     *
     * @access public
     * @return void
     */
    public function ajaxStatus()
    {
        $postData = fixer::input('post')->setDefault('idList', array())->get();

        $instances  = $this->instance->getByIdList($postData->idList);
        $statusList = $this->instance->batchFresh($instances);

        return $this->send(array('result' => 'success', 'data' => $statusList));
    }

    /**
     * Backup instnacd by ajax.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function ajaxBackup($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        $success = $this->instance->backup($instance, $this->app->user);
        if(!$success)
        {
            $this->action->create('instance', $instance->id, 'backup', '', json_encode(array('result' => array('result' => 'fail'))));
            return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'backupFail')));
        }

        $this->action->create('instance', $instance->id, 'backup', '', json_encode(array('result' => array('result' => 'success'))));
        return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'backupSuccess')));
    }

    /**
     * Restore instance by ajax
     *
     * @access public
     * @return void
     */
    public function ajaxRestore()
    {
        $postData = fixer::input('post')
            ->trim('instanceID')
            ->trim('backupName')->get();

        if(empty($postData->instanceID) || empty($postData->backupName)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->wrongRequestData));

        $instance = $this->instance->getByID($postData->instanceID);
        if(empty($instance))return print(js::alert($this->lang->instance->instanceNotExists) . js::locate($this->createLink('space', 'browse')));

        $success = $this->instance->restore($instance, $this->app->user, $postData->backupName);
        if(!$success)
        {
            $this->action->create('instance', $instance->id, 'restore', '', json_encode(array('result' => array('result' => 'fail'))));
            return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'restoreFail')));
        }

        $this->action->create('instance', $instance->id, 'restore', '', json_encode(array('result' => array('result' => 'success'))));
        return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'restoreSuccess')));
    }

    /**
     * Delete backup by ajax.
     *
     * @param  int    $backupID
     * @access public
     * @return void
     */
    public function ajaxDeleteBackup($backupID)
    {
        $success = $this->instance->deleteBackup($backupID, $this->app->user);
        if(!$success) return $this->send(array('result' => 'fail', 'message' => zget($this->lang->instance->notices, 'deleteFail')));

        return $this->send(array('result' => 'success', 'message' => zget($this->lang->instance->notices, 'deleteSuccess')));
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
            ->setDefault('instanceID', 0)
            ->setDefault('dbType', '')
            ->get();
        if(empty($post->dbName)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->dbNameIsEmpty));

        $instance = $this->instance->getByID($post->instanceID);
        if(empty($instance)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->instanceNotExists));

        $detail = $this->loadModel('cne')->appDBDetail($instance, $post->dbName);
        if(empty($detail)) return $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->notFoundDB));

        $dbAuth = array();
        $dbAuth['driver']   = zget($this->config->instance->adminer->dbTypes, $post->dbType, '');
        $dbAuth['server']   = $detail->host . ':' . $detail->port;
        $dbAuth['username'] = $detail->username;
        $dbAuth['db']       = $detail->database;
        $dbAuth['password'] = $detail->password;

        $url = '/adminer?' . http_build_query($dbAuth);
        $this->send(array('result' => 'success', 'message' => '', 'data' => array('url' => $url)));
    }

    /**
     * Adjust instance memory size by ajax.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function ajaxAdjustMemory($instanceID)
    {
        $postData = fixer::input('post')->get();

        /* Check free memory size is enough or not. */
        $clusterResource = $this->cne->cneMetrics();
        $freeMemory      = intval($clusterResource->metrics->memory->allocatable * 0.9); // Remain 10% memory for system.
        if($postData->memory_kb * 1024 > $freeMemory) $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->notEnoughResource));

        /* Request CNE to adjust memory size. */
        $instance = $this->instance->getByID($instanceID);
        if(!$this->instance->updateMemorySize($instance, $postData->memory_kb * 1024))
        {
            $this->send(array('result' => 'fail', 'message' => dao::getError()));
        }

        $this->send(array('result' => 'success', 'message' => ''));
    }

    /**
     * Switch LDAP between enable and disable by ajax.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function ajaxSwitchLDAP($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        $postData = fixer::input('post')->get();

        if($this->instance->switchLDAP($instance, $postData->enableLDAP == 'true'))
        {
            $this->send(array('result' => 'success', 'message' => ''));
        }

        $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->switchLDAPFailed));
    }

    /**
     * Switch SMTP between enable and disable by ajax.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function ajaxSwitchSMTP($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        $postData = fixer::input('post')->get();

        if($this->instance->switchSMTP($instance, $postData->enableSMTP == 'true'))
        {
            $this->send(array('result' => 'success', 'message' => ''));
        }

        $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->switchSMTPFailed));
    }

    /**
     * Update custom settings by ajax. For example: env variables.
     *
     * @param  int    $instanceID
     * @access public
     * @return void
     */
    public function ajaxUpdateCustom($instanceID)
    {
        $instance = $this->instance->getByID($instanceID);
        if(!$instance) $this->send(array('result' => 'fail', 'message' => $this->lang->instance->instanceNotExists));

        $postData = fixer::input('post')->get();

        $settings = new stdclass;
        $settings->force_restart = true;
        $settings->settings_map  = new stdclass;
        $settings->settings_map->custom = $postData;

        if($this->cne->updateConfig($instance, $settings))
        {
            $this->action->create('instance', $instanceID, 'updatecustom', '', json_encode($settings));
            $this->send(array('result' => 'success', 'message' => ''));
        }

        $this->send(array('result' => 'fail', 'message' => $this->lang->instance->errors->setEnvFailed));
    }

    /**
     * Delete expired demo instance by cron.
     *
     * @access public
     * @return void
     */
    public function deleteExpiredDemoInstance()
    {
        if(empty($this->config->demoAccounts)) return $this->send(array('result' => 'fail', 'message' => 'This api is only for demo enviroment.'));

        $this->instance->deleteExpiredDemoInstance();

        $this->send(array('result' => 'success', 'message' => ''));
    }

    /**
     *  Get instance info for q tool in console.
     *
     * @param  int    $id
     * @access public
     * @return mixed
     */
    public function apiDetail($id)
    {
        if(!$this->checkCneToken())
        {
            header("HTTP/1.1 401");
            return print(json_encode(array('code' => 401, 'message' => 'Invalid token.')));
        }

        if(empty($id)) return print(json_encode(array('code' => 401, 'message' => 'Invalid id.')));

        $instance = $this->instance->getByID($id);
        if(empty($instance)) return print(json_encode(array('code' => 404, 'message' => 'Not found.', 'data' => array())));

        $instance->space = $instance->spaceData && isset($instance->spaceData->k8space) ? $instance->spaceData->k8space : '';
        unset($instance->desc);
        unset($instance->spaceData);

        return print(json_encode(array('code' => 200, 'message' => 'success', 'data' => $instance)));
    }

    /**
     * Get instances list by account through api for q tool.
     *
     * @access public
     * @return void
     */
    public function apiBrowse()
    {
        if(!$this->checkCneToken())
        {
            header("HTTP/1.1 401");
            return print(json_encode(array('code' => 401, 'message' => 'Invalid token.')));
        }

        $requestBody = json_decode(file_get_contents("php://input"));
        $account = zget($requestBody, 'account', '');
        if(empty($account)) return print(json_encode(array('code' => 700, 'message' => 'Account is required.', 'data' => new stdclass)));

        $recPerPage = zget($requestBody, 'perPage', 20);
        $pageID     = zget($requestBody, 'page', 1);

        $this->app->loadClass('pager', true);
        $pager = pager::init(0, $recPerPage, $pageID);

        $instanceList = $this->instance->getByAccount($account, $pager);

        $result = new stdclass;
        $result->list      = $instanceList;
        $result->page      = $pageID;
        $result->perPage   = $recPerPage;
        $result->pageTotal = $pager->pageTotal;
        $result->total     = $pager->recTotal;

        return print(json_encode(array('code' => 200, 'message' => 'success', 'data' => $result)));
    }

    /**
     * Install app by api for q tool.
     *
     * @access public
     * @return void
     */
    public function apiInstall()
    {
        if(!$this->checkCneToken())
        {
            header("HTTP/1.1 401");
            return print(json_encode(array('code' => 401, 'message' => 'Invalid token.')));
        }

        $requestBody = json_decode(file_get_contents("php://input"));
        $chart = zget($requestBody , 'chart', '');
        if(empty($chart)) return print(json_encode(array('code' => 701, 'message' => 'Param chart is required.')));

        $user = null;
        $account = zget($requestBody, 'account', '');
        if($account) $user = $this->loadModel('user')->getById($account);
        if(empty($user)) $user = $this->loadModel('company')->getAdmin();
        if(empty($user)) return print(json_encode(array('code' => 703, 'message' => 'No user.')));

        $this->app->user = $user;

        $name    = zget($requestBody , 'name', '');
        $channel = zget($requestBody , 'channel', 'stable');
        $k8name  = zget($requestBody , 'k8name', '');
        if($k8name && $this->instance->k8nameExists($k8name))  return print(json_encode(array('code' => 706, 'message' => $k8name . ' has been used, please change it and try again.')));

        $thirdDomain = zget($requestBody , 'domain', '');
        if($this->instance->domainExists($thirdDomain))  return print(json_encode(array('code' => 705, 'message' => $thirdDomain . ' has been used, please change it and try again.')));

        $cloudApp = $this->store->getAppInfoByChart($chart, $channel, false);
        if(empty($cloudApp)) return print(json_encode(array('code' => 702, 'message' => 'App not found.')));

        $result = $this->instance->apiInstall($cloudApp, $thirdDomain, $name, $k8name, $channel);

        if($result) return print(json_encode(array('code' => 200, 'message' => 'success', 'data' => new stdclass)));

        return print(json_encode(array('code' => 704, 'message' => 'Fail to install app.', 'data' => new stdclass)));
    }

    /**
     * Check CNE token.
     *
     * @access private
     * @return bool
     */
    private function checkCneToken()
    {
        $token = zget($_SERVER, 'HTTP_TOKEN');
        return $token == $this->config->CNE->api->token;
    }
}
