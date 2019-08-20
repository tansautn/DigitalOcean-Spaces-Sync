/**
 * --*--*--*--*--*--*--*--*--*--*--*--*--*--*--*--*-- *
 * @PROJECT    : Digital Ocean Spaces Sync Plugin
 * @AUTHOR     : Zuko
 * @COPYRIGHT  : © 2019 Magebay - Magento Ext Provider
 * @LINK       : https://www.magebay.com/
 * @FILE       : dos-frontend.js
 * @CREATED    : 14:28 , 19/Aug/2019
 * @VERSION    : 2.1.1
 * --*--*--*--*--*--*--*--*--*--*--*--*--*--*--*--*-- *
**/
function dosOnImgError(event){
    var target = jQuery(event.target);
    if(!target.data('isFallbackSet')){
        target.attr('src',dosReplaceAll(window.dosData.uploadUrl,window.dosData.fallbackUrl,target.attr('src')));
        target.attr('srcset',dosReplaceAll(window.dosData.uploadUrl,window.dosData.fallbackUrl,target.attr('srcset')));
        target.data('isFallbackSet',true);
        console.log(target.attr('src'));
    }
}
function dosReplaceAll(search, replacement,subject) {
    return subject.split(search).join(replacement);
}