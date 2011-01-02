<?php
 /**
 * This file is part of MyBB-Wiki.
 * Copyright (C) 2007-2011 StefanT (http://www.mybbcoder.info)
 * https://github.com/Stefan-ST/MyBB-Wiki
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class wiki
{
	public $root = '';
	public $config = array();
	public $db;
	public $user = array();
	public $lang;
	public $output;
	private $request = '';
	private $post = array();
	private $get = '';
	private $permissions = array();
	private $cache;
	private $input = '';

	public final function __call($function, $arguments)
	{
		if($this->root == '')
		{
			$this->start();
		}
		$do = '___'.$function;
		if(method_exists($this, $do))
		{
			return call_user_func_array(array($this, $do), $arguments);
		}
		else
		{
			global $error_handler;
			$error_handler->trigger('<b><i>Function does not exist: '.$function.'</i></b>', MYBB_GENERAL);
		}
	}

	private final function start()
	{
		global $mybb, $db;
		$this->root = dirname(__FILE__).'/';

		require_once $this->root.'config.php';
		$this->config = new wiki_config;

		$this->db = &$db;

		$this->user = array('uid' => &$mybb->user['uid'],
			'username' => &$mybb->user['username']
			);

		if(file_exists($this->root.'language_'.$mybb->settings['bblanguage'].'.php'))
		{
			require_once $this->root.'language_'.$mybb->settings['bblanguage'].'.php';
		}
		else
		{
			require_once $this->root.'language_english.php';
		}
		$this->lang = new wiki_language;

		require_once $this->root.'output_'.$this->config->output.'.php';
		$this->output = new wiki_output($this);

		$this->request = $mybb->request_method;
		$this->post = $_POST;

		$this->permissions($mybb->user['suspendposting']);
	}

	private final function ___system()
	{
		switch($this->config->input)
		{
			case 'request':
				if($_SERVER['REQUEST_URI'])
				{
					$this->input = $_SERVER['REQUEST_URI'];
				}
				elseif($_SERVER['REDIRECT_URL'])
				{
					$this->input = $_SERVER['REDIRECT_URL'];
				}
				elseif($_SERVER['PATH_INFO'])
				{
					$this->input = $_SERVER['PATH_INFO'];
				}
				else
				{
					$this->input = $_SERVER['PHP_SELF'];
				}
				$this->input = preg_replace('#^(.*?)/wiki/(index\.php/|)#', '', $this->input);
				break;
			case 'query':
			default:
				$this->input = $_SERVER['QUERY_STRING'];
				break;
		}
		if(preg_match("#^({$this->lang->url_new}|{$this->lang->url_popup}|{$this->lang->url_ajax})\?(.*?)$#", $this->input))
		{
			$this->get = preg_replace("#^({$this->lang->url_new}|{$this->lang->url_popup}|{$this->lang->url_ajax})\?#", '', $this->input);
		}
		$this->input = preg_replace('#(\?|&)(.*?)$#', '', $this->input);

		$this->output->breadcrumb($this->lang->wiki, $this->output->url());

		$function = $this->functions();
		if(isset($this->permissions[$function[0]]))
		{
			if($this->permissions[$function[0]] != 1 && $this->permissions[$function[0]] != true)
			{
				error_no_permission();
			}
		}
		$do = '_'.$function[0];
		unset($function[0]);
		if(method_exists($this, $do))
		{
			call_user_func_array(array($this, $do), $function);
		}
		else
		{
			$this->output->error($this->lang->error_article);
		}
	}

	private final function ___discussion($thread)
	{
		return $this->output->discussion($thread);
	}

	private final function ___moderation()
	{
		$this->output->error($this->lang->error_moderation);
	}

	private final function ___forum()
	{
		$this->output->error($this->lang->error_forum);
	}

	private final function permissions($suspended=0)
	{
		$permissions = forum_permissions($this->config->fid);
		if($permissions['canview'] != 1 || $permissions['canviewthreads'] != 1)
		{
			error_no_permission();
		}
		check_forum_password($this->config->fid);

		$this->permissions = array('edit' => $permissions['canpostthreads'],
			'delete' => $permissions['candeletethreads'],
			'protection' => is_moderator($this->config->fid),
			'new' => $permissions['canpostthreads'],
			'deleted' => is_moderator($this->config->fid),
			'protected' => is_moderator($this->config->fid),
			'popup' => $permissions['canpostthreads'],
			'revert' => $permissions['canpostthreads'],
			'upload' => $permissions['canpostattachments']
			);
		if($suspended != 0)
		{
			$this->permissions = array('edit' => false,
				'delete' => false,
				'protection' => false,
				'new' => false,
				'deleted' => false,
				'protected' => false,
				'popup' => false,
				'revert' => false,
				'upload' => false
				);
		}
	}

	private final function functions()
	{
		if($this->input == '')
		{
			return array('article', $this->config->start, '', 1);
		}
		$action = explode('/', $this->input);
		$count = count($action);
		if($count == 1)
		{
			switch($action[0])
			{
				case $this->lang->url_new:
					return array('new');
				case $this->lang->url_changes:
					return array('changes');
				case $this->lang->url_articles:
					return array('articles');
				case $this->lang->url_categories:
					return array('articles', 'categories');
				case $this->lang->url_deleted:
					return array('articles', 'deleted');
				case $this->lang->url_protected:
					return array('articles', 'protected');
				case $this->lang->url_popup:
					return array('popup');
				case $this->lang->url_ajax:
					return array('ajax');
			}
			if(preg_match('/^([^\.]+)\.html$/', $action[0]))
			{
				$action[0] = preg_replace('#\.html$#', '', $action[0]);
				return array('article', $action[0]);
			}
		}
		elseif($count == 2 && preg_match('/^([^\.]+)\.html$/', $action[1]))
		{
			$action[1] = preg_replace('#\.html$#', '', $action[1]);
			switch($action[0])
			{
				case $this->lang->url_edit:
					return array('edit', $action[1]);
				case $this->lang->url_delete:
					return array('delete', $action[1]);
				case $this->lang->url_protection:
					return array('protection', $action[1]);
				case $this->lang->url_versions:
					return array('versions', $action[1]);
			}
		}
		elseif($count == 3 && preg_match('/^([^\.]+)$/', $action[1]))
		{
			$action[1] = preg_replace('#\.html$#', '', $action[1]);
			switch($action[0])
			{
				case $this->lang->url_versions:
					return array('versions', $action[1], $action[2]);
				case $this->lang->url_revert:
					return array('revert', $action[1], $action[2]);
				case $this->lang->url_file:
					return array('file', $action[1], $action[2]);
			}
		}
	}

	private final function cache($tid)
	{
		require_once $this->root.'cache_'.$this->config->cache.'.php';
		$this->cache = new wiki_cache($this, $tid);
	}

	private final function _article($url, $file='', $start=0)
	{
		if($start == 0 && $url == $this->config->start)
		{
			$this->redirect();
		}
		$article = $this->get_article($url, true);
		$this->cache($article['tid']);
		$cache = $this->cache->get();
		if(isset($cache))
		{
			$article['content'] = $cache;
		}
		else
		{
			$query = $this->db->simple_select('posts', 'message', 'pid='.intval($article['firstpost']));
			$article['content'] = $this->db->fetch_field($query, 'message');
			require_once $this->root.'/parser.php';
			$parser = new wiki_parser($this);
			$parser->parse($article['content'], $url, $article['firstpost']);
			$this->cache->insert($article['content']);
		}

		if($start == 0)
		{
			$this->output->breadcrumb($article['subject'], $this->output->url($url));
		}

		$input = array('tid' => $article['tid'],
			'title' => $article['subject'],
			'content' => $article['content'],
			'dateline' => $article['wdateline'],
			'replies' => $article['replies'],
			'categories' => $article['wcategories'],
			'protected' => $article['wprotected'],
			'type' => $article['wtype'],
			'visible' => $article['visible']
			);

		$meta = str_replace(array("\n", "\r"), ' ', html_entity_decode(strip_tags($article['content'])));
		while(strstr($meta, '  ') !== false)
		{
		    $meta = str_replace('  ', ' ', $meta);
		}
		$meta = trim($meta);
		if(my_strlen($meta) > 150)
		{
			$meta = my_substr($meta, 0, 150).'...';
		}
		$this->output->meta .= '<meta name="description" content="'.htmlspecialchars_uni($meta).'" />'."\n";

		$content = $this->parse_article($input, $url);
		$this->output->page($content);
	}

	private final function _file($article, $file)
	{
		global $mybb;
		$article = @$this->get_article($article, true);
		if($article['firstpost'])
		{
			$where = 'pid='.$article['firstpost'];
		}
		else
		{
			$where = 'pid=0 AND uid='.$this->user['uid'];
		}
		$file = urldecode($file);
		$query = $this->db->simple_select('attachments', '*', 'filename=\''.$this->db->escape_string($file).'\' AND '.$where);
		$file = $this->db->fetch_array($query);
		$ext = get_extension($file['filename']);
		
		if($ext == "txt" || $ext == "htm" || $ext == "html" || $ext == "pdf")
		{
			header("Content-disposition: attachment; filename=\"{$file['filename']}\"");
		}
		else
		{
			header("Content-disposition: inline; filename=\"{$file['filename']}\"");
		}	
		
		header("Content-type: {$file['filetype']}");
		echo file_get_contents($mybb->settings['uploadspath']."/".$file['attachname']);
	}

	private final function _new($title='')
	{
		$this->output->breadcrumb($this->lang->new, $this->output->url($this->lang->url_new));
		$content = '';
		if($this->request == 'post')
		{
			if(isset($this->post['upload']))
			{
				if($_FILES['file']['size'] > 0 && $this->permissions['upload'] != false)
				{
					global $forum, $pid;
					$forum = get_forum($this->config->fid);
					$pid = 0;
					require_once MYBB_ROOT."inc/functions_upload.php";
					$file = upload_attachment($_FILES['file']);
					if(isset($file['error']))
					{
						$content .= $this->output->error_array(array($file['error']));
					}
					else
					{
						$this->thumb($_FILES['file'], $file['aid']);
					}
				}
			}
			elseif(isset($this->post['delete']))
			{
				foreach($this->post['delete'] as $ids => $delete)
				{
					$ids = explode(',', $ids);
					require_once MYBB_ROOT."inc/functions_upload.php";
					foreach($ids as $aid)
					{
						remove_attachment(0, $this->post['posthash'], $aid);
					}
				}
			}
			else
			{
				$errors = $this->check_input();
				if($errors === false)
				{
					$cats = array();
					if(isset($this->post['cat']) && is_array($this->post['cat']))
					{
						foreach($this->post['cat'] as $cat => $bit)
						{
							$cats[] = $cat;
						}
					}
					$categories = implode(',', $cats);
					if($this->post['category'])
					{
						$type = 1;
					}
					else
					{
						$type = 0;
					}
					if(isset($this->post['preview']))
					{
						$text = $this->post['text'];
						require_once $this->root.'/parser.php';
						$parser = new wiki_parser($this);
						$parser->parse($text, $this->output->get_url($this->post['title']).'.html', 0, $this->post['posthash']);
						$input = array('tid' => 0,
							'title' => $this->post['title'],
							'content' => $text,
							'dateline' => TIME_NOW,
							'replies' => 0,
							'categories' => $categories,
							'protected' => 0,
							'type' => $type,
							'visible' => 1
							);
	
						$content .= $this->parse_article($input, $this->output->get_url($this->post['title']).'.html');
					}
					else
					{
						require_once MYBB_ROOT.'inc/datahandlers/post.php';
						$posthandler = new PostDataHandler('insert');
						$posthandler->action = 'thread';

						$thread = array('fid' => $this->config->fid,
							'subject' => $this->post['title'],
							'uid' => $this->config->uid,
							'message' => $this->post['text'],
							'ipaddress' => get_ip(),
							'posthash' => $this->post['posthash'],
							'savedraft' => 0,
							'options' => array(
								'signature' => 0,
								'subscriptionmethod' => 0,
								'disablesmilies' => 0
							)
						);
						$posthandler->set_data($thread);
						$valid = $posthandler->validate_thread();
						if($valid)
						{
							$thread_info = $posthandler->insert_thread();
							$files = array();
							$query = $this->db->simple_select('attachments', 'aid', 'visible=1 AND pid='.intval($thread_info['pid']));
							while($file = $this->db->fetch_field($query, 'aid'))
							{
								$files[] = $file;
							}
							$files = implode(',', $files);
							$wid = $this->history($thread_info['tid'], $this->post['title'], $this->post['text'], '', 1, $categories, $files, $type);
							$this->thread($thread_info['tid'], $wid, $this->output->get_url($this->post['title']), 0, $categories, $type);
							$this->cache($thread_info['tid']);
							$cache = $this->cache->update();
							$this->update_url($this->post['title']);
							$this->redirect($this->output->get_url($this->post['title']).'.html');
						}
						else
						{
							$errors = $posthandler->get_friendly_errors();
						}
					}
				}
				else
				{
					$content .= $errors;
				}
			}
		}
		else
		{
			$this->post['title'] = $this->output->return_full_url($this->get);
			$this->post['text'] = '';
			$this->post['category'] = '';
			$this->post['posthash'] = md5($mybb->user['uid'].mt_rand());
			$this->post['key'] = generate_post_check();
		}
		if($this->request != 'post' || $errors !== false || isset($this->post['preview']))
		{
			$files = $categories = '';
			$pre = array('name' => '', 'id' => 0);
			$query = $this->db->simple_select('attachments', '*', "posthash='{$this->post['posthash']}' AND pid=0", array('order_by' => 'aid ASC'));
			while($file = $this->db->fetch_array($query))
			{
				if($file['attachname'] == str_replace('.attach', '.small.attach', $pre['name']))
				{
					$files = str_replace("name=\"delete[{$pre['id']}", "name=\"delete[{$pre['id']},{$file['aid']}", $files);
					$files .= $this->output->input_button('insert_'.$file['aid'], $this->lang->insert_small, '', ' onclick="editor.insertImage('.$file['aid'].');" style="font-weight:bold;"');
				}
				elseif($file['attachname'] == str_replace('.attach', '.big.attach', $pre['name']))
				{
					$files = str_replace("name=\"delete[{$pre['id']}", "name=\"delete[{$pre['id']},{$file['aid']}", $files);
					$files .= $this->output->input_button('insert_'.$file['aid'], $this->lang->insert_big, '', ' onclick="editor.insertImage('.$file['aid'].');"');
				}
				else
				{
					$ext = get_extension($file['filename']);
					if($this->output->is_image($ext))
					{
						$lang = 'insert_full';
						$text = 'Image';
					}
					else
					{
						$lang = 'insert';
						$text = 'File';
					}
					$files .= "<br />\n".$this->output->get_icon(get_extension($file['filename']))
						.' '.$this->output->html($file['filename'])
						.' ('.get_friendly_size($file['filesize']).') '
						.$this->output->input_submit('delete['.$file['aid'].']', $this->lang->delete)
						.$this->output->input_button('insert_'.$file['aid'], $this->lang->$lang, '', ' onclick="editor.insert'.$text.'('.$file['aid'].');"');
					$pre = array('name' => $file['attachname'], 'id' => $file['aid']);
				}
			}
			$query = $this->db->simple_select('threads', 'tid, subject', "wid!=0 AND wtype=1", array('order_by' => 'subject ASC'));
			while($category = $this->db->fetch_array($query))
			{
				$categories .= $this->output->input_checkbox('cat['.$category['tid'].']', $this->post['cat'][$category['tid']], 'cat['.$category['tid'].']').' '.$this->output->html($category['subject'])."<br />\n";
			}
			$content .= $this->output->form_start($this->output->url($this->lang->url_new))
			.$this->output->input_hidden('posthash', $this->post['posthash'])
			.$this->output->input_hidden('key', $this->post['key'])
			.$this->output->table_start().$this->output->table_head_start().$this->lang->new.$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->title
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textbox('title', $this->output->html($this->post['title']))
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->text
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('text', $this->output->html($this->post['text']), 'text')
			.$this->output->editor()
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->category.'?'
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_checkbox('category', $this->post['category'], 'category').' '.$this->lang->yes
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->categories
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$categories
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->files
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_file('file', 'file')
			.$this->output->input_submit('upload', $this->lang->upload)
			.$files
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->submit)
			.$this->output->input_submit('preview', $this->lang->preview)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _edit($url)
	{
		$article = $this->get_article($url);
		if($article['wprotected'] != 0 && !$this->permissions['protection'])
		{
			error_no_permission();
		}
		$this->output->breadcrumb($article['subject'], $this->output->url($url.'.html'));
		$this->output->breadcrumb($this->lang->edit, $this->output->url($this->lang->url_edit));
		$content = '';
		if($this->request == 'post')
		{
			if(isset($this->post['upload']))
			{
				if($_FILES['file']['size'] > 0 && $this->permissions['upload'] != 0)
				{
					global $forum, $pid;
					$forum = get_forum($this->config->fid);
					$pid = $article['firstpost'];
					require_once MYBB_ROOT."inc/functions_upload.php";
					$file = upload_attachment($_FILES['file']);
					if(isset($file['error']))
					{
						$content .= $this->output->error_array(array($file['error']));
					}
					else
					{
						$this->thumb($_FILES['file'], $file['aid']);
					}
				}
			}
			elseif(isset($this->post['delete']))
			{
				$query = $this->db->simple_select('wiki', 'files', 'wid='.intval($article['wid']));
				$files = $this->db->fetch_field($query, 'files');
				foreach($this->post['delete'] as $ids => $delete)
				{
					$ids = explode(',', $ids);
					require_once MYBB_ROOT."inc/functions_upload.php";
					foreach($ids as $aid)
					{
						if(preg_match("#,{$aid},#", ",{$files},"))
						{
							$this->db->update_query('attachments', array('visible' => 0), 'aid='.intval($aid));
							update_thread_counters($article['tid'], array("attachmentcount" => -1));
						}
						else
						{
							remove_attachment($article['firstpost'], '', $aid);
						}
					}
				}
			}
			else
			{
				$errors = $this->check_input($article);
				if($errors === false)
				{
					$cats = array();
					if(isset($this->post['cat']) && is_array($this->post['cat']))
					{
						foreach($this->post['cat'] as $cat => $bit)
						{
							$cats[] = $cat;
						}
					}
					$categories = implode(',', $cats);
					if($this->post['category'])
					{
						$type = 1;
					}
					else
					{
						$type = 0;
					}
					if(isset($this->post['preview']))
					{
						$text = $this->post['text'];
						require_once $this->root.'/parser.php';
						$parser = new wiki_parser($this);
						$parser->parse($text, $this->output->get_url($this->post['title']).'.html', $article['firstpost']);
						$input = array('tid' => $article['tid'],
							'title' => $this->post['title'],
							'content' => $text,
							'dateline' => TIME_NOW,
							'replies' => $article['replies'],
							'categories' => $categories,
							'protected' => $article['wprotected'],
							'type' => $type,
							'visible' => 1
							);
	
						$content .= $this->parse_article($input, $this->output->get_url($this->post['title']).'.html');
					}
					else
					{
						require_once MYBB_ROOT.'inc/datahandlers/post.php';
						$posthandler = new PostDataHandler('update');
						$posthandler->action = 'post';

						$thread = array('pid' => $article['firstpost'],
							'subject' => $this->post['title'],
							'uid' => $this->config->uid,
							'edit_uid' => $this->user['uid'],
							'message' => $this->post['text'],
							'options' => array(
								'signature' => 0,
								'subscriptionmethod' => 0,
								'disablesmilies' => 0
							)
						);
						$posthandler->set_data($thread);
						$valid = $posthandler->validate_thread();
						if($valid)
						{
							$thread_info = $posthandler->update_post();
							$files = array();
							$query = $this->db->simple_select('attachments', 'aid', 'visible=1 AND pid='.intval($article['firstpost']));
							while($file = $this->db->fetch_field($query, 'aid'))
							{
								$files[] = $file;
							}
							$files = implode(',', $files);
							$wid = $this->history($article['tid'], $this->post['title'], $this->post['text'], $this->post['comment'], 2, $categories, $files, $type);
							$this->thread($article['tid'], $wid, $this->output->get_url($this->post['title']), 0, $categories, $type);
							$this->cache($article['tid']);
							$cache = $this->cache->update();
							if($article['subject'] != $this->post['title'])
							{
								$this->update_url($article['subject'], $this->post['title']);
							}
							$this->redirect($this->output->get_url($this->post['title']).'.html');
						}
						else
						{
							$errors = $posthandler->get_friendly_errors();
						}
					}
				}
				else
				{
					$content .= $errors;
				}
			}
		}
		else
		{
			$query = $this->db->simple_select('posts', 'message', 'pid='.intval($article['firstpost']));
			$this->post['text'] = $this->db->fetch_field($query, 'message');
			$this->post['title'] = $article['subject'];
			if($article['wtype'] == 1)
			{
				$this->post['category'] = true;
			}
			else
			{
				$this->post['category'] = '';
			}
			$this->post['posthash'] = md5($mybb->user['uid'].mt_rand());
			$this->post['key'] = generate_post_check();
			$this->post['comment'] = '';
			$cats = explode(',', $article['wcategories']);
			foreach($cats as $cat)
			{
				$this->post['cat'][$cat] = true;
			}
		}
		if($this->request != 'post' || $errors !== false || isset($this->post['preview']))
		{
			$files = $categories = '';
			$pre = array('name' => '', 'id' => 0);
			$query = $this->db->simple_select('attachments', '*', 'visible=1 AND pid='.intval($article['firstpost']), array('order_by' => 'aid ASC'));
			while($file = $this->db->fetch_array($query))
			{
				if($file['attachname'] == str_replace('.attach', '.small.attach', $pre['name']))
				{
					$files = str_replace("name=\"delete[{$pre['id']}", "name=\"delete[{$pre['id']},{$file['aid']}", $files);
					$files .= $this->output->input_button('insert_'.$file['aid'], $this->lang->insert_small, '', ' onclick="editor.insertImage('.$file['aid'].');" style="font-weight:bold;"');
				}
				elseif($file['attachname'] == str_replace('.attach', '.big.attach', $pre['name']))
				{
					$files = str_replace("name=\"delete[{$pre['id']}", "name=\"delete[{$pre['id']},{$file['aid']}", $files);
					$files .= $this->output->input_button('insert_'.$file['aid'], $this->lang->insert_big, '', ' onclick="editor.insertImage('.$file['aid'].');"');
				}
				else
				{
					$ext = get_extension($file['filename']);
					if($this->output->is_image($ext))
					{
						$lang = 'insert_full';
						$text = 'Image';
					}
					else
					{
						$lang = 'insert';
						$text = 'File';
					}
					$files .= "<br />\n".$this->output->get_icon(get_extension($file['filename']))
						.' '.$this->output->html($file['filename'])
						.' ('.get_friendly_size($file['filesize']).') '
						.$this->output->input_submit('delete['.$file['aid'].']', $this->lang->delete)
						.$this->output->input_button('insert_'.$file['aid'], $this->lang->$lang, '', ' onclick="editor.insert'.$text.'('.$file['aid'].');"');
					$pre = array('name' => $file['attachname'], 'id' => $file['aid']);
				}
			}
			$query = $this->db->simple_select('threads', 'tid, subject', 'wid!=0 AND wtype=1', array('order_by' => 'subject ASC'));
			while($category = $this->db->fetch_array($query))
			{
				$categories .= $this->output->input_checkbox('cat['.$category['tid'].']', $this->post['cat'][$category['tid']], 'cat['.$category['tid'].']').' '.$this->output->html($category['subject'])."<br />\n";
			}
			$content .= $this->output->form_start($this->output->url($url.'.html', $this->lang->url_edit))
			.$this->output->input_hidden('posthash', $this->post['posthash'])
			.$this->output->input_hidden('key', $this->post['key'])
			.$this->output->table_start().$this->output->table_head_start().$this->lang->new.$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->title
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textbox('title', $this->output->html($this->post['title']))
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->text
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('text', $this->output->html($this->post['text']), 'text')
			.$this->output->editor()
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->category.'?'
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_checkbox('category', $this->post['category'], 'category').' '.$this->lang->yes
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->categories
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$categories
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->files
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_file('file', 'file')
			.$this->output->input_submit('upload', $this->lang->upload)
			.$files
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->lang->comment
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('comment', $this->output->html($this->post['comment']), 'comment', '', false)
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->submit)
			.$this->output->input_submit('preview', $this->lang->preview)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _popup()
	{
		$this->output->breadcrumb($this->lang->new_link);
		$content = "<script type=\"text/javascript\">\n"
		."<!--\n"
		."	function get_return()\n"
		."	{\n"
		."		if($('title').value == '')\n"
		."		{\n"
		."			return '[wiki='+$('article').value+'#'+$('anchorh').value+']'+$('article').value+'[/wiki]';\n"
		."		}\n"
		."		else\n"
		."		{\n"
		."			return '[wiki='+$('article').value+'#'+$('anchorh').value+']'+$('title').value+'[/wiki]';\n"
		."		}\n"
		."	}\n"
		."// -->\n"
		."</script>\n"
		.$this->output->form_start('#')
		.$this->output->table_start().$this->output->table_head_start().$this->lang->new_link.$this->output->table_head_end()
		.$this->output->table_cat_start()
		.$this->lang->article
		.$this->output->table_cat_end()
		.$this->output->table_row_start()
		.$this->output->input_textbox('article', '', 'article', " onblur=\"new Ajax.Request('".$this->output->url($this->lang->url_ajax.'?anchors&query=\'+$(\'article\').value').", {method: 'get', onComplete: function(request) { $('anchora').innerHTML = request.responseText; }});\"")
		.$this->output->table_row_end()
		.$this->output->table_cat_start()
		.$this->lang->anchor.' ('.$this->lang->optional.')'
		.$this->output->table_cat_end()
		.$this->output->table_row_start()
		.$this->output->input_textbox('anchor', '', 'anchor', " onblur=\"new Ajax.Request('".$this->output->url($this->lang->url_ajax.'?anchor&query=\'+$(\'anchor\').value').", {method: 'get', onComplete: function(request) { $('anchorh').value = request.responseText; }});\"")
		.'<span id="anchora"></span>'
		.$this->output->input_hidden('anchorh', '', 'anchorh')
		.$this->output->table_row_end()
		.$this->output->table_cat_start()
		.$this->lang->title.' ('.$this->lang->optional.')'
		.$this->output->table_cat_end()
		.$this->output->table_row_start()
		.$this->output->input_textbox('title', $this->output->html($this->output->return_full_url($this->get)), 'title')
		.$this->output->table_row_end()
		.$this->output->table_cat_start()
		.$this->output->input_button('close', $this->lang->insert, '', " onclick=\"opener.editor.performInsert(get_return(), '', true, false); window.close();\"")
		.$this->output->table_cat_end()
		.$this->output->table_end()
		."<script type=\"text/javascript\" src=\"{$this->output->js}autocomplete.js?ver={$this->output->ver}\"></script>\n"
		."<script type=\"text/javascript\">\n"
		."<!--\n"
		."	new autoComplete(\"article\", \"".$this->output->url($this->lang->url_ajax.'?articles')."\", {valueSpan: \"article\"});\n"
		."// -->\n"
		."</script>\n"
		.$this->output->form_end();
		$this->output->page($content, true);
	}

	private final function _delete($url)
	{
		if($url == $this->config->start)
		{
			error($this->lang->error_delete_start);
		}
		$article = $this->get_article($url);
		if($article['wprotected'] != 0 && !$this->permissions['protection'])
		{
			error_no_permission();
		}
		$this->output->breadcrumb($article['subject'], $this->output->url($url.'.html'));
		$this->output->breadcrumb($this->lang->delete, $this->output->url($this->lang->url_delete));
		if($this->request == 'post')
		{
			if(!verify_post_check($this->post['key'], true))
			{
				error($this->lang->error_key);
			}
			$string = 'deleted_'.md5(uniqid(rand(), true));
			while($this->db->fetch_field($this->db->simple_select('threads', 'wurl', 'wid!=0 AND wurl=\''.$this->db->escape_string($string).'\''), 'wurl'))
			{
				$string = 'deleted_'.md5(uniqid(rand(), true));
			}
			$query = $this->db->simple_select('wiki', '*', 'wid='.intval($article['wid']));
			$history = $this->db->fetch_array($query);
			require_once MYBB_ROOT."inc/class_moderation.php";
			$moderation = new Moderation;
			$moderation->unapprove_threads($article['tid']);
			$wid = $this->history($article['tid'], $article['subject'], $history['content'], $this->post['comment'], 3, $article['wcategories'], $history['files'], $article['wtype']);
			$this->thread($article['tid'], $wid, $string, $article['wprotected'], $article['wcategories'], $article['wtype']);
			$this->update_url($article['subject']);
			$this->redirect($string.'.html');
		}
		else
		{
			$content = $this->output->form_start($this->output->url($url.'.html', $this->lang->url_delete))
			.$this->output->input_hidden('key', generate_post_check())
			.$this->output->table_start().$this->output->table_head_start().$this->lang->delete.$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->comment
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('comment', $this->output->html($this->post['comment']), 'comment', '', false)
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->submit)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _protection($url)
	{
		$article = $this->get_article($url);
		if($article['wprotected'] == 1)
		{
			$action = 'unprotect';
			$new = 0;
			$id = 5;
		}
		else
		{
			$action = 'protect';
			$new = 1;
			$id = 4;
		}
		$this->output->breadcrumb($article['subject'], $this->output->url($url.'.html'));
		$this->output->breadcrumb($this->lang->$action, $this->output->url($this->lang->url_protection));
		if($this->request == 'post')
		{
			if(!verify_post_check($this->post['key'], true))
			{
				error($this->lang->error_key);
			}
			$query = $this->db->simple_select('wiki', '*', 'wid='.intval($article['wid']));
			$history = $this->db->fetch_array($query);
			$wid = $this->history($article['tid'], $article['subject'], $history['content'], $this->post['comment'], $id, $article['wcategories'], $history['files'], $article['wtype']);
			$this->thread($article['tid'], $wid, $article['wurl'], $new, $article['wcategories'], $article['wtype']);
			$this->redirect($article['wurl'].'.html');
		}
		else
		{
			$content = $this->output->form_start($this->output->url($url.'.html', $this->lang->url_protection))
			.$this->output->input_hidden('key', generate_post_check())
			.$this->output->table_start().$this->output->table_head_start().$this->lang->$action.$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->comment
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('comment', $this->output->html($this->post['comment']), 'comment', '', false)
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->submit)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _revert($url, $version)
	{
		$article = $this->get_article($url, true);
		if($article['wprotected'] != 0 && !$this->permissions['protection'])
		{
			error_no_permission();
		}
		$this->output->breadcrumb($article['subject'], $this->output->url($url.'.html'));
		$this->output->breadcrumb($this->lang->revert, $this->output->url($url.'/'.$version.'.html', $this->lang->url_revert));
		if($this->request == 'post')
		{
			if(!verify_post_check($this->post['key'], true))
			{
				error($this->lang->error_key);
			}
			$query = $this->db->simple_select('wiki', '*', 'tid='.intval($article['tid']).' AND dateline<'.$this->output->return_time($version), array('order_by' => 'dateline DESC', 'limit' => 1));
			$history = $this->db->fetch_array($query);
			if(!isset($history['wid']))
			{
				$this->output->error('');
			}
			if($article['visible'] == 0)
			{
				require_once MYBB_ROOT."inc/class_moderation.php";
				$moderation = new Moderation;
				$moderation->approve_threads($article['tid']);
			}
			require_once MYBB_ROOT.'inc/datahandlers/post.php';
			$posthandler = new PostDataHandler('update');
			$posthandler->action = 'post';

			$thread = array('pid' => $article['firstpost'],
				'subject' => $history['title'],
				'uid' => $this->config->uid,
				'edit_uid' => $this->user['uid'],
				'message' => $history['content'],
				'options' => array(
					'signature' => 0,
					'subscriptionmethod' => 0,
					'disablesmilies' => 0
				)
			);
			$posthandler->set_data($thread);
			$valid = $posthandler->validate_thread();
			if($valid)
			{
				$thread_info = $posthandler->update_post();
				if($article['subject'] != $this->post['title'])
				{
					$this->update_url($article['subject'], $this->post['title']);
				}
				$this->db->update_query('attachments', array('visible' => 0), 'pid='.intval($article['firstpost']));
				$this->db->update_query('attachments', array('visible' => 1), 'pid='.intval($article['firstpost']).' AND aid IN ('.$this->escape_list($history['files']).')');
				update_thread_counters($article['tid'], array("attachmentcount" => count(explode(',', $history['files']))));
				$wid = $this->history($article['tid'], $history['title'], $history['content'], $this->post['comment'], 6, $history['categories'], $history['files'], $history['type'], $history['wid']);
				$this->thread($article['tid'], $wid, $this->output->get_url($history['title']), $article['wprotected'], $history['categories'], $history['type']);
				$this->cache($article['tid']);
				$cache = $this->cache->update();
				$this->update_url($history['title']);
				$this->redirect($this->output->get_url($history['title']).'.html');
			}
			else
			{
				$this->output->error('');
			}
		}
		else
		{
			$content = $this->output->form_start($this->output->url($url.'/'.$version.'.html', $this->lang->url_revert))
			.$this->output->input_hidden('key', generate_post_check())
			.$this->output->table_start().$this->output->table_head_start().$this->lang->delete.$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->comment
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			.$this->output->input_textarea('comment', $this->output->html($this->post['comment']), 'comment', '', false)
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->submit)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _ajax()
	{
		header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		$get = explode('&', $this->get);
		switch($get[0])
		{
			case 'articles':
				$get[1] = str_replace('query=', '', $get[1]);
				if(my_strlen($get[1]) < 3)
				{
					exit;
				}
				$query_options = array(
					"order_by" => "username",
					"order_dir" => "asc",
					"limit_start" => 0,
					"limit" => 15
				);
	
				$query = $this->db->simple_select("threads", "subject", "subject LIKE '".$this->db->escape_string($get[1])."%' AND wid!=0", $query_options);
				while($article = $this->db->fetch_array($query))
				{
					$article['subject'] = htmlspecialchars_uni($article['subject']);
					echo "<div>\n";
					echo "<span class=\"article\">{$article['subject']}</span>\n";
					echo "</div>\n";
				}
				break;
			case 'anchors':
				$get[1] = str_replace('query=', '', $get[1]);
				if(my_strlen($get[1]) < 3)
				{
					exit;
				}
				
				$query = $this->db->query("SELECT t.tid, p.message
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."posts p ON t.firstpost=p.pid
					WHERE t.subject='".$this->db->escape_string($get[1])."' AND t.wid!=0
					");
				$article = $this->db->fetch_array($query);
				if(!$article['tid'])
				{
					exit;
				}
				echo "<ul>\n";
				preg_match_all('#\[h(1|2)\](.*?)\[/h(1|2)\]#si', $article['message'], $titles);
				foreach($titles[2] as $key => $match)
				{
					if($titles[1][$key] == 2)
					{
						$add = '- ';
					}
					else
					{
						$add = '';
					}
					$match = htmlspecialchars_uni($match);
					echo "<li>{$add}<span class=\"article\"><a href=\"javascript:;\" onclick=\"$('anchor').value='{$match}'; $('anchorh').value='".$this->output->get_url($match, 1)."';\">{$match}</a></li>\n";
				}
				echo "</ul>\n";
				break;
			case 'anchor':
				$get[1] = str_replace('query=', '', $get[1]);
				echo $this->output->get_url($get[1], 1);
				break;
		}
	}

	private final function _versions($url, $version='')
	{
		$article = $this->get_article($url, true);
		$this->output->breadcrumb($article['subject'], $this->output->url($url.'.html'));
		$this->output->breadcrumb($this->lang->versions, $this->output->url($url.'.html', $this->lang->url_versions));
		if($this->request == 'post')
		{
			$this->output->breadcrumb($this->lang->differences);
			$query = $this->db->simple_select('wiki', 'dateline, content', 'tid='.intval($article['tid']).' AND dateline='.intval($this->post['old']));
			$old = $this->db->fetch_array($query);
			$query = $this->db->simple_select('wiki', 'dateline, content', 'tid='.intval($article['tid']).' AND dateline='.intval($this->post['new']));
			$new = $this->db->fetch_array($query);

			if($old['content'] == $new['content'])
			{
				error($this->lang->error_no_differences);
			}

			if(!isset($old['dateline']) || !isset($new['dateline']))
			{
				$this->output->error($this->lang->error_article, true);
			}
		
			$old['content'] = explode("\n", $this->output->html($old['content']));
			$new['content'] = explode("\n", $this->output->html($new['content']));

			function wfProfileIn($no)
			{}
			function wfProfileOut($no)
			{}
			require_once $this->root.'diff/DifferenceEngine.php';
			$diffs =& new Diff($old['content'], $new['content']);
			$formatter =& new TableDiffFormatter();
			global $headerinclude;
			$headerinclude .= "<style type=\"text/css\">\ntd.diff-otitle { background:#ffffff; }\ntd.diff-ntitle { background:#ffffff; }\ntd.diff-addedline {\nbackground:#ccffcc;\nfont-size: smaller;\n}\ntd.diff-deletedline {\nbackground:#ffffaa;\nfont-size: smaller;\n}\ntd.diff-context {\nbackground:#eeeeee;\nfont-size: smaller;\n}\n</style>";

			$content = $this->output->table_start().$this->output->table_head_start().$this->output->html($article['subject']).$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->versions
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			."<table class=\"diff\" width=\"100%\"><tr><td colspan=\"2\" style=\"text-align:center;\">{$this->lang->version_of}".$this->dateline($old['dateline'])."</td><td colspan=\"2\" style=\"text-align:center;\">{$this->lang->version_of}".$this->dateline($new['dateline'])."</td></tr>\n".$formatter->format($diffs)."\n</table>"
			.$this->output->table_row_end()
			.$this->output->table_end();
			$this->output->page($content);
		}
		elseif($version != '')
		{
			$query = $this->db->simple_select('wiki', '*', 'tid='.intval($article['tid']).' AND dateline='.$this->output->return_time($version));
			$history = $this->db->fetch_array($query);

			if(!isset($history['wid']))
			{
				$this->output->error($this->lang->error_article, true);
			}

			require_once $this->root.'/parser.php';
			$parser = new wiki_parser($this);
			$parser->parse($history['content'], $url.'.html', $article['firstpost'], '', $history['files']);

			$input = array('tid' => $history['tid'],
				'title' => $history['title'].' ('.$this->lang->version_of.$this->dateline($history['dateline']).')',
				'content' => $history['content'],
				'dateline' => $history['dateline'],
				'replies' => $article['replies'],
				'categories' => $history['categories'],
				'protected' => $history['protected'],
				'type' => $history['type'],
				'visible' => $article['visible']
				);
		
			$this->output->breadcrumb($this->lang->version_of.$this->dateline($history['dateline']), $url.'/'.$version, $this->lang->url_versions);
	
			$content = $this->parse_article($input, $url);
			$this->output->page($content);
		}
		else
		{
			$i = 1;
			$versions = '';
			$query = $this->db->query("SELECT w.*, u.username, u.usergroup, u.displaygroup, r.dateline AS revert
				FROM ".TABLE_PREFIX."wiki w
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.uid)
				LEFT JOIN ".TABLE_PREFIX."wiki r ON (r.wid=w.revert)
				WHERE w.tid=".intval($article['tid'])."
				ORDER BY w.dateline DESC
				");
			while($version = $this->db->fetch_array($query))
			{
				if($article['wprotected'] == 0 && $this->permissions['protection'])
				{
					$revert = ' (<a href="'.$this->output->url($article['wurl'].'/'.$this->output->time_link($version['dateline']).'.html', $this->lang->url_revert).'">'.$this->lang->revert.'</a>)';
				}
				switch($version['action'])
				{
					case 1:
						$action = $this->lang->version_added;
						$revert = '';
						break;
					case 2:
						$action = $this->lang->version_edited;
						break;
					case 3:
						$action = $this->lang->version_deleted;
						break;
					case 4:
						$action = $this->lang->version_protected;
						break;
					case 5:
						$action = $this->lang->version_unprotected;
						break;
					case 6:
						$action = $this->lang->version_reverted.'<a href="'.$this->output->url($article['wurl'].'/'.$this->output->time_link($version['revert']).'.html', $this->lang->url_versions).'">'.$this->dateline($version['revert']).'</a>';
						break;
				}
				if($version['comment'] != '')
				{
					$version['comment'] = ' - <i>'.$this->output->html($version['comment']).'</i>';
				}
				if($i != 1)
				{
					$buttons = $this->output->input_radio('old', $version['dateline']);
				}
				else
				{
					$buttons = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
				}
				$buttons .= $this->output->input_radio('new', $version['dateline']);
				$version['title'] = "{$buttons}<a href=\"".$this->output->url($article['wurl'].'/'.$this->output->time_link($version['dateline']).'.html', $this->lang->url_versions)."\">".$this->dateline($version['dateline'])."</a>";
				$versions .= "<li>{$version['title']} - ".$this->output->user_link($version)." - {$action}{$version['comment']}{$revert}</li>\n";
				$i++;
			}
			$content = $this->output->form_start($this->output->url($url.'.html', $this->lang->url_versions))
			.$this->output->table_start().$this->output->table_head_start().$this->output->html($article['subject']).$this->output->table_head_end()
			.$this->output->table_cat_start()
			.$this->lang->versions
			.$this->output->table_cat_end()
			.$this->output->table_row_start()
			."<ul>\n"
			.$versions
			."</ul>\n"
			.$this->output->table_row_end()
			.$this->output->table_cat_start()
			.$this->output->input_submit('submit', $this->lang->differences)
			.$this->output->table_cat_end()
			.$this->output->table_end()
			.$this->output->form_end();
			$this->output->page($content);
		}
	}

	private final function _changes()
	{
		$this->output->breadcrumb($this->lang->changes, $this->output->url($this->lang->url_changes));
		$versions = '';
		$query = $this->db->query("SELECT w.*, t.wurl, t.subject, t.wprotected, u.username, u.usergroup, u.displaygroup, r.dateline AS revert
			FROM ".TABLE_PREFIX."wiki w
			LEFT JOIN ".TABLE_PREFIX."threads t ON (w.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.uid)
			LEFT JOIN ".TABLE_PREFIX."wiki r ON (r.wid=w.revert)
			ORDER BY w.dateline DESC
			LIMIT 50
			");
		while($version = $this->db->fetch_array($query))
		{
			if($version['wprotected'] == 0 && $this->permissions['protection'])
			{
				$revert = ' (<a href="'.$this->output->url($version['wurl'].'/'.$this->output->time_link($version['dateline']).'.html', $this->lang->url_revert).'">'.$this->lang->revert.'</a>)';
			}
			switch($version['action'])
			{
				case 1:
					$action = $this->lang->version_added;
					$revert = '';
					break;
				case 2:
					$action = $this->lang->version_edited;
					break;
				case 3:
					$action = $this->lang->version_deleted;
					break;
				case 4:
					$action = $this->lang->version_protected;
					break;
				case 5:
					$action = $this->lang->version_unprotected;
					break;
				case 6:
					$action = $this->lang->version_reverted.'<a href="'.$this->output->url($article['wurl'].'/'.$this->output->time_link($version['revert']).'.html', $this->lang->url_versions).'">'.$this->dateline($version['revert']).'</a>';
					break;
			}
			if($version['comment'] != '')
			{
				$version['comment'] = ' - <i>'.$this->output->html($version['comment']).'</i>';
			}
			$version['title'] = "<a href=\"".$this->output->url($version['wurl'].'/'.$this->output->time_link($version['dateline']).'.html', $this->lang->url_versions)."\">".$this->dateline($version['dateline'])."</a>";
			$version['subject'] = "<a href=\"".$this->output->url($version['wurl'].'.html')."\">".$this->output->html($version['subject'])."</a>";
			$versions .= "<li>{$version['title']} - {$version['subject']} - ".$this->output->user_link($version)." - {$action}{$version['comment']}{$revert}</li>\n";
			$i++;
		}
		$content = $this->output->table_start().$this->output->table_head_start().$this->lang->changes.$this->output->table_head_end()
		.$this->output->table_row_start()
		."<ul>\n"
		.$versions
		."</ul>\n"
		.$this->output->table_row_end()
		.$this->output->table_end();
		$this->output->page($content);
	}

	private final function _articles($type='')
	{
		switch($type)
		{
			case '':
				$where = 'visible=1';
				$name = 'articles';
				break;
			case 'categories':
				$where = 'wtype=1';
				$name = 'categories';
				break;
			case 'deleted':
				$where = 'visible=0';
				$name = 'deleted';
				break;
			case 'protected':
				$where = 'wprotected=1';
				$name = 'protected';
				break;
		}
		$url = 'url_'.$name;
		$this->output->breadcrumb($this->lang->$name, $this->output->url($url));
		$articles = '';
		$query = $this->db->simple_select('threads', '*', 'wid!=0 AND '.$where);
		while($article = $this->db->fetch_array($query))
		{
			$articles .= "<li><a href=\"".$this->output->url($article['wurl'].'.html')."\">".$this->output->html($article['subject'])."</a></li>\n";
		}
		$content = $this->output->table_start().$this->output->table_head_start().$this->lang->$name.$this->output->table_head_end()
		.$this->output->table_row_start()
		."<ul>\n"
		.$articles
		."</ul>\n"
		.$this->output->table_row_end()
		.$this->output->table_end();
		$this->output->page($content);
	}

	private final function get_article($url, $visible=false)
	{
		$query = $this->db->simple_select('threads', 'tid, subject, replies, firstpost, visible, wid, wurl, wdateline, wcategories, wprotected, wtype', 'wurl=\''.$this->db->escape_string($url).'\' AND wid!=0');
		$article = $this->db->fetch_array($query);

		if(!$article['tid'])
		{
			$query = $this->db->simple_select('wiki', 'tid', 'url=\''.$this->db->escape_string($url).'\'', array('order_by' => 'dateline DESC', 'limit' => 1));
			$tid = $this->db->fetch_field($query, 'tid');
			$query = $this->db->simple_select('threads', 'tid, subject, replies, firstpost, visible, wid, wurl, wdateline, wcategories, wprotected, wtype', 'tid='.intval($tid).' AND wid!=0');
			$article = $this->db->fetch_array($query);
		}

		if(!$article['tid'] || ($article['visible'] == 0 && $visible == false && $this->permission['deleted']))
		{
			$this->output->error($this->lang->error_article, true);
		}
		if(isset($tid))
		{
			$this->redirect($article['wurl'].'.html');
		}
		if($url == $this->config->start)
		{
			$link = '';
		}
		else
		{
			$link = $url.'.html';
		}
		$this->output->canonical = "<link rel=\"canonical\" href=\"".$this->output->url($link)."\" />\n";
		return $article;
	}

	private final function parse_article($article, $url)
	{
		$actions = array();
		if($article['visible'] == 1)
		{
			if($this->permissions['edit'] && ($article['protected'] == 0 || $this->permissions['protection']))
			{
				$actions[] = $this->output->button($url.'.html', 'edit', $this->lang->url_edit);
			}
			if($this->permissions['delete'])
			{
				$actions[] = $this->output->button($url.'.html', 'delete', $this->lang->url_delete);
			}
			if($this->permissions['protection'] && $article['protected'] == 0)
			{
				$actions[] = $this->output->button($url.'.html', 'protect', $this->lang->url_protection);
			}
			if($this->permissions['protection'] && $article['protected'] == 1)
			{
				$actions[] = $this->output->button($url.'.html', 'unprotect', $this->lang->url_protection);
			}
			$actions[] = $this->output->button($url.'.html', 'versions', $this->lang->url_versions);
		}
		else
		{
			$actions[] = $this->output->button($url.'.html', 'revert', $this->lang->url_versions);
		}
		$actions = implode(' - ', $actions);

		$buttons = array();
		if($this->permissions['new'])
		{
			$buttons[] = $this->output->button($this->lang->url_new, 'new');
		}
		$buttons[] = $this->output->button($this->lang->url_changes, 'changes');
		$buttons[] = $this->output->button($this->lang->url_articles, 'articles');
		$buttons[] = $this->output->button($this->lang->url_categories, 'categories');
		if($this->permissions['deleted'])
		{
			$buttons[] = $this->output->button($this->lang->url_deleted, 'deleted');
		}
		if($this->permissions['protected'])
		{
			$buttons[] = $this->output->button($this->lang->url_protected, 'protected');
		}
		$buttons = implode(' - ', $buttons);

		$content = $this->output->table_start()
		.$this->output->table_head_start()
		."<span style=\"float:right\">"
		."<a href=\"".$this->output->thread_link($article['tid'])."\">".$this->lang->discussion." ({$article['replies']})</a>"
		."</span>"
		.$this->output->html($article['title'])
		.$this->output->table_head_end()
		.$article['content']
		.$this->output->table_end();
		if($article['categories'] != '' && $article['categories'] != 0)
		{
			$cat = array();
			$query = $this->db->simple_select('threads', 'subject, wurl', 'wtype=1 AND visible=1 AND tid IN ('.$this->escape_list($article['categories']).')');
			while($category = $this->db->fetch_array($query))
			{
				$cat[] = "<a href=\"".$this->output->url($category['wurl'].'.html')."\">{$category['subject']}</a>";
			}
			$cat = implode(' - ', $cat);
			if($cat != '')
			{
				$content .= $this->output->table_start()
					.$this->output->table_head_start()
					.$this->lang->categories
					.$this->output->table_head_end()
					.$this->output->table_row_start()
					.$cat
					.$this->output->table_row_end()
					.$this->output->table_end();
			}
		}
		if($article['type'] == 1 && $article['tid'] != 0)
		{
			$cat = array();
			$query = $this->db->simple_select('threads', 'subject, wurl', 'visible=1 AND CONCAT(\',\', wcategories, \',\') LIKE \'%,'.intval($article['tid']).',%\'');
			while($category = $this->db->fetch_array($query))
			{
				$cat[] = "<li><a href=\"".$this->output->url($category['wurl'].'.html')."\">{$category['subject']}</a></li>\n";
			}
			$cat = implode('', $cat);
			if($cat != '')
			{
				$content .= $this->output->table_start()
					.$this->output->table_head_start()
					.$this->lang->articles_category
					.$this->output->table_head_end()
					.$this->output->table_row_start()
					."<ul>\n"
					.$cat
					."</ul>\n"
					.$this->output->table_row_end()
					.$this->output->table_end();
			}
		}
		$content .= $this->output->table_start()
		.$this->output->table_head_start()
		.$this->lang->actions
		.$this->output->table_head_end()
		.$this->output->table_row_start()
		.$this->output->table_right_start()
		.$actions
		.$this->output->table_right_end()
		.$this->lang->last_change.': '.$this->dateline($article['dateline'])."<br />\n"
		.$buttons
		.$this->output->table_row_end()
		.$this->output->table_end();
		return $content;
	}

	private final function dateline($dateline)
	{
		global $mybb;
		return my_date($mybb->settings['dateformat'], $dateline, '', false).', '.my_date($mybb->settings['timeformat'], $dateline);
	}

	public final function escape_list($input)
	{
		$array = explode(',', $input);
		foreach($array as $bit)
		{
			$list[] = intval($bit);
		}
		return implode(',', $list);
	}

	private final function redirect($url='')
	{
		header("HTTP/1.0 301 Moved Permanently");
		header("Location: ".$this->output->url($url));
		exit;
	}

	private final function check_input($article=array('subject' => '', 'wurl' => ''))
	{
		$errors = array();
		if(!verify_post_check($this->post['key'], true))
		{
			$errors[] = $this->lang->error_key;
		}
		if(!isset($this->post['title']) || $this->post['title'] == '')
		{
			$errors[] = $this->lang->error_no_title;
		}
		$query = $this->db->simple_select('threads', 'subject', 'wid!=0 AND visible=1 AND subject=\''.$this->db->escape_string($this->post['title']).'\'');
		$subject = $this->db->fetch_field($query, 'subject');
		if(isset($subject) && $article['subject'] != $subject)
		{
			$errors[] = $this->lang->error_title_exists;
		}
		$query = $this->db->simple_select('threads', 'wurl', 'wurl=\''.$this->db->escape_string($this->output->get_url($this->post['title'])).'\'');
		$url = $this->db->fetch_field($query, 'wurl');
		if(isset($url) && $article['wurl'] != $url)
		{
			$errors[] = $this->lang->error_url_exists;
		}
		if(!isset($this->post['text']) || $this->post['text'] == '')
		{
			$errors[] = $this->lang->error_no_text;
		}
		return $this->output->error_array($errors);
	}

	private final function thumb($file, $aid)
	{
		global $mybb;
		$ext = get_extension($file['name']);
		if($this->output->is_image($ext))
		{
			$query = $this->db->simple_select('attachments', '*', 'aid='.intval($aid));
			$info = $this->db->fetch_array($query);

			$img_dimensions = @getimagesize($mybb->settings['uploadspath']."/".$info['attachname']);
			require_once MYBB_ROOT."inc/functions_image.php";

			unset($info['aid']);

			$thumbname = str_replace('.attach', '.big.attach', $info['attachname']);
			$thumbnail = generate_thumbnail($mybb->settings['uploadspath']."/".$info['attachname'], $mybb->settings['uploadspath'], $thumbname, $this->config->thumb['big']['height'], $this->config->thumb['big']['width']);
			if($thumbnail['filename'])
			{
				$attacharray['thumbnail'] = $thumbnail['filename'];
				$insert = $info;
				$insert['filename'] = str_replace(".{$ext}", ".big.$ext", $insert['filename']);
				$insert['attachname'] = $thumbname;
				$this->db->insert_query('attachments', $insert);
			}

			$thumbname = str_replace('.attach', '.small.attach', $info['attachname']);
			$thumbnail = generate_thumbnail($mybb->settings['uploadspath']."/".$info['attachname'], $mybb->settings['uploadspath'], $thumbname, $this->config->thumb['small']['height'], $this->config->thumb['small']['width']);
			if($thumbnail['filename'])
			{
				$attacharray['thumbnail'] = $thumbnail['filename'];
				$insert = $info;
				$insert['filename'] = str_replace(".{$ext}", ".small.$ext", $insert['filename']);
				$insert['attachname'] = $thumbname;
				$this->db->insert_query('attachments', $insert);
			}
		}
	}

	private final function thread($tid, $wid, $url, $protected, $categories, $type)
	{
		$update = array('wid' => intval($wid),
			'wurl' => $this->db->escape_string($url),
			'wdateline' => TIME_NOW,
			'wprotected' => $this->user['uid'],
			'wprotected' => intval($protected),
			'wcategories' => $this->escape_list($categories),
			'wtype' => intval($type)
			);
		$this->db->update_query('threads', $update, 'tid='.$tid);
	}

	private final function history($tid, $title, $content, $comment, $action, $categories, $files, $type, $revert=0)
	{
		$insert = array('tid' => intval($tid),
			'url' => $this->db->escape_string($this->output->get_url($title), 0),
			'title' => $this->db->escape_string($title),
			'content' => $this->db->escape_string($content),
			'comment' => $this->db->escape_string($comment),
			'dateline' => TIME_NOW,
			'uid' => $this->user['uid'],
			'action' => intval($action),
			'revert' => intval($revert),
			'categories' => $this->escape_list($categories),
			'files' => $this->escape_list($files),
			'type' => intval($type)
			);
		return $this->db->insert_query('wiki', $insert);
	}

	private final function update_url($old, $new='')
	{
		$array = $where = $cache = array();
		$query = $this->db->simple_select('wiki', 'wid, tid, content', 'content LIKE \'%[wiki='.$this->db->escape_string($old).'#%]%\'');
		while($article = $this->db->fetch_array($query))
		{
			if($new != '')
			{
				$article['content'] = preg_replace("#\[wiki={$old}\#(.*?)\](.*?)\[/wiki\]#si", "[wiki={$new}#$1]$2[/wiki]", $article['content']);
				$this->db->update_query('wiki', array('content' => $this->db->escape_string($article['content'])), 'wid='.intval($article['wid']));
			}
			$array[$article['tid']] = $article['wid'];
			$cache[$article['tid']] = $article['content'];
		}
		foreach($array as $tid => $wid)
		{
			$where[] = "(tid={$tid} AND wid={$wid})";
		}
		$where = implode(' OR ', $where);
		if($where != '')
		{
			$query = $this->db->simple_select('threads', 'tid, firstpost', $where);
			while($thread = $this->db->fetch_array($query))
			{
				$this->db->update_query('posts', array('message' => $this->db->escape_string($cache[$thread['tid']])), 'pid='.intval($thread['firstpost']));
				$this->cache($thread['tid']);
				$this->cache->update();
			}
		}
	}
}
?>
