<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: forum_viewthread.php 16602 2010-09-10 03:59:29Z zhengqingpeng $
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

define('SQL_ADD_THREAD', ' t.dateline, t.special, t.lastpost AS lastthreadpost, ');

require_once libfile('function/forumlist');

$page = max(1, $_G['page']);

require_once libfile('function/discuzcode');
require_once libfile('function/post');

$threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
$threadtable_info = !empty($_G['cache']['threadtable_info']) ? $_G['cache']['threadtable_info'] : array();

if(!in_array(0, $threadtableids)) {
	$threadtableids = array_merge(array(0), $threadtableids);
}
$archiveid = intval($_G['gp_archiveid']);
$displayorderadd = $_G['uid'] ? " OR (t.displayorder IN ('-4', '-3', '-2') AND t.authorid='{$_G['uid']}')" : '';

if(empty($archiveid) || !in_array($archiveid, $threadtableids)) {
	$threadtable = !empty($_G['forum']['threadtableid']) ? "forum_thread_{$_G['forum']['threadtableid']}" : 'forum_thread';
	$_G['forum_thread'] = DB::fetch_first("SELECT * FROM ".DB::table($threadtable)." t WHERE tid='$_G[tid]'".($_G['forum_auditstatuson'] ? '' : " AND (displayorder>='0' $displayorderadd)"));
	if($_G['forum_thread']) {
		if($_G['forum']['threadtableid']) {
			$_G['forum_thread']['is_archived'] = true;
			$_G['forum_thread']['archiveid'] = $_G['forum']['threadtableid'];
		} else {
			$_G['forum_thread']['is_archived'] = false;
		}
	}
} elseif(in_array($archiveid, $threadtableids)) {
	$threadtable = $archiveid ? "forum_thread_{$archiveid}" : 'forum_thread';
	$_G['forum_thread'] = DB::fetch_first("SELECT * FROM ".DB::table($threadtable)." t WHERE tid='$_G[tid]'".($_G['forum_auditstatuson'] ? '' : " AND (displayorder>='0' $displayorderadd)"));

	$_G['forum_thread']['is_archived'] = true;
	$_G['forum_thread']['archiveid'] = $_G['gp_archiveid'];
} else {
	$threadtable = 'forum_thread';
	$_G['forum_thread'] = DB::fetch_first("SELECT * FROM ".DB::table($threadtable)." t WHERE tid='$_G[tid]'".($_G['forum_auditstatuson'] ? '' : " AND (displayorder>='0' $displayorderadd)"));
}

if(!$_G['forum_thread']) {
	showmessage('thread_nonexistence');
}

$_G['forum_thread']['short_subject'] = cutstr($_G['forum_thread']['subject'], 52);
if($_G['setting']['cachethreadlife'] && $_G['forum']['threadcaches'] && !$_G['uid'] && $page == 1 && !$_G['forum']['special'] && empty($_G['gp_do']) && empty($_G['gp_archiver'])) {
	viewthread_loadcache();
}

$posttableid = $_G['forum_thread']['posttableid'];

$_G['action']['fid'] = $_G['fid'];
$_G['action']['tid'] = $_G['tid'];
$_G['gp_authorid'] = !empty($_G['gp_authorid']) ? intval($_G['gp_authorid']) : 0;
$_G['gp_ordertype'] = !empty($_G['gp_ordertype']) ? intval($_G['gp_ordertype']) : 0;

$aimgs = array();
$skipaids = array();
$oldthreads = array();

$oldtopics = isset($_G['cookie']['oldtopics']) ? $_G['cookie']['oldtopics'] : 'D';

if($_G['setting']['visitedthreads'] && $oldtopics) {
	$oldtids = array_slice(explode('D', $oldtopics), 0, $_G['setting']['visitedthreads']);
	$oldtidsnew = array();
	foreach($oldtids as $oldtid) {
		$oldtid && $oldtidsnew[] = $oldtid;
	}
	if($oldtidsnew) {
		$query = DB::query("SELECT tid, subject FROM ".DB::table('forum_thread')." WHERE tid IN (".dimplode($oldtidsnew).")");
		while($oldthread = DB::fetch($query)) {
			$oldthreads[$oldthread['tid']] = $oldthread['subject'];
		}
	}
}

if(strpos($oldtopics, 'D'.$_G['tid'].'D') === FALSE) {
	$oldtopics = 'D'.$_G['tid'].$oldtopics;
	if(strlen($oldtopics) > 3072) {
		$oldtopics = preg_replace("((D\d+)+D).*$", "\\1", substr($oldtopics, 0, 3072));
	}
	dsetcookie('oldtopics', $oldtopics, 3600);
}

if($_G['member']['lastvisit'] < $_G['forum_thread']['lastpost'] && (!isset($_G['cookie']['fid'.$_G['fid']]) || $_G['forum_thread']['lastpost'] > $_G['cookie']['fid'.$_G['fid']])) {
	dsetcookie('fid'.$_G['fid'], $_G['forum_thread']['lastpost'], 3600);
}

$thisgid = 0;

$_G['forum_thread']['subjectenc'] = rawurlencode($_G['forum_thread']['subject']);
$_G['gp_from'] = !empty($_G['gp_from']) && $_G['gp_from'] == 'portal' ? 'portal' : '';
$fromuid = $_G['setting']['creditspolicy']['promotion_visit'] && $_G['uid'] ? '&amp;fromuid='.$_G['uid'] : '';
$feeduid = $_G['forum_thread']['authorid'] ? $_G['forum_thread']['authorid'] : 0;
$feedpostnum = $_G['forum_thread']['replies'] > $_G['ppp'] ? $_G['ppp'] : ($_G['forum_thread']['replies'] ? $_G['forum_thread']['replies'] : 1);

