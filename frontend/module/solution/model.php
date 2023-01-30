<?php
/**
 * The model file of solution module of QuCheng.
 *
 * @copyright   Copyright 2009-2022 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Jianhua Wang<wangjianhua@easycorp.ltd>
 * @package     solution
 * @version     $Id$
 * @link        https://www.qucheng.com
 */
class solutionModel extends model
{

    /**
     * Get solution by id.
     *
     * @param  int         $id
     * @access public
     * @return object|null
     */
    public function getByID($id)
    {
        $solution  = $this->dao->select('*')->from(TABLE_SOLUTION)->where('id')->eq($id)->fetch();
        if(!$solution) return null;

        $instanceIDList = $this->dao->select('id')->from(TABLE_INSTANCE)->where('solution')->eq($id)->fetchAll('id');

        $solution->instances = array();
        if($instanceIDList) $solution->instances = $this->loadModel('instance')->getByIDList(array_keys($instanceIDList));

        return $solution;
    }

    /**
     * Search
     *
     * @param  string $keyword
     * @access public
     * @return array
     */
    public function search($keyword = '')
    {
        return $this->dao->select('*')->from(TABLE_SOLUTION)
            ->where('deleted')->eq(0)
            ->beginIF($keyword)->andWhere('name')->like($keyword)->fi()
            ->orderBy('createdAt desc')->fetchAll();
    }

    /**
     * Update solution name.
     *
     * @param  int    $solutionID
     * @access public
     * @return int
     */
    public function updateName($solutionID)
    {
        $newSolution = fixer::input('post')->trim('name')->get();

        return $this->dao->update(TABLE_SOLUTION)->data($newSolution)->autoCheck()->where('id')->eq($solutionID)->exec();
    }

    /**
     * Create by solution of cloud market.
     *
     * @param  object $cloudSolution
     * @access public
     * @return object
     */
    public function create($cloudSolution, $components)
    {
        $postedCharts = fixer::input('post')->get();

        /* Sort selected apps. */
        $orderedCategories = $components->order;
        $selectedApps = array();
        foreach($orderedCategories as $category)
        {
            $chart = zget($postedCharts, $category);

            $selectedApps[$category] = $this->pickAppFromSchema($components, $category, $chart, $cloudSolution);
        }

        /* Create solution. */
        $solution = new stdclass;
        $solution->name         = $cloudSolution->title;
        $solution->appID        = $cloudSolution->id;
        $solution->appName      = $cloudSolution->name;
        $solution->appVersion   = $cloudSolution->app_version;
        $solution->version      = $cloudSolution->version;
        $solution->chart        = $cloudSolution->chart;
        $solution->cover        = $cloudSolution->background_url;
        $solution->introduction = $cloudSolution->introduction;
        $solution->desc         = $cloudSolution->description;
        $solution->status       = 'waiting';
        $solution->source       = 'cloud';
        $solution->components   = json_encode($selectedApps);
        $solution->createdBy    = $this->app->user->account;
        $solution->createdAt    = date('Y-m-d H:i:s');

        $channel = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;

        $solution->channel = $channel;

        $this->dao->insert(TABLE_SOLUTION)->data($solution)->exec();
        if(dao::isError()) return null;

        return $this->getByID($this->dao->lastInsertID());
    }

    /**
     * Pick App from schema info by category and chart.
     *
     * @param  object $schema
     * @param  string $category
     * @param  string $chart
     * @param  object $cloudSolution
     * @access public
     * @return object|null
     */
    public function pickAppFromSchema($schema, $category, $chart, $cloudSolution)
    {
        $appGroup = zget($schema->categories, $category, array());

        foreach($appGroup->choices as $appInSchema)
        {
            if($appInSchema->name != $chart) continue;

            $appInfo = zget($cloudSolution->apps, $chart);

            $appInfo->version     = $appInSchema->version;
            $appInfo->app_version = $appInSchema->app_version;
            $appInfo->status      = 'waiting';

            return $appInfo;
        }

        return;
    }

