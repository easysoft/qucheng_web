$(function()
{
    $('.btn-uninstall').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmUninstall, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.uninstalling + '</div>',
            });

            let id  = $(event.target).closest('button').attr('instance-id');
            let url = createLink('instance', 'ajaxUninstall', 'id=' + id, 'json');
            $.post(url).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    window.parent.$.apps.open(createLink('space', 'browse'), 'space');
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    $('.btn-start').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmStart, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.starting + '</div>',
            });

            let id  = $(event.target).closest('button').attr('instance-id');
            let url = createLink('instance', 'ajaxStart', 'id=' + id, 'json');
            $.post(url).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    window.location.reload();
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    $('.btn-stop').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmStop, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.stopping + '</div>',
            });

            let id  = $(event.target).closest('button').attr('instance-id');
            let url = createLink('instance', 'ajaxStop', 'id=' + id, 'json');
            $.post(url).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    window.location.reload();
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    $('.btn-backup').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmBackup, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.backuping + '</div>',
            });

            let id  = $(event.target).closest('button').attr('instance-id');
            let url = createLink('instance', 'ajaxBackup', 'id=' + id, 'json');
            $.post(url).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.success,
                        message: res.message,
                        callback: function(){window.location.reload();}
                    });
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    $('.btn-restore').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmRestore, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.restoring + '</div>',
            });

            let instanceID = $(event.target).closest('button').attr('instance-id');
            let backupName = $(event.target).closest('button').attr('backup-name');
            let url = createLink('instance', 'ajaxRestore', '', 'json');
            $.post(url, { instanceID, backupName }).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.success,
                        message: res.message,
                        callback: function(){window.location.reload();}
                    });
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    $('.btn-delete-backup').on('click', function(event)
    {
        bootbox.confirm(instanceNotices.confirmDelete, function(result)
        {
            if(!result) return;

            var loadingDialog = bootbox.dialog(
            {
                message: '<div class="text-center"><i class="icon icon-spinner-indicator icon-spin"></i>&nbsp;&nbsp;' + instanceNotices.deleting + '</div>',
            });

            let id  = $(event.target).closest('button').attr('backup-id');
            let url = createLink('instance', 'ajaxDeleteBackup', 'id=' + id, 'json');
            $.post(url).done(function(response)
            {
                loadingDialog.modal('hide');

                let res = JSON.parse(response);
                if(res.result == 'success')
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.success,
                        message: res.message,
                        callback: function(){window.location.reload();}
                    });
                }
                else
                {
                    bootbox.alert(
                    {
                        title:   instanceNotices.fail,
                        message: res.message,
                    });
                }
            });
        });
    });

    var enableTimer = true;
    window.parent.$(window.parent.document).on('showapp', function(event, app)
    {
        enableTimer = app.code == 'space';
    });

    setInterval(function()
    {
        if(!enableTimer) return;

        var statusURL = createLink('instance', 'ajaxStatus');
        $.post(statusURL, {idList: instanceIdList}).done(function(response)
        {
            let res = JSON.parse(response);
            if(res.result == 'success' && res.data instanceof Array)
            {
                res.data.forEach(function(instance)
                {
                    if($(".instance-status[instance-id=" + instance.id + "]").data('status') != instance.status) window.location.reload();
                });
            }
            if(res.locate) window.parent.location.href = res.locate;
        });
    }, 1000 * 5);

    $('[data-toggle="tooltip"]').tooltip();
})
