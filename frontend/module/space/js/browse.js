$(function()
{
    $(".switchButton").on('click', function()
    {
        var spaceType = $(this).attr('data-type');
        $.cookie('spaceType', spaceType, {expires:config.cookieLife, path:config.webRoot});
    });
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
                } });
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

    /* Count down for demo instance. */
    setInterval(function()
    {
        let nowSeconds = Math.round((new Date).getTime() / 1000);
        $('.count-down').each(function(index, item)
        {
            let createdAt = $(item).data('created-at');
            let passSeconds = nowSeconds - createdAt;
            let leftSeconds = (demoAppLife ? demoAppLife : 30) * 60 - passSeconds;

            if(leftSeconds < 0)
            {
                window.parent.location.href = createLink('space', 'browse');
            }
            else
            {
                let minutes = Math.floor(leftSeconds / 60);
                let seconds = Math.round(leftSeconds % 60);
                $(item).find('.left-time').text(('0' + minutes).slice(-2) + ':' + ('0' + seconds).slice(-2));
            }

        })
    }, 1000);
})
