<?php

/* * **** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is http://code.mattzuba.com code.
 *
 * The Initial Developer of the Original Code is
 * Matt Zuba.
 * Portions created by the Initial Developer are Copyright (C) 2010-2011
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *
 * ***** END LICENSE BLOCK ***** */

// If SSI.php is in the same place as this file, and SMF isn't defined, this is being run standalone.
if (!defined('SMF') && file_exists(dirname(__FILE__) . '/SSI.php'))
    require_once(dirname(__FILE__) . '/SSI.php');
// Hmm... no SSI.php and no SMF?
elseif (!defined('SMF'))
    die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

pre_install_check();

// List settings here in the format: setting_key => default_value.  Escape any "s. (" => \")
$newSettings = array(
    'simplesef_space' => '_',
    'simplesef_suffix' => 'html',
    'simplesef_lowercase' => '1',
    'simplesef_strip_words' => 'a,about,above,across,after,along,around,at,before,behind,below,beneath,beside,between,but,by,down,during,except,for,from,in,inside,into,like,near,of,off,on,onto,out,outside,over,since,through,the,till,to,toward,under,until,up,upon,with,within,without',
    'simplesef_actions' => 'activate,admin,announce,attachapprove,buddy,calendar,clock,collapse,coppa,credits,deletemsg,display,dlattach,editpoll,editpoll2,emailuser,findmember,groups,help,helpadmin,im,jseditor,jsmodify,jsoption,lock,lockvoting,login,login2,logout,markasread,mergetopics,mlist,moderate,modifycat,modifykarma,movetopic,movetopic2,notify,notifyboard,openidreturn,pm,post,post2,printpage,profile,quotefast,quickmod,quickmod2,recent,register,register2,reminder,removepoll,removetopic2,reporttm,requestmembers,restoretopic,search,search2,sendtopic,smstats,suggest,spellcheck,splittopics,stats,sticky,theme,trackip,about:mozilla,about:unknown,unread,unreadreplies,verificationcode,viewprofile,vote,viewquery,viewsmfile,who,.xml,xmlhttp',
    'simplesef_useractions' => 'profile,pm',
);

$newSettings['simplesef_strip_chars'] = empty($smcFunc['db_query']) ? '&quot,&amp,&lt,&gt,`,~,!,@,#,$,%,^,&,*,(,),-,_,=,+,[,{,],},;,:,\\\',",",/,?,\\\,|' : '&quot,&amp,&lt,&gt,`,~,!,@,#,$,%,^,&,*,(,),-,_,=,+,[,{,],},;,:,\',",",/,?,\,|';

updateSettings($newSettings);

// Add hooks (for 2.0)
if (!empty($smcFunc['db_query'])) {
    $sef_functions = array(
        'integrate_pre_load' => 'SimpleSEF::convertQueryString',
        'integrate_buffer' => 'SimpleSEF::ob_simplesef',
        'integrate_redirect' => 'SimpleSEF::fixRedirectUrl',
        'integrate_outgoing_email' => 'SimpleSEF::fixEmailOutput',
        'integrate_exit' => 'SimpleSEF::fixXMLOutput',
        'integrate_pre_include' => $sourcedir . '/SimpleSEF.php',
        'integrate_load_theme' => 'SimpleSEF::loadTheme',
        'integrate_admin_areas' => 'SimpleSEF::adminAreas',
        'integrate_menu_buttons' => 'SimpleSEF::menuButtons',
        'integrate_actions' => 'SimpleSEF::actionArray',
    );

    foreach ($sef_functions as $hook => $function)
        add_integration_function($hook, $function, TRUE);
}

if (addHtaccess() === false)
    log_error('Could not add or edit .htaccess file upon install of SimpleSEF', 'debug');

if (SMF == 'SSI') {
    fatal_error('<b>This isn\'t really an error, just a message telling you that the settings have been entered into the database!</b><br />');
    @unlink(__FILE__);
}

function pre_install_check() {
    global $modSettings, $txt;

    if (version_compare(PHP_VERSION, '5.0.0', '<'))
        fatal_error('<b>PHP 5 or geater is required to install SimpleSEF.  Please remind your host that PHP4 is no longer maintained and ask that they upgrade you to PHP5.</b><br />');

    $char_set = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
    if ($char_set != 'ISO-8859-1' && $char_set != 'UTF-8' && !function_exists('iconv') && !function_exists('mb_convert_encoding') && !function_exists('unicode_decode'))
        fatal_error('<b>You are currently using the ' . $char_set . ' character set and your server does not have functions available to convert to UTF-8.  In order to use this mod, you will either need to convert your board to UTF-8 or ask your host to recompile PHP with with the Iconv or Multibyte String extensions.</b>');
}

function addHtaccess() {
    global $boarddir;

    $htaccess_addition = '
RewriteEngine On
# Uncomment the following line if it\'s not working right
# RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?q=$1 [L,QSA]';

    if (file_exists($boarddir . '/.htaccess') && is_writable($boarddir . '/.htaccess')) {
        $current_htaccess = file_get_contents($boarddir . '/.htaccess');

        // Only change something if the mod hasn't been addressed yet.
        if (strpos($current_htaccess, 'RewriteRule ^(.*)$ index.php') === false) {
            if (($ht_handle = @fopen(dirname(__FILE__) . '/.htaccess', 'ab'))) {
                fwrite($ht_handle, $htaccess_addition);
                fclose($ht_handle);
                return true;
            }
            else
                return false;
        }
        else
            return true;
    }
    elseif (file_exists($boarddir . '/.htaccess'))
        return strpos(file_get_contents($boarddir . '/.htaccess'), 'RewriteRule ^(.*)$ index.php') !== false;
    elseif (is_writable($boarddir)) {
        if (($ht_handle = fopen($boarddir . '/.htaccess', 'wb'))) {
            fwrite($ht_handle, trim($htaccess_addition));
            fclose($ht_handle);
            return true;
        }
        else
            return false;
    }
    else
        return false;
}