if(!$_G['gp_from']) {
	$forumarchivename = $threadtable_info[$_G['forum']['threadtableid']]['displayname'] ? htmlspecialchars($threadtable_info[$_G['forum']['threadtableid']]['displayname']) : lang('core', 'archive').' '.$_G['forum']['threadtableid'];
	$upnavlink = 'forum.php?mod=forumdisplay&fid='.$_G['fid'].(!empty($_G['gp_extra']) ? '&amp;'.preg_replace("/^(&amp;)*/", '', $_G['gp_extra']) : '');
	if(empty($_G['forum']['threadtableid'])) {
		$navigation = ' <em>&rsaquo;</em> <a href="forum.php">'.$_G['setting']['navs'][2]['navname'].'</a> <em>&rsaquo;</em> <a href="'.$upnavlink.'">'.(strip_tags($_G['forum']['name']) ? strip_tags($_G['forum']['name']) : $_G['forum']['name']).'</a>';
	} else {
		$navigation = ' <em>&rsaquo;</em> <a href="forum.php">'.$_G['setting']['navs'][2]['navname'].'</a> <em>&rsaquo;</em> <a href="'.$upnavlink.'">'.(strip_tags($_G['forum']['name']) ? strip_tags($_G['forum']['name']) : $_G['forum']['name']).'</a>'.' <em>&rsaquo;</em> <a href="forum.php?mod=forumdisplay&fid='.$_G['fid'].'&archiveid='.$_G['forum']['threadtableid'].'">'.$forumarchivename.'</a>';
	}
	$navtitle = $_G['forum_thread']['subject'].' - '.strip_tags($_G['forum']['name']).' - '.$_G['setting']['bbname'];
	if($_G['forum']['type'] == 'sub') {
		$fup = DB::fetch_first("SELECT fid, name FROM ".DB::table('forum_forum')." WHERE fid='".$_G['forum']['fup']."'");
		if(empty($_G['forum']['threadtableid'])) {
			$navigation = ' <em>&rsaquo;</em> <a href="forum.php">'.$_G['setting']['navs'][2]['navname'].'</a> <em>&rsaquo;</em> <a href="forum.php?mod=forumdisplay&fid='.$fup['fid'].'">'.(strip_tags($fup['name']) ? strip_tags($fup['name']) : $fup['name']).'</a> <em>&rsaquo;</em> <a href="'.$upnavlink.'">'.(strip_tags($_G['forum']['name']) ? strip_tags($_G['forum']['name']) : $_G['forum']['name']).'</a>';
		} else {
			$navigation = ' <em>&rsaquo;</em> <a href="forum.php">'.$_G['setting']['navs'][2]['navname'].'</a> <em>&rsaquo;</em> <a href="forum.php?mod=forumdisplay&fid='.$fup['fid'].'">'.(strip_tags($fup['name']) ? strip_tags($fup['name']) : $fup['name']).'</a> <em>&rsaquo;</em> <a href="'.$upnavlink.'">'.(strip_tags($_G['forum']['name']) ? strip_tags($_G['forum']['name']) : $_G['forum']['name']).'</a>'.' <em>&rsaquo;</em> <a href="forum.php?mod=forumdisplay&fid='.$_G['fid'].'&archiveid='.$_G['forum']['threadtableid'].'">'.$forumarchivename.'</a>';
		}
	}
} elseif($_G['gp_from'] == 'portal') {
	$_G['setting']['ratelogon'] = 1;
	$navigation = ' <em>&rsaquo;</em> <a href="portal.php">'.lang('core', 'portal').'</a>';
	$navsubject = $_G['forum_thread']['subject'];
}
$metakeywords = strip_tags($_G['forum_thread']['subject']);

if(in_array('forum_viewthread', $_G['setting']['rewritestatus'])) {
	$canonical = rewriteoutput('forum_viewthread', 1, '', $_G['tid'], 1, '', '');
} elseif(in_array('all_script', $_G['setting']['rewritestatus'])) {
	$canonical = rewriteoutput('all_script', 1, '', 'forum', 'viewthread&tid='.$_G['tid'].'&page=1', '');
} else {
	$canonical = 'forum.php?mod=viewthread&tid='.$_G['tid'];
}
$_G['setting']['seohead'] .= '<link href="'.$_G['siteurl'].$canonical.'" rel="canonical" />';

if($_G['forum']['status'] == 3) {
	$_G['action']['action'] = 3;
	require_once libfile('function/group');
	$status = groupperm($_G['forum'], $_G['uid']);
	if($status == 1) {
		showmessage('forum_group_status_off');
	} elseif($status == 2) {
		showmessage('forum_group_noallowed', 'forum.php?mod=group&fid='.$_G['fid']);
	} elseif($status == 3) {
		showmessage('forum_group_moderated', 'forum.php?mod=group&fid='.$_G['fid']);
	}
	$navigation = ' <em>&rsaquo;</em> <a href="group.php">'.$_G['setting']['navs'][3]['navname'].'</a> '.get_groupnav($_G['forum']);
	$_G['grouptypeid'] = $_G['forum']['fup'];
}

$_G['forum_tagscript'] = '';

$threadsort = $_G['forum_thread']['sortid'] && isset($_G['forum']['threadsorts']['types'][$_G['forum_thread']['sortid']]) ? 1 : 0;
if($threadsort) {
	require_once libfile('function/threadsort');
	$threadsortshow = threadsortshow($_G['forum_thread']['sortid'], $_G['tid']);
}

if(empty($_G['forum']['allowview'])) {

	if(!$_G['forum']['viewperm'] && !$_G['group']['readaccess']) {
		showmessage('group_nopermission', NULL, array('grouptitle' => $_G['group']['grouptitle']), array('login' => 1));
	} elseif($_G['forum']['viewperm'] && !forumperm($_G['forum']['viewperm'])) {
		$navtitle = '';
		showmessagenoperm('viewperm', $_G['fid']);
	}

} elseif($_G['forum']['allowview'] == -1) {
	$navtitle = '';
	showmessage('forum_access_view_disallow');
}

if($_G['forum']['formulaperm']) {
	formulaperm($_G['forum']['formulaperm']);
}

if($_G['forum']['password'] && $_G['forum']['password'] != $_G['cookie']['fidpw'.$_G['fid']]) {
	dheader("Location: $_G[siteurl]forum.php?mod=forumdisplay&fid=$_G[fid]");
}

if($_G['forum_thread']['readperm'] && $_G['forum_thread']['readperm'] > $_G['group']['readaccess'] && !$_G['forum']['ismoderator'] && $_G['forum_thread']['authorid'] != $_G['uid']) {
	showmessage('thread_nopermission', NULL, array('readperm' => $_G['forum_thread']['readperm']), array('login' => 1));
}

$usemagic = array('user' => array(), 'thread' => array());

$hiddenreplies = getstatus($_G['forum_thread']['status'], 2);

$rushreply = getstatus($_G['forum_thread']['status'], 3);

$savepostposition = getstatus($_G['forum_thread']['status'], 1);

