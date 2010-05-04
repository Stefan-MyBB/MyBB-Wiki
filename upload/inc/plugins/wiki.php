<?php
/*
	Wiki-System
	(c) 2008-2010 by StefanT
	http://www.mybbcoder.info
*/
if(!version_compare(PHP_VERSION, '5.0.0', '<') && !defined('MYBB_ADMIN_DIR'))
{
	require_once MYBB_ROOT.'inc/wiki/class.php';
	global $wiki;
	$wiki = new wiki;

	$plugins->add_hook('postbit', 'wiki_postbit');
	$plugins->add_hook('moderation_deletethread', 'wiki_moderation');
	$plugins->add_hook('moderation_do_deletethread', 'wiki_moderation');
	$plugins->add_hook('moderation_approvethread', 'wiki_moderation');
	$plugins->add_hook('moderation_unapprovethread', 'wiki_moderation');
	$plugins->add_hook('moderation_move', 'wiki_moderation');
	$plugins->add_hook('moderation_merge', 'wiki_moderation');
	$plugins->add_hook('moderation_do_merge', 'wiki_moderation_merge');
	$plugins->add_hook('moderation_do_deleteposts', 'wiki_moderation_deleteposts');
	$plugins->add_hook('moderation_do_mergeposts', 'wiki_moderation_mergeposts');
	$plugins->add_hook('class_moderation_delete_thread_start', 'wiki_moderation_tid');
	$plugins->add_hook('class_moderation_approve_threads', 'wiki_moderation_tid');
	$plugins->add_hook('class_moderation_unapprove_threads', 'wiki_moderation_tid');
	$plugins->add_hook('class_moderation_delete_post_start', 'wiki_moderation_pid');
	$plugins->add_hook('class_moderation_move_thread_redirect', 'wiki_moderation_arguments');
	$plugins->add_hook('class_moderation_copy_thread', 'wiki_moderation_arguments');
	$plugins->add_hook('class_moderation_move_simple', 'wiki_moderation_arguments');
	$plugins->add_hook('newthread_start', 'wiki_newthread');
	$plugins->add_hook('admin_config_menu', 'wiki_admin_menu');
	$plugins->add_hook('admin_config_action_handler', 'wiki_admin_action');
}

function wiki_info()
{
	return array(
		'name' => 'Wiki',
		'description' => '',
		'website' => 'http://www.mybbcoder.info',
		'author' => 'Dragon',
		'authorsite' => 'http://www.mybbcoder.info',
		'version' => 'Beta 3',
		'guid' => '488cabefed827be04784a6256710633d',
		'compatibility' => '14*'
	);
}