    /**
     * Install solution.
     *
     * @param  int    $solutionID
     * @access public
     * @return bool
     */
    public function install($solutionID)
    {
        ignore_user_abort(true);
        set_time_limit(0);
        session_write_close();

        $solution = $this->getByID($solutionID);
        if(!$solution)
        {
            dao::$errors[] = $this->lang->solution->errors->notFound;
            return false;
        }
        $this->saveStatus($solutionID, 'installing');

        $this->loadModel('cne');
        $this->loadModel('instance');
        $this->loadModel('store');
        $allMappings    = array();
        $solutionSchema = $this->loadModel('store')->solutionConfigByID($solution->appID);
        $channel        = $this->app->session->cloudChannel ? $this->app->session->cloudChannel : $this->config->cloud->api->channel;
        $components     = json_decode($solution->components);
        foreach($components as $categorty => $componentApp)
        {
            $solutionStatus = $this->dao->select('status')->from(TABLE_SOLUTION)->where('id')->eq($solutionID)->fetch();
            if($solutionStatus->status !='installing')
            {
                /* If status is not installing, should abort installation.  Becaust installation was canceled or error happened. */
                dao::$errors[] = $this->lang->solution->errors->hasInstallationError;
                return false;
            }

            $instance = $this->instance->instanceOfSolution($solution, $componentApp->chart);
            /* If not install. */
            if(!$instance)
            {
                $cloudApp = $this->store->getAppInfo($componentApp->id, false, '', $componentApp->version, $channel);
                if(!$cloudApp)
                {
                    $this->saveStatus($solutionID, 'notFoundApp');
                    dao::$errors[] = sprintf($this->lang->solution->errors->notFoundAppByVersion, $componentApp->version, $componentApp->alias);
                    return false;
                }
                /* Must install the defineded version in solution schema. */
                $cloudApp->version     = $componentApp->version;
                $cloudApp->app_version = $componentApp->app_version;

                /* Check enough memory to install app, or not.*/
                if(!$this->instance->enoughMemory($cloudApp))
                {
                    $this->saveStatus($solutionID, 'notEnoughResource');
                    dao::$errors[] = $this->lang->solution->errors->notEnoughResource;
                    return false;
                }

                $settings = $this->mountSettings($solutionSchema, $componentApp->chart, $components, $allMappings);
                $instance = $this->installApp($cloudApp, $settings);
                if(!$instance)
                {
                    $this->saveStatus($solutionID, 'cneError');
                    dao::$errors[] = sprintf($this->lang->solution->errors->failToInstallApp, $cloudApp->name);
                    return false;
                }
                $this->dao->update(TABLE_INSTANCE)->set('solution')->eq($solutionID)->where('id')->eq($instance->id)->exec();

                $componentApp->status = 'installing';
                $this->dao->update(TABLE_SOLUTION)->set('components')->eq(json_encode($components))->where('id')->eq($solution->id)->exec();
            }

            /* Wait instanlled app started. */
            $instance = $this->waitInstanceStart($instance);
            if($instance)
            {
                $mappingKeys = zget($solutionSchema->mappings, $instance->chart, '');
                if($mappingKeys)
                {
                    /* Load settings mapping of installed app for next app. */
                    $tempMappings = $this->cne->getSettingsMapping($instance, $mappingKeys);
                    if($tempMappings) $allMappings[$categorty] = $tempMappings;
                }
                $componentApp->status = 'installed';
                $this->dao->update(TABLE_SOLUTION)->set('components')->eq(json_encode($components))->where('id')->eq($solution->id)->exec();
            }
            else
            {
                $this->saveStatus($solutionID, 'timeout');
                dao::$errors[] = $this->lang->solution->errors->timeout;
                return false;
            }
        }

        $this->saveStatus($solutionID, 'installed');
        return true;
    }

    /**
     * Save status.
     *
     * @param  int    $solutionID
     * @param  string $status
     * @access public
     * @return int
     */
    public function saveStatus($solutionID, $status)
    {
        return $this->dao->update(TABLE_SOLUTION)->set('status')->eq($status)->where('id')->eq($solutionID)->exec();
    }

    /**
     * Mount settings for installing app.
     *
     * @param  object  $solutionSchema
     * @param  string  $chart
     * @param  object  $components
     * @param  array   $mappings  example: ['git' => ['env.GIT_USERNAME' => 'admin', ...], ...]
     * @access private
     * @return array
     */
    private function mountSettings($solutionSchema, $chart, $components, $mappings)
    {
        $settings = array();

        $appSettings = zget($solutionSchema->settings, $chart, array());
        foreach($appSettings as $item)
        {
            switch($item->type)
            {
                case 'static':
                    $settings[] = array('key' => $item->key, 'value' => $item->value);
                    break;
                case 'choose':
                    $appInfo = zget($components, $item->target, '');
                    if($appInfo) $settings[] = array('key' => $item->key, 'value' => $appInfo->chart);
                    break;
                case 'mappings':
                    $mappingInfo = zget($mappings, $item->target, '');
                    if($mappingInfo) $settings[] = array('key' => $item->key, 'value' => zget($mappingInfo, $item->key, ''));
                    break;
            }
        }

        return $settings;
    }