$_G['forum_threadpay'] = FALSE;
if($_G['forum_thread']['price'] > 0 && $_G['forum_thread']['special'] == 0) {
	if($_G['setting']['maxchargespan'] && TIMESTAMP - $_G['forum_thread']['dateline'] >= $_G['setting']['maxchargespan'] * 3600) {
		DB::query("UPDATE ".DB::table($threadtable)." SET price='0' WHERE tid='$_G[tid]'");
		$_G['forum_thread']['price'] = 0;
	} else {
		$exemptvalue = $_G['forum']['ismoderator'] ? 128 : 16;
		if(!($_G['group']['exempt'] & $exemptvalue) && $_G['forum_thread']['authorid'] != $_G['uid']) {
			$query = DB::query("SELECT relatedid FROM ".DB::table('common_credit_log')." WHERE relatedid='$_G[tid]' AND uid='$_G[uid]' AND operation='BTC'");
			if(!DB::num_rows($query)) {
				require_once libfile('thread/pay', 'include');
				$_G['forum_threadpay'] = TRUE;
			}
		}
	}
}

$_G['group']['raterange'] = $_G['setting']['modratelimit'] && $adminid == 3 && !$_G['forum']['ismoderator'] ? array() : $_G['group']['raterange'];
$_G['gp_extra'] = !empty($_G['gp_extra']) ? rawurlencode($_G['gp_extra']) : '';

$_G['group']['allowgetattach'] = !empty($_G['forum']['allowgetattach']) || ($_G['group']['allowgetattach'] && !$_G['forum']['getattachperm']) || forumperm($_G['forum']['getattachperm']);
$_G['getattachcredits'] = '';
if($_G['forum_thread']['attachment']) {
	$exemptvalue = $_G['forum']['ismoderator'] ? 32 : 4;
	if(!($_G['group']['exempt'] & $exemptvalue)) {
		$creditlog = updatecreditbyaction('getattach', $_G['uid'], array(), '', 1, 0, $_G['forum_thread']['fid']);
		$p = '';
		if($creditlog['updatecredit']) for($i = 1;$i <= 8;$i++) {
			if($policy = $creditlog['extcredits'.$i]) {
				$_G['getattachcredits'] .= $p.$_G['setting']['extcredits'][$i]['title'].' '.$policy.' '.$_G['setting']['extcredits'][$i]['unit'];
				$p = ', ';
			}
		}
	}
}

$exemptvalue = $_G['forum']['ismoderator'] ? 64 : 8;
$_G['forum_attachmentdown'] = $_G['group']['exempt'] & $exemptvalue;

$seccodecheck = ($_G['setting']['seccodestatus'] & 4) && (!$_G['setting']['seccodedata']['minposts'] || getuserprofile('posts') < $_G['setting']['seccodedata']['minposts']);
$secqaacheck = $_G['setting']['secqaa']['status'] & 2 && (!$_G['setting']['secqaa']['minposts'] || getuserprofile('posts') < $_G['setting']['secqaa']['minposts']);

$postlist = $_G['forum_attachtags'] = $attachlist = $_G['forum_threadstamp'] = array();
$aimgcount = 0;
$_G['forum_attachpids'] = -1;

if(!empty($_G['gp_action']) && $_G['gp_action'] == 'printable' && $_G['tid']) {
	require_once libfile('thread/printable', 'include');
	dexit();
}

if($_G['forum_thread']['stamp'] >= 0) {
	$_G['forum_threadstamp'] = $_G['cache']['stamps'][$_G['forum_thread']['stamp']];
}

$thisgid = $_G['forum']['type'] == 'forum' ? $_G['forum']['fup'] : $_G['cache']['forums'][$_G['forum']['fup']]['fup'];
$lastmod = $_G['forum_thread']['moderated'] ? viewthread_lastmod() : array();

$showsettings = str_pad(decbin($_G['setting']['showsettings']), 3, '0', STR_PAD_LEFT);

$showsignatures = $showsettings{0};
$showavatars = $showsettings{1};
$_G['setting']['showimages'] = $showsettings{2};

$highlightstatus = isset($_G['gp_highlight']) && str_replace('+', '', $_G['gp_highlight']) ? 1 : 0;

$_G['forum']['allowreply'] = isset($_G['forum']['allowreply']) ? $_G['forum']['allowreply'] : '';
$_G['forum']['allowpost'] = isset($_G['forum']['allowpost']) ? $_G['forum']['allowpost'] : '';
$allowpostreply = ($_G['forum']['allowreply'] != -1) && (($_G['forum_thread']['isgroup'] || (!$_G['forum_thread']['closed'] && !checkautoclose($_G['forum_thread']))) || $_G['forum']['ismoderator']) && ((!$_G['forum']['replyperm'] && $_G['perm']['allowreply']) || ($_G['forum']['replyperm'] && forumperm($_G['forum']['replyperm'], $_G['perm']['groupid'])) || $_G['forum']['allowreply']);
if(!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum'])) {
	$allowpostreply = false;
}
$allowpostreplyguest = !$_G['uid'] && (($_G['forum']['allowreply'] != -1) && (($_G['forum_thread']['isgroup'] || (!$_G['forum_thread']['closed'] && !checkautoclose($_G['forum_thread']))) || $_G['forum']['ismoderator']) && ((!$_G['forum']['replyperm'] && $_G['group']['allowreply']) || ($_G['forum']['replyperm'] && forumperm($_G['forum']['replyperm'])) || $_G['forum']['allowreply']));
$guestreply = !$_G['uid'] && !$allowpostreplyguest && !$_G['group']['allowreply'] && $_G['perm']['allowreply'];
$_G['group']['allowpost'] = $_G['forum']['allowpost'] != -1 && ((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])) || $_G['forum']['allowpost']);
$allowpostreply = $allowpostreplyguest ? true : $allowpostreply;

if($_G['group']['allowpost']) {
	$_G['group']['allowpostpoll'] = $_G['group']['allowpostpoll'] && ($_G['forum']['allowpostspecial'] & 1);
	$_G['group']['allowposttrade'] = $_G['group']['allowposttrade'] && ($_G['forum']['allowpostspecial'] & 2);
	$_G['group']['allowpostreward'] = $_G['group']['allowpostreward'] && ($_G['forum']['allowpostspecial'] & 4) && isset($_G['setting']['extcredits'][$_G['setting']['creditstrans']]);
	$_G['group']['allowpostactivity'] = $_G['group']['allowpostactivity'] && ($_G['forum']['allowpostspecial'] & 8);
	$_G['group']['allowpostdebate'] = $_G['group']['allowpostdebate'] && ($_G['forum']['allowpostspecial'] & 16);
} else {
	$_G['group']['allowpostpoll'] = $_G['group']['allowposttrade'] = $_G['group']['allowpostreward'] = $_G['group']['allowpostactivity'] = $_G['group']['allowpostdebate'] = FALSE;
}

