<?php
/**
 * The html template file of login method of user module of QuCheng.
 *
 * @copyright   Copyright 2021-2022 北京渠成软件有限公司(BeiJing QuCheng Software Co,LTD, www.qucheng.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Jianhua Wang <wangjianhua@easycorp.ltd>
 * @package     user
 * @version     $Id$
 * @link        https://www.qucheng.com
 */
include '../../common/view/header.lite.html.php';
if(empty($config->notMd5Pwd))js::import($jsRoot . 'md5.js');
?>
<style>
#logo-box img{ border-radius: 10px;}
</style>
<main id="main" class="fade no-padding">
  <div class="container" id="login">
    <div id="loginPanel">
      <header>
        <h2><?php printf($lang->welcome, $app->company->name);?></h2>
        <div class="hidden actions dropdown dropdown-hover" id='langs'>
          <button type='button' class='btn' title='Change Language/更换语言/更換語言'><?php echo $config->langs[$this->app->getClientLang()]; ?> <span class="caret"></span></button>
          <ul class="dropdown-menu pull-right">
            <?php foreach($config->langs as $key => $value):?>
            <li><a class="switch-lang" data-value="<?php echo $key; ?>"><?php echo $value; ?></a></li>
            <?php endforeach;?>
          </ul>
        </div>
      </header>
      <div class="table-row">
        <div class="col-4 text-center" id='logo-box'>
          <img src="<?php echo $config->webRoot . 'theme/default/images/main/' . $this->lang->logoImg;?>" />
        </div>
        <div class="col-8">
          <form method='post' target='hiddenwin'>
            <table class='table table-form'>
              <tbody>
                <tr>
                  <th><?php echo $lang->user->account;?></th>
                  <td><input class='form-control' type='text' name='account' id='account' value="<?php echo commonModel::isDemoAccount($demoAccount) ? $demoAccount : '';?>" autocomplete='off' autofocus /></td>
                </tr>
                <tr>
                  <th><?php echo $lang->user->password;?></th>
                  <td><input class='form-control' type='password' name='password' value="<?php echo commonModel::isDemoAccount($demoAccount) ? $demoPass : '';?>" autocomplete='off' /></td>
                </tr>
                <?php if(!empty($this->config->safe->loginCaptcha)):?>
                <tr>
                  <th><?php echo $lang->user->captcha;?></th>
                  <td class='captchaBox'>
                    <div class='input-group'>
                      <?php echo html::input('captcha', '', "class='form-control'");?>
                      <span class='input-group-addon'><img src="<?php echo $this->createLink('misc', 'captcha', "sessionVar=captcha");?>" /></span>
                    </div>
                  </td>
                </tr>
                <?php endif;?>
                <tr>
                  <th></th>
                  <td id="keeplogin"><?php echo html::checkBox('keepLogin', $lang->user->keepLogin, $keepLogin);?></td>
                </tr>
                <tr>
                  <td></td>
                  <td class="form-actions">
                  <?php
                  echo html::submitButton($lang->login, '', 'btn btn-primary');
                  if($app->company->guest) echo html::linkButton($lang->user->asGuest, $this->createLink($config->default->module));
                  echo html::hidden('referer', $referer);
                  //echo html::a(inlink('reset'), $lang->user->resetPassword);
                  ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </form>
        </div>
      </div>
      <?php if(!empty($this->config->global->showDemoUsers)):?>
      <?php
      $demoPassword = '123456';
      $md5Password  = md5('123456');
      $demoUsers    = 'productManager,projectManager,dev1,dev2,dev3,tester1,tester2,tester3,testManager';
      if($this->app->getClientLang() == 'en') $demoUsers = 'thePO,pm1,pm2,pg1,pg2,pg3,thePM,qa1,theQS';
      $demoUsers = $this->dao->select('account,password,realname')->from(TABLE_USER)->where('account')->in($demoUsers)->andWhere('deleted')->eq(0)->andWhere('password')->eq($md5Password)->fetchAll('account');
      ?>
      <footer>
        <span><?php echo $lang->user->loginWithDemoUser;?></span>
        <?php
        $link  = inlink('login');
        $link .= strpos($link, '?') !== false ? '&' : '?';
        foreach($demoUsers as $demoAccount => $demoUser)
        {
            if($demoUser->password != $md5Password) continue;
            echo html::a($link . "account={$demoAccount}&password=" . md5($md5Password . $this->session->rand), $demoUser->realname);
        }
        ?>
      </footer>
      <?php endif;?>
    </div>
    <div id="info" class="table-row">
      <div class="table-col text-middle text-center">
        <div id="poweredby">
        </div>
      </div>
    </div>
  </div>
</main>
<?php include '../../common/view/footer.lite.html.php';?>