    /**
     * installApp
     *
     * @param  int     $cloudApp
     * @param  int     $settings
     * @access private
     * @return mixed
     */
    private function installApp($cloudApp, $settings)
    {
        /* Fake parameters for installation. */

        $customData = new stdclass;
        $customData->customName   = $cloudApp->alias;
        $customData->dbType       = null;
        $customData->customDomain = $this->loadModel('instance')->randThirdDomain();

        $dbInfo = new stdclass;
        $dbList = $this->loadModel('cne')->sharedDBList();
        if(count($dbList) > 0)
        {
            $dbInfo = reset($dbList);

            $customData->dbType    = 'sharedDB';
            $customData->dbService = $dbInfo->name; // Use first shared database.
        }

        return $this->instance->install($cloudApp, $dbInfo, $customData, null, $settings);
    }

    /**
     * Wait instance started.
     *
     * @param  object      $instance
     * @access private
     * @return object|bool
     */
    private function waitInstanceStart($instance)
    {
        /* Query status of the installed instance. */
        $times = 0;
        for($times = 0; $times < 50; $times++)
        {
            sleep(6);
            $instance = $this->instance->freshStatus($instance);
            $this->app->saveLog(date('Y-m-d H:i:s').' installing ' . $instance->name . ':' .$instance->status); // Code for debug.
            if($instance->status == 'running') return $instance;
        }

        return false;
    }

    /**
     * Uninstall solution and all included instances .
     *
     * @param  int    $solutionID
     * @access public
     * @return void
     */
    public function uninstall($solutionID)
    {
        $this->loadModel('instance');
        /* Firstly change the status to 'unintalling' for abort installing process. */
        $this->dao->update(TABLE_SOLUTION)->set('status')->eq('uninstalling')->where('id')->eq($solutionID)->exec();

        $solution = $this->getByID($solutionID);
        if(empty($solution))
        {
            dao::$errors[] = $this->lang->solution->notFound;
            return;
        }

        foreach($solution->instances as $instance)
        {
            $success = $this->instance->uninstall($instance);
            if(!$success)
            {
                dao::$errors[] = sprintf($this->lang->solution->errors->failToUninstallApp, $instance->name);
                return;
            }
        }

        $this->dao->update(TABLE_SOLUTION)->set('status')->eq('uninstalled')->set('deleted')->eq(1)->where('id')->eq($solutionID)->exec();
    }

    /**
     * Convert schema choices to select options.
     *
     * @param  object $schemaChoices
     * @param  object $cloudSolution
     * @access public
     * @return array
     */
    public function createSelectOptions($schemaChoices, $cloudSolution)
    {
        $options = array();
        foreach($schemaChoices as $cloudApp)
        {
            $appInfo = zget($cloudSolution->apps, $cloudApp->name);
            $options[$cloudApp->name] = zget($appInfo, 'alias', $cloudApp->name);
        }

        return $options;
    }

    /**
     * Print CPU usage.
     *
     * @param  object $solution
     * @param  object $metrics
     * @param  string $type    'bar' is progress bar, 'pie' is progress pie.
     * @static
     * @access public
     * @return viod
     */
    public function printCpuUsage($solution, $type = 'bar')
    {
        /* Calculate total usage of all instances. */
        $totalRate  = 0;
        $totalUsage = 0;
        $totalLimit = 0;
        $instancesMetric = $this->loadModel('cne')->instancesMetrics($solution->instances);
        foreach($instancesMetric as $metric)
        {
            $totalRate  += $metric->cpu->rate;
            $totalUsage += $metric->cpu->usage;
            $totalLimit += $metric->cpu->limit;
        }

        $totalRate = round($totalRate / count($solution->instances), 2);

        $tip = "{$totalRate}% = {$totalUsage} / {$totalLimit}";

        if(strtolower($type) == 'pie') return commonModel::printProgressPie($totalRate, '', $tip);

        return commonModel::printProgressBar($totalRate, '', $tip, 'percent');
    }

    /**
     * Print memory usage.
     *
     * @param  object $solution
     * @param  object $metrics
     * @param  string $type    'bar' is progress bar, 'pie' is progress pie.
     * @static
     * @access public
     * @return viod
     */
    public function printMemUsage($solution, $type = 'bar')
    {
        /* Calculate total usage of all instances. */
        $totalRate  = 0;
        $totalUsage = 0;
        $totalLimit = 0;
        $instancesMetric = $this->loadModel('cne')->instancesMetrics($solution->instances);
        foreach($instancesMetric as $metric)
        {
            $totalRate  += $metric->memory->rate;
            $totalUsage += $metric->memory->usage;
            $totalLimit += $metric->memory->limit;
        }

        $totalRate = round($totalRate / count($solution->instances), 2);

        $tip = "{$totalRate}% = {$totalUsage} / {$totalLimit}";

        if(strtolower($type) == 'pie') return commonModel::printProgressPie($totalRate, '', $tip);

        return commonModel::printProgressBar($totalRate, '', $tip, 'percent');
    }
}