$_G['forum']['threadplugin'] = $_G['group']['allowpost'] && $_G['setting']['threadplugins'] ? is_array($_G['forum']['threadplugin']) ? $_G['forum']['threadplugin'] : unserialize($_G['forum']['threadplugin']) : array();

$_G['setting']['visitedforums'] = $_G['setting']['visitedforums'] ? visitedforums() : '';
$forummenu = '';

if($_G['setting']['forumjump']) {
	$forummenu = forumselect(FALSE, 1);
}



$relatedthreadlist = array();
$relatedthreadupdate = $tagupdate = FALSE;
$relatedkeywords = $tradekeywords = $_G['forum_firstpid'] = '';
$metakeywords = $_G['forum']['name'];

if(!isset($_G['cookie']['collapse']) || strpos($_G['cookie']['collapse'], 'modarea_c') === FALSE) {
	$collapseimg['modarea_c'] = 'collapsed_no';
	$collapse['modarea_c'] = '';
} else {
	$collapseimg['modarea_c'] = 'collapsed_yes';
	$collapse['modarea_c'] = 'display: none';
}

$threadtag = array();
$_G['setting']['tagstatus'] = $_G['setting']['tagstatus'] && $_G['forum']['allowtag'] ? ($_G['setting']['tagstatus'] == 2 ? 2 : $_G['forum']['allowtag']) : 0;

viewthread_updateviews();

@extract($_G['cache']['custominfo']);

$_G['setting']['infosidestatus']['posts'] = $_G['setting']['infosidestatus'][1] && isset($_G['setting']['infosidestatus']['f'.$_G['fid']]['posts']) ? $_G['setting']['infosidestatus']['f'.$_G['fid']]['posts'] : $_G['setting']['infosidestatus']['posts'];


$postfieldsadd = $specialadd1 = $specialadd2 = $specialextra = '';
if($_G['forum_thread']['special'] == 2) {
	if(!empty($_G['gp_do']) && $_G['gp_do'] == 'tradeinfo') {
		require_once libfile('thread/trade', 'include');
	}
	$query = DB::query("SELECT pid FROM ".DB::table('forum_trade')." WHERE tid='$_G[tid]'");
	while($trade = DB::fetch($query)) {
		$tpids[] = $trade['pid'];
	}
	$specialadd2 = " AND pid NOT IN (".dimplode($tpids).")";
} elseif($_G['forum_thread']['special'] == 5) {
	if(isset($_G['gp_stand']) && $_G['gp_stand'] >= 0 && $_G['gp_stand'] < 3) {
		$specialadd1 .= "LEFT JOIN ".DB::table('forum_debatepost')." dp ON p.pid=dp.pid";
		if($_G['gp_stand']) {
			$specialadd2 .= "AND (dp.stand='$_G[gp_stand]' OR p.first='1')";
		} else {
			$specialadd2 .= "AND (dp.stand='0' OR dp.stand IS NULL OR p.first='1')";
		}
		$specialextra = "&amp;stand=$_G[gp_stand]";
	} else {
		$specialadd1 = "LEFT JOIN ".DB::table('forum_debatepost')." dp ON p.pid=dp.pid";
	}
	$postfieldsadd .= ", dp.stand, dp.voters";
}

$onlyauthoradd = $threadplughtml = '';
$posttable = $posttableid ? "forum_post_$posttableid" : 'forum_post';

