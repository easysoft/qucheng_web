<?php include $this->app->getModuleRoot() . '/common/view/header.html.php';?>
<style>
.body-modal #mainContent {padding-top: 10px;}
</style>
<div id='mainContent' class='main-row'>
  <div class='main-col main-content'>
    <h3><?php echo $lang->instance->installConfirm;?></h3>
    <table class='table table-form instance-status'>
      <tbody>
        <?php $_GET['onlybody'] = false;?>
        <?php foreach($instances as $instance):?>
        <tr>
          <td>
            <?php if($instance->solution == $devops->id):?>
            <?php echo html::a('javascript:void(0)', $instance->name . "（{$instance->appVersion}）", '', 'onclick="window.parent.goDevops()" class="text-primary" data-app="system" target="_top"');?>
            <?php else:?>
            <?php echo html::a($this->createLink('instance', 'view', 'id=' . $instance->id), $instance->name . "（{$instance->appVersion}）", '', 'class="text-primary" target="_parent"');?>
            <?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <div class='text-center form-actions'>
      <?php echo html::commonButton($lang->cancel, "onclick='$(\".modal-header .close\", window.parent.document).click()'", 'btn btn-primary btn-wide')?>
      <?php echo html::a($this->createLink('instance', 'install', "appID={$appID}&checkExist=0", '', true), $lang->instance->install, '', 'class="btn btn-primary btn-wide"');?>
    </div>
  </div>
</div>
<?php include $this->app->getModuleRoot() . '/common/view/footer.html.php';?>
