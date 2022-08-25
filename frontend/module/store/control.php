<?php
/**
 * The control file of store module of QuCheng.
 *
 * @copyright Copyright 2021-2022 北京渠成软件有限公司(BeiJing QuCheng Software Co,LTD, www.qucheng.com)
 * @license   ZPL (http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author    Jianhua Wang <wangjianhua@easycorp.ltd>
 * @package   store
 * @version   $Id$
 * @link      https://www.qucheng.com
 */
class store extends control
{
    /**
     * Contruct function, load cne model.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->loadModel('cne');
        $this->app->loadLang('instance');
    }

    /**
     * Index page.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->locate($this->createLink('store', 'browse'));
    }

    /**
     * Browse departments and users of a store.
     *
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @param  string $channel
     * @access public
     * @return void
     */
    public function browse($recTotal = 0, $recPerPage = 0, $pageID = 1, $channel = '')
    {
        global $config;
        if(empty($recPerPage)) $recPerPage = $this->cookie->pagerStoreBrowse ? $this->cookie->pagerStoreBrowse : 24;
        if(in_array( $channel, array('stable', 'test')))
        {
            $config->CNE->api->channel   = $channel;
            $config->cloud->api->channel = $channel;
            $this->session->set('cloudChannel', $channel);
        }

        $keyword        = '';
        $postCategories = array();
        if(!empty($_POST))
        {
            $pageID = 1;
            $conditions = fixer::input('post')
                ->setDefault('keyword', '')
                ->setDefault('categories', array())
                ->get();
            $keyword        = $conditions->keyword;
            $postCategories = $conditions->categories;
        }

        $pagedApps = $this->cne->searchApps($keyword, $postCategories, $pageID, $recPerPage);
        $this->app->loadClass('pager', true);
        $pager = pager::init($pagedApps->total, $recPerPage, $pageID);

        $pagedCategories = $this->cne->getCategories();
        $categories      = array_combine(array_column($pagedCategories->categories, 'id'), array_column($pagedCategories->categories, 'alias'));

        $this->lang->switcherMenu = $this->store->getBrowseSwitcher();

        $this->view->title          = $this->lang->store->common;
        $this->view->position[]     = $this->lang->store->common;
        $this->view->cloudApps      = $pagedApps->apps;
        $this->view->categories     = $categories;
        $this->view->postCategories = $postCategories;
        $this->view->keyword        = $keyword;
        $this->view->pager          = $pager;

        $this->display();
    }

    /**
     * Show app detail.
     *
     * @param  int $id
     * @access public
     * @return viod
     */
    public function appView($id)
    {
        $appInfo = $this->cne->getAppInfo($id, true);
        if(empty($appInfo)) return print(js::locate('back', 'parent'));

        $this->lang->switcherMenu = $this->store->getAppViewSwitcher($appInfo);

        $this->view->title      = $appInfo->alias;
        $this->view->position[] = $appInfo->alias;
        $this->view->cloudApp   = $appInfo;
        $this->view->components = null; // Hide custom installation in version 1.0. If want, opened by: $this->cne->getAppSettings($id);

        $this->display();
    }
}