if(empty($_G['gp_viewpid'])) {

	$ordertype = empty($_G['gp_ordertype']) && getstatus($_G['forum_thread']['status'], 4) ? 1 : $_G['gp_ordertype'];

	$sticklist = array();
	if($_G['forum_thread']['stickreply'] && $page == 1 && !$_G['gp_authorid'] && !$ordertype) {
		$query = DB::query("SELECT p.*, ps.position FROM ".DB::table('forum_poststick')." ps
			LEFT JOIN ".DB::table($posttable)." p USING(pid)
			WHERE ps.tid='$_G[tid]' ORDER BY ps.dateline DESC");
		while($post = DB::fetch($query)) {
			$post['message'] = messagecutstr($post['message'], 400);
			$post['avatar'] = discuz_uc_avatar($post['authorid'], 'small');
			$sticklist[$post['pid']] = $post;
		}
		$stickcount = count($sticklist);
	}

	if($_G['gp_authorid']) {
		$_G['forum_thread']['replies'] = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0' AND authorid='$_G[gp_authorid]'");
		$_G['forum_thread']['replies']--;
		if($_G['forum_thread']['replies'] < 0) {
			showmessage('undefined_action');
		}
		$onlyauthoradd = "AND p.authorid='$_G[gp_authorid]'";
	} elseif($_G['forum_thread']['special'] == 5) {
		if(isset($_G['gp_stand']) && $_G['gp_stand'] >= 0 && $_G['gp_stand'] < 3) {
			$_G['forum_thread']['replies'] = DB::result_first("SELECT COUNT(*) FROM ".DB::table('forum_debatepost')." WHERE tid='$_G[tid]' AND stand='$_G[gp_stand]'");
		} else {
			$_G['forum_thread']['replies'] = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0'");
			$_G['forum_thread']['replies'] > 0 && $_G['forum_thread']['replies']--;
		}
	} elseif($_G['forum_thread']['special'] == 2) {
		$tradenum = DB::result_first("SELECT count(*) FROM ".DB::table('forum_trade')." WHERE tid='$_G[tid]'");
		$_G['forum_thread']['replies'] -= $tradenum;
	}

	$_G['ppp'] = $_G['forum']['threadcaches'] && !$_G['uid'] ? $_G['setting']['postperpage'] : $_G['ppp'];
	$totalpage = ceil(($_G['forum_thread']['replies'] + 1) / $_G['ppp']);
	$page > $totalpage && $page = $totalpage;
	$_G['forum_pagebydesc'] = $page > 50 && $page > ($totalpage / 2) ? TRUE : FALSE;

	if($_G['forum_pagebydesc']) {
		$firstpagesize = ($_G['forum_thread']['replies'] + 1) % $_G['ppp'];
		$_G['forum_ppp3'] = $_G['forum_ppp2'] = $page == $totalpage && $firstpagesize ? $firstpagesize : $_G['ppp'];
		$realpage = $totalpage - $page + 1;
		$start_limit = max(0, ($realpage - 2) * $_G['ppp'] + $firstpagesize);
		$_G['forum_numpost'] = ($page - 1) * $_G['ppp'];
		if($ordertype != 1) {
			$pageadd =  "ORDER BY p.dateline DESC LIMIT $start_limit, ".$_G['forum_ppp2'];
		} else {
			$_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'] + ($page > 1 ? 1 : 0);
			$pageadd = "ORDER BY p.first ASC, p.dateline ASC LIMIT $start_limit, ".$_G['forum_ppp2'];
		}
	} else {
		$start_limit = $_G['forum_numpost'] = max(0, ($page - 1) * $_G['ppp']);
		if($start_limit > $_G['forum_thread']['replies']) {
			$start_limit = $_G['forum_numpost'] = 0;
			$page = 1;
		}
		if($ordertype != 1) {
			$pageadd = "ORDER BY p.dateline LIMIT $start_limit, $_G[ppp]";
		} else {
			$_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'] + ($page > 1 ? 1 : 0);
			$pageadd = "ORDER BY p.first DESC, p.dateline DESC LIMIT $start_limit, $_G[ppp]";
		}
	}
	$multipage_archive = $_G['forum_thread']['is_archived'] ? "archive={$_G['forum_thread']['archiveid']}" : '';
	$multipage = multi($_G['forum_thread']['replies'] + 1, $_G['ppp'], $page, "forum.php?mod=viewthread&tid=$_G[tid]".(!empty($multipage_archive) ? "&{$multipage_archive}" : '')."&amp;extra=$_G[gp_extra]".($ordertype && $ordertype != getstatus($_G['forum_thread']['status'], 4) ? "&amp;ordertype=$ordertype" : '').(isset($_G['gp_highlight']) ? "&amp;highlight=".rawurlencode($_G['gp_highlight']) : '').(!empty($_G['gp_authorid']) ? "&amp;authorid=$_G[gp_authorid]" : '').(!empty($_G['gp_from']) ? "&amp;from=$_G[gp_from]" : '').$specialextra);
} else {
	$_G['gp_viewpid'] = intval($_G['gp_viewpid']);
	$pageadd = "AND p.pid='$_G[gp_viewpid]'";
}

$_G['forum_newpostanchor'] = $_G['forum_postcount'] = $_G['forum_ratelogpid'] = $_G['forum_commonpid'] = 0;

$_G['forum_onlineauthors'] = array();

$query = "SELECT p.* $postfieldsadd
		FROM ".DB::table($posttable)." p
		$specialadd1 ";

$cachepids = $positionlist = $postusers = $skipaids = array();
if($savepostposition && empty($onlyauthoradd) && empty($specialadd2) && empty($_G['gp_viewpid']) && $ordertype != 1) {
	$start = ($page - 1) * $_G['ppp'] + 1;
	$end = $start + $_G['ppp'];
	$q2 = DB::query("SELECT pid, position FROM ".DB::table('forum_postposition')." WHERE tid='$_G[tid]' AND position>='$start' AND position<'$end' ORDER BY position");
	while ($post = DB::fetch($q2)) {
		$cachepids[] = $post['pid'];
		$positionlist[$post['pid']] = $post['position'];
	}
	$cachepids = dimplode($cachepids);
	$pagebydesc = false;
}

$query .= $savepostposition && $cachepids ? "WHERE p.pid IN ($cachepids)" : ("WHERE p.tid='$_G[gp_tid]'".($_G['forum_auditstatuson'] || in_array($_G['forum_thread']['displayorder'], array(-2, -3, -4)) && $_G['forum_thread']['authorid'] == $_G['uid'] ? '' : " AND p.invisible='0'")." $specialadd2 $onlyauthoradd $pageadd");

$query = DB::query($query);
while($post = DB::fetch($query)) {
	if(($onlyauthoradd && $post['anonymous'] == 0) || !$onlyauthoradd) {
		$postlist[$post['pid']] = $post;
		$postusers[$post['authorid']] = array();
		if($post['first']) {
			$metadescription = strip_tags(cutstr(preg_replace('/\[.+?\]/', '', $post['message']), 140));
		}
	}
}
if(!$metadescription) {
	$metadescription = strip_tags($_G['forum_thread']['subject']);
}
if($postusers) {
	$verifyadd = '';
	if($_G['setting']['verify']['enabled']) {
		$verifyadd = "LEFT JOIN ".DB::table('common_member_verify')." mv USING(uid)";
		$fieldsadd .= ', mv.verify1, mv.verify2, mv.verify3, mv.verify4, mv.verify5';
	}
	$query = DB::query("SELECT m.uid, m.username, m.groupid, m.adminid, m.regdate, m.credits, m.email, m.status AS memberstatus,
			ms.lastactivity, ms.lastactivity, ms.invisible AS authorinvisible,
			mc.*, mp.gender, mp.site, mp.icq, mp.qq, mp.yahoo, mp.msn, mp.taobao, mp.alipay,
			mf.medals, mf.sightml AS signature, mf.customstatus $fieldsadd
			FROM ".DB::table('common_member')." m
			LEFT JOIN ".DB::table('common_member_field_forum')." mf USING(uid)
			LEFT JOIN ".DB::table('common_member_status')." ms USING(uid)
			LEFT JOIN ".DB::table('common_member_count')." mc USING(uid)
			LEFT JOIN ".DB::table('common_member_profile')." mp USING(uid)
			$verifyadd
			WHERE m.uid IN (".dimplode(array_keys($postusers)).")");
	while($postuser = DB::fetch($query)) {
		$postusers[$postuser['uid']] = $postuser;
	}
	foreach($postlist as $pid => $post) {
		$post = array_merge($postlist[$pid], $postusers[$post['authorid']]);
		$postlist[$pid] = viewthread_procpost($post, $_G['member']['lastvisit'], $ordertype);
	}

}

if($savepostposition && $positionlist) {
	foreach ($positionlist as $pid => $position)
	$postlist[$pid]['number'] = $position;
}

if($_G['forum_thread']['special'] > 0 && (empty($_G['gp_viewpid']) || $_G['gp_viewpid'] == $_G['forum_firstpid'])) {
	$_G['forum_thread']['starttime'] = gmdate($_G['forum_thread']['dateline']);
	$_G['forum_thread']['remaintime'] = '';
	switch($_G['forum_thread']['special']) {
		case 1: require_once libfile('thread/poll', 'include'); break;
		case 2: require_once libfile('thread/trade', 'include'); break;
		case 3: require_once libfile('thread/reward', 'include'); break;
		case 4: require_once libfile('thread/activity', 'include'); break;
		case 5: require_once libfile('thread/debate', 'include'); break;
		case 127:
			$sppos = strpos($postlist[$_G['forum_firstpid']]['message'], chr(0).chr(0).chr(0));
			$specialextra = substr($postlist[$_G['forum_firstpid']]['message'], $sppos + 3);
			$postlist[$_G['forum_firstpid']]['message'] = substr($postlist[$_G['forum_firstpid']]['message'], 0, $sppos);
			if($specialextra) {
				if(array_key_exists($specialextra, $_G['setting']['threadplugins'])) {
					@include_once DISCUZ_ROOT.'./source/plugin/'.$_G['setting']['threadplugins'][$specialextra]['module'].'.class.php';
					$classname = 'threadplugin_'.$specialextra;
					if(class_exists($classname) && method_exists($threadpluginclass = new $classname, 'viewthread')) {
						$threadplughtml = $threadpluginclass->viewthread($_G['tid']);
					}
				}
			}
			break;
	}
}

if(empty($_G['gp_authorid']) && empty($postlist)) {
	$replies = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0'");
	$replies = intval($replies) - 1;
	if($_G['forum_thread']['replies'] != $replies && $replies > 0) {
		DB::query("UPDATE ".DB::table($threadtable)." SET replies='$replies' WHERE tid='$_G[tid]'");
		dheader("Location: forum.php?mod=redirect&tid=$_G[tid]&goto=lastpost");
	}
}

if($_G['forum_pagebydesc'] && (!$savepostposition || $_G['gp_ordertype'] == 1)) {
	$postlist = array_reverse($postlist, TRUE);
}

if($_G['setting']['vtonlinestatus'] == 2 && $_G['forum_onlineauthors']) {
	$query = DB::query("SELECT uid FROM ".DB::table('common_session')." WHERE uid IN(".dimplode($_G['forum_onlineauthors']).") AND invisible=0");
	$_G['forum_onlineauthors'] = array();
	while($author = DB::fetch($query)) {
		$_G['forum_onlineauthors'][$author['uid']] = 1;
	}
} else {
	$_G['forum_onlineauthors'] = array();
}
$ratelogs = array();
if($_G['forum_ratelogpid']) {
	$query = DB::query("SELECT * FROM ".DB::table('forum_ratelog')." WHERE pid IN (".$_G['forum_ratelogpid'].") ORDER BY dateline DESC");
	while($ratelog = DB::fetch($query)) {
		if(count($postlist[$ratelog['pid']]['ratelog']) < $_G['setting']['ratelogrecord']) {
			$ratelogs[$ratelog['pid']][$ratelog['uid']]['username'] = $ratelog['username'];
			$ratelogs[$ratelog['pid']][$ratelog['uid']]['score'][$ratelog['extcredits']] += $ratelog['score'];
			empty($ratelogs[$ratelog['pid']][$ratelog['uid']]['reason']) && $ratelogs[$ratelog['pid']][$ratelog['uid']]['reason'] = dhtmlspecialchars($ratelog['reason']);
			$postlist[$ratelog['pid']]['ratelog'][$ratelog['uid']] = $ratelogs[$ratelog['pid']][$ratelog['uid']];
		}
		$postlist[$ratelog['pid']]['ratelogextcredits'][$ratelog['extcredits']] += $ratelog['score'];

		if(!$postlist[$ratelog['pid']]['totalrate'] || !in_array($ratelog['uid'], $postlist[$ratelog['pid']]['totalrate'])) {
			$postlist[$ratelog['pid']]['totalrate'][] = $ratelog['uid'];
		}
	}
	foreach($postlist as $key => $val) {
		if(!empty($val['ratelogextcredits'])) {
			ksort($postlist[$key]['ratelogextcredits']);
		}
	}
}

$comments = $commentcount = $totalcomment = array();
if($_G['forum_commonpid'] && $_G['setting']['commentnumber']) {
	$query = DB::query("SELECT * FROM ".DB::table('forum_postcomment')." WHERE pid IN (".$_G['forum_commonpid'].') ORDER BY dateline DESC');
	while($comment = DB::fetch($query)) {
		if($comment['authorid']) {
			$commentcount[$comment['pid']]++;
		}
		if(count($comments[$comment['pid']]) < $_G['setting']['commentnumber'] && $comment['authorid']) {
			$comment['avatar'] = discuz_uc_avatar($comment['authorid'], 'small');
			$comment['dateline'] = dgmdate($comment['dateline'], 'u');
			$comments[$comment['pid']][] = str_replace(array('[b]', '[/b]', '[/color]'), array('<b>', '</b>', '</font>'), preg_replace("/\[color=([#\w]+?)\]/i", "<font color=\"\\1\">", $comment));
		}
		if(!$comment['authorid']) {
			$cic = 0;
			$totalcomment[$comment['pid']] = preg_replace('/<i>([\.\d]+)<\/i>/e', "'<i class=\"cmstarv\" style=\"background-position:20px -'.(intval(\\1) * 16).'px\">'.sprintf('%1.1f', \\1).'</i>'.(\$cic++ % 2 ? '<br />' : '');", $comment['comment']);
		}
	}
}

if($_G['forum_attachpids'] != '-1' && !$_G['gp_archiver']) {
	require_once libfile('function/attachment');
	if(is_array($threadsortshow) && !empty($threadsortshow['sortaids'])) {
		$skipaids = $threadsortshow['sortaids'];
	}
	parseattach($_G['forum_attachpids'], $_G['forum_attachtags'], $postlist, $skipaids);
}

if(empty($postlist)) {
	showmessage('undefined_action', NULL);
} else {
	foreach($postlist as $pid => $post) {
		$postlist[$pid]['message'] = preg_replace("/\[attach\]\d+\[\/attach\]/i", '', $postlist[$pid]['message']);
	}
}

viewthread_parsetags($postlist);

if($_G['gp_archiver']) {
	include loadarchiver('forum/viewthread');
	exit();
}

$_G['forum_thread']['heatlevel'] = $_G['forum_thread']['recommendlevel'] = 0;
if($_G['setting']['heatthread']['iconlevels']) {
	foreach($_G['setting']['heatthread']['iconlevels'] as $k => $i) {
		if($_G['forum_thread']['heats'] > $i) {
			$_G['forum_thread']['heatlevel'] = $k + 1;
			break;
		}
	}
}

if(!empty($_G['setting']['recommendthread']['status']) && $_G['forum_thread']['recommends']) {
	foreach($_G['setting']['recommendthread']['iconlevels'] as $k => $i) {
		if($_G['forum_thread']['recommends'] > $i) {
			$_G['forum_thread']['recommendlevel'] = $k+1;
			break;
		}
	}
}

$allowblockrecommend = $_G['group']['allowdiy'] || $_G['group']['allowauthorizedblock'];
$allowpostarticle = $_G['group']['allowmanagearticle'] || $_G['group']['allowauthorizedarticle'];
$allowpusharticle = empty($_G['forum_thread']['special']) && empty($_G['forum_thread']['sortid']) && !$_G['forum_thread']['pushedaid'];
$modmenu = array(
	'thread' => $_G['forum_thread']['digest'] >= 0 && ($_G['forum']['ismoderator'] || $allowblockrecommend || $allowpusharticle && $allowpostarticle),
	'post' => $_G['forum']['ismoderator'] && ($_G['group']['allowwarnpost'] || $_G['group']['allowbanpost'] || $_G['group']['allowdelpost'] || $_G['group']['allowstickreply']) || $_G['forum_thread']['pushedaid'] && $allowpostarticle
);

if(empty($_G['gp_viewpid'])) {
	$sufix = '';
	if($_G['gp_from'] == 'portal') {
		$_G['disabledwidthauto'] = 1;
		$sufix = '_portal';
		$post = &$postlist[$_G['forum_firstpid']];
	}
	if($_G['inajax']) {
		include template('common/header_ajax');
		$sufix = '_from_node';
	}
	include template('diy:forum/viewthread'.$sufix.':'.$_G['fid']);
	if($_G['inajax']) {
		include template('common/footer_ajax');
	}
} else {
	$_G['setting']['admode'] = 0;
	$post = $postlist[$_G['gp_viewpid']];
	$post['number'] = DB::result_first("SELECT count(*) FROM ".DB::table($posttable)." WHERE tid='$post[tid]' AND dateline<='$post[dbdateline]'");
	include template('common/header_ajax');
	hookscriptoutput('viewthread');
	$postcount = 0;
	if($_G['gp_from']) {
		include template('forum/viewthread_from_node');
	} else {
		include template('forum/viewthread_node');
	}
	include template('common/footer_ajax');
}



function viewthread_updateviews() {
	global $_G, $do, $threadtable;

	if($_G['setting']['delayviewcount'] == 1 || $_G['setting']['delayviewcount'] == 3) {
		$_G['forum_logfile'] = './data/cache/forum_cache_threadviews.log';
		if(substr(TIMESTAMP, -2) == '00') {
			require_once libfile('function/misc');
			updateviews($threadtable, 'tid', 'views', $_G['forum_logfile']);
		}
		if(@$fp = fopen(DISCUZ_ROOT.$_G['forum_logfile'], 'a')) {
			fwrite($fp, "$_G[tid]\n");
			fclose($fp);
		} elseif($adminid == 1) {
			showmessage('view_log_invalid', '', array('logfile' => $_G['forum_logfile']));
		}
	} else {

		DB::query("UPDATE LOW_PRIORITY ".DB::table($threadtable)." SET views=views+1 WHERE tid='$_G[tid]'", 'UNBUFFERED');

	}
}

function viewthread_procpost($post, $lastvisit, $ordertype, $special = 0) {
	global $_G;

	if(!$_G['forum_newpostanchor'] && $post['dateline'] > $lastvisit) {
		$post['newpostanchor'] = '<a name="newpost"></a>';
		$_G['forum_newpostanchor'] = 1;
	} else {
		$post['newpostanchor'] = '';
	}

	$post['lastpostanchor'] = ($ordertype != 1 && $_G['forum_numpost'] == $_G['forum_thread']['replies']) || ($ordertype == 1 && $_G['forum_numpost'] == $_G['forum_thread']['replies'] + 2) ? '<a name="lastpost"></a>' : '';

	if($_G['forum_pagebydesc']) {
		if($ordertype != 1) {
			$post['number'] = $_G['forum_numpost'] + $_G['forum_ppp2']--;
		} else {
			$post['number'] = $post['first'] == 1 ? 1 : $_G['forum_numpost'] - $_G['forum_ppp2']--;
		}
	} else {
		if($ordertype != 1) {
			$post['number'] = ++$_G['forum_numpost'];
		} else {
			$post['number'] = $post['first'] == 1 ? 1 : --$_G['forum_numpost'];
		}
	}

	$_G['forum_postcount']++;

	$post['dbdateline'] = $post['dateline'];
	$post['dateline'] = dgmdate($post['dateline'], 'u');
	$post['groupid'] = $_G['cache']['usergroups'][$post['groupid']] ? $post['groupid'] : 7;

	if($post['username']) {

		$_G['forum_onlineauthors'][] = $post['authorid'];
		$post['usernameenc'] = rawurlencode($post['username']);
		$post['readaccess'] = $_G['cache']['usergroups'][$post['groupid']]['readaccess'];
		if($_G['cache']['usergroups'][$post['groupid']]['userstatusby'] == 1) {
			$post['authortitle'] = $_G['cache']['usergroups'][$post['groupid']]['grouptitle'];
			$post['stars'] = $_G['cache']['usergroups'][$post['groupid']]['stars'];
		}
		$post['upgradecredit'] = false;
		if($_G['cache']['usergroups'][$post['groupid']]['type'] == 'member' && $_G['cache']['usergroups'][$post['groupid']]['creditslower'] != 999999999) {
			$post['upgradecredit'] = $_G['cache']['usergroups'][$post['groupid']]['creditslower'] - $post['credits'];
		}

		$post['taobaoas'] = addslashes($post['taobao']);
		$post['regdate'] = dgmdate($post['regdate'], 'd');
		$post['lastdate'] = dgmdate($post['lastactivity'], 'd');

		$post['authoras'] = !$post['anonymous'] ? ' '.addslashes($post['author']) : '';

		if($post['medals']) {
			loadcache('medals');
			foreach($post['medals'] = explode("\t", $post['medals']) as $key => $medalid) {
				list($medalid, $medalexpiration) = explode("|", $medalid);
				if(isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
					$post['medals'][$key] = $_G['cache']['medals'][$medalid];
				} else {
					unset($post['medals'][$key]);
				}
			}
		}

		$post['avatar'] = discuz_uc_avatar($post['authorid']);
		$post['groupicon'] = $post['avatar'] ? g_icon($post['groupid'], 1) : '';
		$post['banned'] = $post['status'] & 1;
		$post['warned'] = ($post['status'] & 2) >> 1;

	} else {
		if(!$post['authorid']) {
			$post['useip'] = substr($post['useip'], 0, strrpos($post['useip'], '.')).'.x';
		}
	}
	$post['attachments'] = array();
	$post['imagelist'] = $post['attachlist'] = '';

	if($post['attachment']) {
		if($_G['group']['allowgetattach']) {
			$_G['forum_attachpids'] .= ",$post[pid]";
			$post['attachment'] = 0;
			if(preg_match_all("/\[attach\](\d+)\[\/attach\]/i", $post['message'], $matchaids)) {
				$_G['forum_attachtags'][$post['pid']] = $matchaids[1];
			}
		} else {
			$post['message'] = preg_replace("/\[attach\](\d+)\[\/attach\]/i", '', $post['message']);
		}
	}

	$_G['forum_ratelogpid'] .= ($_G['setting']['ratelogrecord'] && $post['ratetimes']) ? ','.$post['pid'] : '';
	if($_G['setting']['commentnumber'] && ($post['first'] && $_G['setting']['commentfirstpost'] || !$post['first'])) {
		$_G['forum_commonpid'] .= $post['comment'] ? ','.$post['pid'] : '';
	}
	$post['allowcomment'] = $_G['setting']['commentnumber'] && ($_G['setting']['commentpostself'] || $post['authorid'] != $_G['uid']) &&
		($post['first'] && $_G['setting']['commentfirstpost'] && in_array($_G['group']['allowcommentpost'], array(1, 3)) ||
		(!$post['first'] && in_array($_G['group']['allowcommentpost'], array(2, 3))));
	$_G['forum']['allowbbcode'] = $_G['forum']['allowbbcode'] ? -$post['groupid'] : 0;
	$post['signature'] = $post['usesig'] ? ($_G['setting']['sigviewcond'] ? (strlen($post['message']) > $_G['setting']['sigviewcond'] ? $post['signature'] : '') : $post['signature']) : '';
	if($post['first']) {
		$_G['forum_firstpid'] = $post['pid'];
		$_G['seodescription'] = !$_G['forum_thread']['price'] ? str_replace(array("\r", "\n"), '', messagecutstr(strip_tags($post['message']), 160)) : '';
		$_G['seokeywords'] = strip_tags($post['subject']);
	}
	if(!$_G['gp_archiver']) {
		$post['message'] = discuzcode($post['message'], $post['smileyoff'], $post['bbcodeoff'], $post['htmlon'] & 1, $_G['forum']['allowsmilies'], $_G['forum']['allowbbcode'], ($_G['forum']['allowimgcode'] && $_G['setting']['showimages'] ? 1 : 0), $_G['forum']['allowhtml'], ($_G['forum']['jammer'] && $post['authorid'] != $_G['uid'] ? 1 : 0), 0, $post['authorid'], $_G['forum']['allowmediacode'], $post['pid']);
	}
	$_G['forum_firstpid'] = intval($_G['forum_firstpid']);
	return $post;
}

function viewthread_loadcache() {
	global $_G;
	$_G['forum']['livedays'] = ceil((TIMESTAMP - $_G['forum']['dateline']) / 86400);
	$_G['forum']['lastpostdays'] = ceil((TIMESTAMP - $_G['forum']['lastthreadpost']) / 86400);
	$threadcachemark = 100 - (
	$_G['forum']['displayorder'] * 15 +
	$_G['forum']['digest'] * 10 +
	min($_G['forum']['views'] / max($_G['forum']['livedays'], 10) * 2, 50) +
	max(-10, (15 - $_G['forum']['lastpostdays'])) +
	min($_G['forum']['replies'] / $_G['setting']['postperpage'] * 1.5, 15));
	if($threadcachemark < $_G['forum']['threadcaches']) {

		$threadcache = getcacheinfo($_G['tid']);

		if(TIMESTAMP - $threadcache['filemtime'] > $_G['setting']['cachethreadlife']) {
			@unlink($threadcache['filename']);
			define('CACHE_FILE', $threadcache['filename']);
		} else {
			readfile($threadcache['filename']);

			viewthread_updateviews();
			$_G['setting']['debug'] && debuginfo();
			$_G['setting']['debug'] ? die('<script type="text/javascript">document.getElementById("debuginfo").innerHTML = " '.($_G['setting']['debug'] ? 'Updated at '.gmdate("H:i:s", $threadcache['filemtime'] + 3600 * 8).', Processed in '.$debuginfo['time'].' second(s), '.$debuginfo['queries'].' Queries'.($_G['gzipcompress'] ? ', Gzip enabled' : '') : '').'";</script>') : die();
		}
	}
}

function viewthread_lastmod() {
	global $_G, $threadtable;
	if($lastmod = DB::fetch_first("SELECT uid AS moduid, username AS modusername, dateline AS moddateline, action AS modaction, magicid, stamp
		FROM ".DB::table('forum_threadmod')."
		WHERE tid='$_G[tid]' ORDER BY dateline DESC LIMIT 1")) {
		$modactioncode = lang('forum/modaction');
		$lastmod['modusername'] = $lastmod['modusername'] ? $lastmod['modusername'] : 'System';
		$lastmod['moddateline'] = dgmdate($lastmod['moddateline'], 'u');
		if($modactioncode[$lastmod['modaction']]) {
			$lastmod['modaction'] = $modactioncode[$lastmod['modaction']].($lastmod['modaction'] != 'SPA' ? '' : ' '.$_G['cache']['stamps'][$lastmod['stamp']]['text']);
		} elseif(substr($lastmod['modaction'], 0, 1) == 'L' && preg_match('/L(\d\d)/', $lastmod['modaction'], $a)) {
			$lastmod['modaction'] = $modactioncode['SLA'].' '.$_G['cache']['stamps'][intval($a[1])]['text'];
		} else {
			$lastmod['modaction'] = '';
		}
		if($lastmod['magicid']) {
			loadcache('magics');
			$lastmod['magicname'] = $_G['cache']['magics'][$lastmod['magicid']]['name'];
		}
	} else {
		DB::query("UPDATE ".DB::table($threadtable)." SET moderated='0' WHERE tid='$_G[tid]'", 'UNBUFFERED');
	}
	return $lastmod;
}

function viewthread_parsetags($postlist) {
	global $_G;
	if($_G['forum_firstpid'] && $_G['setting']['tagstatus'] && $_G['forum']['allowtag'] && !($postlist[$_G['forum_firstpid']]['htmlon'] & 2) && !empty($_G['cache']['tags'])) {
		$_G['forum_tagscript'] = '<script type="text/javascript">var tagarray = '.$_G['cache']['tags'][0].';var tagencarray = '.$_G['cache']['tags'][1].';parsetag('.$_G['forum_firstpid'].');</script>';
	}
}

function remaintime($time) {
	$days = intval($time / 86400);
	$time -= $days * 86400;
	$hours = intval($time / 3600);
	$time -= $hours * 3600;
	$minutes = intval($time / 60);
	$time -= $minutes * 60;
	$seconds = $time;
	return array((int)$days, (int)$hours, (int)$minutes, (int)$seconds);
}

?>