function wiki_install()
{
	global $mybb, $db, $page;
	if(version_compare(PHP_VERSION, '5.0.0', '<'))
	{
		flash_message('Wiki can not start, because it requires PHP 5.', 'error');
		admin_redirect("index.php?module=config/plugins");
	}
	if(!is_writable(MYBB_ROOT.'inc/wiki/config.php'))
	{
		flash_message('inc/wiki/config.php is not writable', 'error');
		admin_redirect("index.php?module=config/plugins");
	}
	if(!isset($mybb->input['fid']) || !isset($mybb->input['uid']))
	{
		$page->output_header('Wiki Installation');
		$form = new Form("index.php?module=config/plugins&amp;action=activate&amp;plugin=wiki", "post");
		$form_container = new FormContainer('Wiki Installation');
		$form_container->output_row("Forum-ID for Discussions <em>*</em>", '', $form->generate_text_box('fid', ''), '');
		$form_container->output_row("User-ID for Discussions <em>*</em>", '', $form->generate_text_box('uid', ''), '');
		$form_container->end();
		$form->output_submit_wrapper(array($form->generate_submit_button('Install')));
		$form->end();
		echo "Powered by <a href=\"http://www.mybbcoder.info\" target=\"_blank\">MyBB-Wiki</a>, &copy; 2006-{$copy_year} <a href=\"http://www.mybbcoder.info\" target=\"_blank\">StefanT</a>";
		$page->output_footer();
		exit;
	}
	$file = fopen(MYBB_ROOT.'inc/wiki/config.php', 'w');
	if(!fwrite($file, "<?php
/*
	Wiki-System
	(c) 2008-2010 by StefanT
	http://www.mybbcoder.info
*/
class wiki_config
{
	public \$start = 'start.html';
	public \$cache = 'db';
	public \$output = 'theme';
	public \$input = 'query';
	public \$fid = ".intval($fid).";
	public \$uid = ".intval($uid).";
	public \$root = '/wiki.php?';
	public \$thumb = array('small' => array('width' => 200,
			'height' => 200),
			'big' => array('width' => 800,
			'height' => 800)
		);
}
?>"))
	{
		flash_message('Plugin installation failed', 'error');
		admin_redirect("index.php?module=config/plugins");
	}
	fclose($file);
	$fid = intval($mybb->input['fid']);
	$uid = intval($mybb->input['uid']);
	$db->write_query("CREATE TABLE `".TABLE_PREFIX."wiki` (
	`wid` int(10) unsigned NOT NULL auto_increment,
	`tid` int(10) unsigned NOT NULL,
	`url` varchar(120) NOT NULL,
	`title` varchar(120) NOT NULL,
	`content` text NOT NULL,
	`comment` text NOT NULL,
	`dateline` int(10) NOT NULL,
	`uid` int(10) unsigned NOT NULL,
	`action` smallint(1) NOT NULL default '0',
	`revert` int(10) unsigned NOT NULL,
	`categories` varchar(100) NOT NULL,
	`files` varchar(100) NOT NULL,
	`type` smallint(1) NOT NULL,
	PRIMARY KEY (`wid`),
	KEY `tid` (`tid`),
	KEY `uid` (`uid`),
	KEY `revert` (`revert`),
	KEY `categories` (`categories`),
	KEY `url` (`url`)
	) ENGINE=MyISAM;");

	$db->write_query("CREATE TABLE `".TABLE_PREFIX."wiki_cache` (
	`tid` int(10) unsigned NOT NULL,
	`content` text NOT NULL,
	`active` smallint(1) NOT NULL default '0',
	PRIMARY KEY (`tid`)
	) ENGINE=MyISAM;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wid int(10) unsigned NOT NULL");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wurl varchar(120) NOT NULL");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wdateline bigint(10) NOT NULL");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wprotected smallint(1) NOT NULL default '0'");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wcategories varchar(25) NOT NULL");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD wtype smallint(1) NOT NULL default '0'");

	require_once MYBB_ROOT.'inc/datahandlers/post.php';
	$posthandler = new PostDataHandler('insert');
	$posthandler->action = 'thread';

	$thread = array('fid' => $fid,
		'subject' => 'Start',
		'uid' => $uid,
		'message' => 'You can add your content here!',
		'ipaddress' => get_ip(),
		'posthash' => '',
		'savedraft' => 0,
		'options' => array(
			'signature' => 0,
			'subscriptionmethod' => 0,
			'disablesmilies' => 0
		)
	);
	$posthandler->set_data($thread);
	$valid = $posthandler->validate_thread();
	$thread_info = $posthandler->insert_thread();
	$db->write_query("INSERT INTO `".TABLE_PREFIX."wiki` VALUES (1, {$thread_info['tid']}, 'start', 'Start', 'You can add your content here!', '', ".time().", {$uid}, 1, 0, '0', '0', 0);");
	$db->update_query('threads', array('wid' => 1, 'wurl' => 'start', 'wdateline' => time()), 'tid='.$thread_info['tid']);
}

function wiki_uninstall()
{
	global $db;
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wid");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wurl");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wdateline");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wprotected");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wcategories");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP wtype");
	$db->drop_table('wiki');
	$db->drop_table('wiki_cache');
}

function wiki_activate()
{
	if(version_compare(PHP_VERSION, '5.0.0', '<'))
	{
		flash_message('Wiki can not start, because it requires PHP 5.', 'error');
		admin_redirect("index.php?module=config/plugins");
	}
}

function wiki_deactivate()
{
}

function wiki_is_installed()
{
	global $db;
	if($db->table_exists('wiki'))
	{
		return true;
	}
	return false;
}

