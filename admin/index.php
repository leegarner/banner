<?php
/**
 *  Banner admin entry point.
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 *  @package    banner
 *  @version    0.1.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *  GNU Public License v2 or later
 *  @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';

$content = '';

// Must have privileges to access this admin area
if (!SEC_hasRights('banner.edit')) {
    COM_404();
}

USES_lib_admin();
USES_banner_functions();

$action = '';
$actionval = '';
$expected = array(
    'save', 'delete', 'delitem', 'validate',
    'edit', 'moderate', 'cancel',
    'banners', 'categories', 'campaigns',
    'mode', 'view',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
    	$action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}
// Allow for old-style "mode=xxxx" urls
if ($action == 'mode') {
    $action = $actionval;
}
if ($action == '') $action = 'banners';    // default view
$item = isset($_REQUEST['item']) ? $_REQUEST['item'] : 'banner';
$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';

if ($action == 'delitem') {
    switch ($item) {
    case 'banner':
        $action = 'delMultiBanner';
        break;
    default:
        $action = '';
        break;
    }
}

if (isset($_REQUEST['bid'])) {
    $bid = COM_sanitizeID($_REQUEST['bid'], false);
} else {
    $bid = '';
}

switch ($action) {
case 'toggleEnabled':
    USES_banner_class_banner();
    $B = new Banner($_REQUEST['bid']);
    $B->toggleEabled($_REQUEST['newval']);
    $view = 'banners';
    break;

case 'delete':
    switch ($item) {
    case 'banner':
        USES_banner_class_banner();
        if ($type == 'submission') {
            $B = new Banner($_REQUEST['bid'], 'bannersubmission');
            $view = 'moderation';
        } else {
            $B = new Banner($_REQUEST['bid']);
            $view = 'banners';
        }
        if ($B->hasAccess(3))
            $B->Delete();
        break;
    case 'category':
        USES_banner_class_category();
        $C = new Category($_REQUEST['cid']);
        $content .= $C->Delete();
        $view = 'categories';
        break;
    case 'campaign':
        USES_banner_class_campaign();
        $C = new Campaign($_REQUEST['camp_id']);
        $C->Delete();
        $view = 'campaigns';
        break;
    }
    break;

case 'delMultiBanner':
    USES_banner_class_banner();
    foreach ($_POST['delitem'] as $item) {
        $B = new Banner($item);
        if ($B->hasAccess(3))
            $B->Delete();
    }
    $view = 'banners';
    break;

case 'toggleEnabledCategory':
    USES_banner_class_category();
    Category::toggleEnabled($_REQUEST['newval'], $_REQUEST['cid']);
    $view = 'categories';
    break;

case 'toggleEnabledCampaign':
    USES_banner_class_campaign();
    Campaign::toggleEnabled($_REQUEST['newval'], $_REQUEST['camp_id']);
    $view = 'campaigns';
    break;

case 'save':
    switch ($item) {
    case 'category':
        USES_banner_class_category();
        // 'oldcid' will be empty for new entries, non-empty for updates
        $C = new Category($_POST['oldcid']);
        $status = $C->Save($_POST);
        if ($status != '') {
            $content .= BANNER_errorMessage($status);
            if (isset($_POST['oldcid']) && !empty($_POST['oldcid'])) {
                //$view = 'editcategory';
                $view = 'edit';
            } else {
                $view = 'newcategory';
            }
        } else {
            $view = 'categories';
        }
        break;

    case 'campaign':
        USES_banner_class_campaign();
        $C = new Campaign($_POST['old_camp_id']);
        $status = $C->Save($_POST);
            if ($status != '') {
            $content .= BANNER_errorMessage($status);
            if (isset($_POST['old_camp_id']) && !empty($_POST['old_camp_id'])) {
                $view = 'edit';
                $mode = 'edit';
            } else {
                $view = 'newcampaign';
            }
        } else {
            $view = 'campaigns';
        }
        break;

    case 'banner':
        $status = '';
        if (SEC_checkToken()) {
            USES_banner_class_banner();
            $B = new Banner();
            $B->setAdmin(true);

            if ($type == 'submission') {
                $B->setTable('bannersubmission');
            }
            if (isset($_POST['oldbid']) && !empty($_POST['oldbid'])) {
                $B->Read($_POST['oldbid']);
            }
            $B->setTable('banner');
            // Delete the submission, if any
            if ($type == 'submission') {
                $B->setVars($_POST);
                $status = $B->Insert(false);
                if ($status == '') {
                    // Only delete from submission table if status is ok
                    DB_delete($_TABLES['bannersubmission'], 'bid', $B->getID());
                }
            } else {
                $status = $B->Save($_POST);
            }
        }
        if ($status != '') {
            $content .= BANNER_errorMessage($status);
            $bid = '';      // Reset to force new banner form
            $view = 'edit';
            $mode = 'edit';
        } else {
            $view = 'banners';
        }
        break;

    }
    break;

case 'moderate':
    $view = $action;
    break;

default:
    $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
    break;
}

switch ($view) {
case 'campaigns':
    USES_banner_class_campaignlist();
    $L = new CampaignList(true);
    $content .= $L->ShowList();
    //$content .= BANNER_adminCampaigns();
    break;

case 'categories':
    USES_banner_class_category();
    $content .= BANNER_adminCategories();
    break;

// Redirect to the system moderation page
case 'moderation':
    echo COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
    break;

case 'editsubmission':
case 'moderate':
    USES_banner_class_banner();
    $B = new Banner($_GET['bid'], 'bannersubmission');
    $B->setAdmin(true);
    if ($B->getID() != '') {
        $content .= $B->Edit($mode);
    }
    break;

case 'edit':
    switch ($item) {
    case 'banner':
        USES_banner_class_banner();
        $B = new Banner($bid);
        if (!empty($_POST)) {
            $B->SetVars($_POST);
        }
        $B->setAdmin(true);
        $content .= $B->Edit($mode);
        break;
    case 'campaign':
        USES_banner_class_campaign();
        $C = new Campaign($_REQUEST['camp_id']);
        if (!empty($_POST)) {
            $C->SetVars($_POST);
        }
        if ($C->camp_id == '')
            $C->setUID($_REQUEST['uid']);
        $content .= $C->Edit();
        break;
    case 'category':
        USES_banner_class_category();
        $C = new Category($_REQUEST['cid']);
        $content .= $C->Edit();
        break;
    }
    break;

case 'newcategory':
    USES_banner_class_category();
    $C = new Category();
    if (!empty($_POST)) {
        $C->SetVars($_POST);
    }
    $content .= $C->Edit();
    break;

case 'editcategory':
    USES_banner_class_category();
    $C = new Category($_REQUEST['cid']);
    $content .= $C->Edit();
    break;

case 'newcampaign':
echo "here in newcampaign";die;
    USES_banner_class_campaign();
    $C = new Campaign();
    if (!empty($_POST)) {
        $C->SetVars($_POST);
    }
    if ($C->camp_id == '')
        $C->setUID($_REQUEST['uid']);
    $content .= $C->Edit();
    break;

case 'editcampaign':
    USES_banner_class_campaign();
    $C = new Campaign($_REQUEST['camp_id']);
    if (!empty($_POST)) {
        $C->SetVars($_POST);
    }
    if ($C->camp_id == '')
        $C->setUID($_REQUEST['uid']);
    $content .= $C->Edit();
    break;

case 'banners':
default:
    if (isset($_GET['msg'])) {
        $msg = COM_applyFilter($_GET['msg'], true);
        if ($msg > 0) {
            $content .= COM_showMessage($msg, 'banner');
        }
    }
    USES_banner_class_bannerlist();
    $L = new BannerList(true);
    if (isset($_REQUEST['category']))
        $L->setCatID($_REQUEST['category']);
    if (isset($_REQUEST['camp_id']))
        $L->setCampID($_REQUEST['camp_id']);
    $content .= $L->ShowList();
    break;

}   // switch ($view)

echo COM_siteHeader('menu', $LANG_BANNER['banners']);
echo BANR_adminMenu($view);
echo $content;
echo COM_siteFooter();
exit;

/**
*   Create the administrator menu
*
*   @param  string  $view   View being shown, so set the help text
*   @return string      Administrator menu
*/
function BANR_adminMenu($view='')
{
    global $_CONF, $LANG_ADMIN, $LANG_BANNER, $_CONF_BANR;

    if (isset($LANG_BANNER['admin_hdr_' . $view]) && 
        !empty($LANG_BANNER['admin_hdr_' . $view])) {
        $hdr_txt = $LANG_BANNER['admin_hdr_' . $view];
    } else {
        $hdr_txt = $LANG_BANNER['admin_hdr'];
    }

    if ($view == 'banners') {
        $menu_arr[] = array(
                    'url'  => BANR_ADMIN_URL . '/index.php?edit=x',
                    'text' => '<span class="banrNewAdminItem">' .
                            $LANG_BANNER['new_banner'], '</span>');
        $menu_arr[] = array(
                    'url' => BANR_ADMIN_URL . '/index.php?validate=enabled',
                    'text' => $LANG_BANNER['validate_banner']);
    } else {
        $menu_arr[] = array(
                    'url'  => BANR_ADMIN_URL . '/index.php',
                    'text' => $LANG_BANNER['banners']);
    }

    if ($view == 'categories') {
        $menu_arr[] = array(
                    'url'  => BANR_ADMIN_URL . '/index.php?edit=x&item=category',
                    'text' => '<span class="banrNewAdminItem">' .
                            $LANG_BANNER['new_cat']. '</span>');
    } else {
        $menu_arr[] = array('url'  => BANR_ADMIN_URL . '/index.php?categories=x',
                    'text' => $LANG_BANNER['categories']);
    }

    if ($view == 'campaigns') {
        $menu_arr[] = array(
                    'url'  => BANR_ADMIN_URL . '/index.php?edit=x&item=campaign',
                    'text' => '<span class="banrNewAdminItem">' .
                            $LANG_BANNER['new_camp'] . '</span>');
    } else {
        $menu_arr[] = array('url'  => BANR_ADMIN_URL . 
                            '/index.php?campaigns=x',
                    'text' => $LANG_BANNER['campaigns']);
    }

    $menu_arr[] = array('url'  => $_CONF['site_admin_url'],
                  'text' => $LANG_ADMIN['admin_home']);

    $T = new Template(BANR_PI_PATH . '/templates');
    $T->set_file('title', 'banner_admin_title.thtml');
    $T->set_var('title', 
        $LANG_BANNER['banner_mgmt'] . ' (Ver. ' . $_CONF_BANR['pi_version'] . ')');
    $retval = $T->parse('', 'title');
    $retval .= ADMIN_createMenu($menu_arr, $hdr_txt, 
            plugin_geticon_banner());

    return $retval;

}

?>
