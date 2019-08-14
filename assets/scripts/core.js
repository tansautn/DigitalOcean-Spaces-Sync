
const dos_loader = jQuery('.dos__loader')
const dos_message = jQuery('.dos__message')
const dos_test_connection = jQuery('.dos__test__connection')
const dosSyncBtn = jQuery('#dos-sync-btn');
/**
 * Remove element from array , using Array.splice()
 * @param element
 * @return {Array}
 */
Array.prototype.remove = function (element)
{
    var idx = this.indexOf(element);
    if (idx !== -1)
    {
        this.splice(idx,1);
    }
    return this;
};
jQuery( function () {
    let intervalHandle;
    let dosSyncData = window.dosSyncData || {
        perPage : 25,
        curPage : 1,
        idList : [],
        totalImg : 0,
        syncedImg : 0,
        imgInfo : [],
        processing : [],
        done : [],
        apiUrl : '',
        editUrl : '',
    };
    function registerUnload() {
        if(dosSyncData.isRuning){
            window.onbeforeunload = function() {
                return '';
            }
        }else{
            window.onbeforeunload = undefined
        }
    }
    function appendLog(logMsg) {
        let logelem = jQuery('#dos-sync-log');
        let li = jQuery('<li></li>').html(logMsg);
        logelem.append(li);
    }
    function updateProgressBar() {
        let progress = jQuery('.ui-progressbar-value');
        let percent = Math.floor(dosSyncData.syncedImg / dosSyncData.totalImg * 100);
        jQuery('#dos-synced-count').text(dosSyncData.syncedImg + ' / ' + dosSyncData.totalImg);
        progress.attr('style','width:'+percent+'%');
    }
    function process() {
        updateProgressBar();
        if(dosSyncData.isPaused || dosSyncData.childRunning){
            return;
        }
        if(dosSyncData.syncedImg < dosSyncData.totalImg){
            if(dosSyncData.processing.length < 1){
                jQuery.ajax({
                                type: 'GET',
                                url: dosSyncData.apiUrl + dosSyncData.curPage,
                                dataType: 'json',
                                beforeSend : () => {
                                    dosSyncData.childRunning = true;
                                },
                            }).done( function ( res ) {
                    dosSyncData.childRunning = false;
                    console.log('res get media',res);
                    for(let i = 0; i < res.length; i++) {
                        let item = res[i];
                        dosSyncData.processing.push(item.id);
                    }
                    dosSyncData.curPage++;
                })
            }else{
                let curElem = dosSyncData.processing[0];
                if(dosSyncData.done.indexOf(curElem) !== -1){
                    appendLog('Skipped Attachment ID <a href="'+dosSyncData.editUrl+curElem+'">'+curElem+'</a> . Id exists in json');
                    dosSyncData.processing.remove(curElem);
                    dosSyncData.syncedImg++;
                }else{
                    sync(curElem);
                }
            }
        }else{
            window.clearInterval(intervalHandle);
            dosSyncData.isRuning = false;
            registerUnload();
            alert('Done');
        }
    }
    function sync(attId){
        const data = {
            id : attId,
            action: 'dos_sync_file',
            skipExists : jQuery('#dos-regenopt-onlymissing').prop('checked'),
        };
        jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: data,
                        dataType: 'json',
                        beforeSend : () => {
                            dosSyncData.childRunning = true;
                        },
        }).done( function ( res ) {
            dosSyncData.childRunning = false;
            if(res.ok === true){
                appendLog('Synced Attachment ID <a href="'+dosSyncData.editUrl+attId+'">'+attId+'</a>. '+res.message+' <code>'+res.meta.file+'</code>');
            }else{
                appendLog('Warning : Attachment ID <a href="'+dosSyncData.editUrl+attId+'">'+attId+'</a>. <code>'+res.message+'</code>');
            }
            dosSyncData.syncedImg++;
            dosSyncData.processing.remove(attId);
            dosSyncData.done.push(attId);
            console.log(res);
        })
    }
    dosSyncBtn.on('click',function() {
        jQuery('#dos-config').hide();
        jQuery('#dos-run').show();
        dosSyncData.isRuning = true;
        registerUnload();
        intervalHandle = window.setInterval(process,500);
//        sync(464);

    });
    jQuery('#dos-pause-btn').on('click',function() {
        dosSyncData.isPaused = !dosSyncData.isPaused;
        jQuery('#dos-pause-btn').text(dosSyncData.isPaused ? 'Resume' : 'Pause');
    });
  // check connection button
  dos_test_connection.on( 'click', function () {

    console.log( 'Testing connection to DigitalOcean Spaces Container' )

    const data = {
      dos_key: jQuery('input[name=dos_key]').val(),
      dos_secret: jQuery('input[name=dos_secret]').val(),
      dos_endpoint: jQuery('input[name=dos_endpoint]').val(),
      dos_container: jQuery('input[name=dos_container]').val(),
      action: 'dos_test_connection'
    }

    dos_loader.hide()

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      dataType: 'html'
    }).done( function ( res ) {
      dos_message.show()
      dos_message.html('<br/>' + res)
      dos_loader.hide()
      jQuery('html,body').animate({ scrollTop: 0 }, 1000)
    })

  })

})