function wiki_postbit(&$post)
{
	global $mybb, $thread, $wiki;
	if($thread['firstpost'] == $post['pid'] && $thread['wid'] != 0)
	{
		$post['message'] = $wiki->discussion($thread);
		$post['attachments']
		= $post['button_edit']
		= $post['button_quickdelete']
		= $post['button_quote']
		= $post['button_multiquote']
		= $post['button_report']
		= $post['button_warn']
		= '';
	}
}

function wiki_moderation()
{
	global $thread, $wiki;
	if($thread['wid'] != 0)
	{
		$wiki->moderation();
	}
}

function wiki_moderation_deleteposts()
{
	global $mybb, $thread, $wiki;
	if($mybb->input['deletepost'][$thread['firstpost']] == 1)
	{
		$wiki->moderation();
	}
}

function wiki_moderation_mergeposts()
{
	global $mybb, $thread, $wiki;
	if($mybb->input['mergepost'][$thread['firstpost']])
	{
		$wiki->moderation();
	}
}

function wiki_moderation_merge()
{
	global $mybb, $db, $thread, $wiki;

	if($thread['wid'] != 0)
	{
		$wiki->moderation();
	}
	
	// explode at # sign in a url (indicates a name reference) and reassign to the url
	$realurl = explode("#", $mybb->input['threadurl']);
	$mybb->input['threadurl'] = $realurl[0];
	
	// Are we using an SEO URL?
	if(substr($mybb->input['threadurl'], -4) == "html")
	{
		// Get thread to merge's tid the SEO way
		preg_match("#thread-([0-9]+)?#i", $mybb->input['threadurl'], $threadmatch);
		preg_match("#post-([0-9]+)?#i", $mybb->input['threadurl'], $postmatch);
		
		if($threadmatch[1])
		{
			$parameters['tid'] = $threadmatch[1];
		}
		
		if($postmatch[1])
		{
			$parameters['pid'] = $postmatch[1];
		}
	}
	else
	{
		// Get thread to merge's tid the normal way
		$splitloc = explode(".php", $mybb->input['threadurl']);
		$temp = explode("&", my_substr($splitloc[1], 1));

		if(!empty($temp))
		{
			for($i = 0; $i < count($temp); $i++)
			{
				$temp2 = explode("=", $temp[$i], 2);
				$parameters[$temp2[0]] = $temp2[1];
			}
		}
		else
		{
			$temp2 = explode("=", $splitloc[1], 2);
			$parameters[$temp2[0]] = $temp2[1];
		}
	}
	
	if($parameters['pid'] && !$parameters['tid'])
	{
		$query = $db->simple_select("posts", "*", "pid='".intval($parameters['pid'])."'");
		$post = $db->fetch_array($query);
		$mergetid = $post['tid'];
	}
	elseif($parameters['tid'])
	{
		$mergetid = $parameters['tid'];
	}
	$mergetid = intval($mergetid);
	$query = $db->simple_select("threads", "*", "tid='".intval($mergetid)."'");
	$mergethread = $db->fetch_array($query);
	if($mergethread['wid'] != 0)
	{
		$wiki->moderation();
	}
}

function wiki_moderation_tid($tids)
{
	global $wiki;
	if(!is_array($tids))
	{
		$tids = array($tids);
	}
	foreach($tids as $tid)
	{
		$thread = get_thread($tid);
		if($thread['wid'] != 0)
		{
			$wiki->moderation();
		}
	}
}

function wiki_moderation_pid($pid)
{
	global $wiki;
	$post = get_post($pid);
	$thread = get_thread($post['tid']);
	if($thread['wid'] != 0 && $post['pid'] == $thread['firstpost'])
	{
		$wiki->moderation();
	}
}

function wiki_moderation_arguments($arguments)
{
	global $wiki;
	$thread = get_thread($arguments['tid']);
	if($thread['wid'] != 0)
	{
		$wiki->moderation();
	}
}

function wiki_newthread()
{
	global $forum, $wiki;
	if($forum['fid'] == $wiki->config->fid)
	{
		$wiki->forum();
	}
}

function wiki_admin_menu(&$sub_menu)
{
	$sub_menu[] = array("id" => "wiki", "title" => 'Wiki', "link" => "index.php?module=config/wiki");
}

function wiki_admin_action(&$actions)
{
	$actions['wiki'] = array('active' => 'wiki', 'file' => 'wiki.php');
}

?>
