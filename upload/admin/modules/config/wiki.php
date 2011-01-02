<?php
 /**
 * This file is part of MyBB-Wiki.
 * Copyright (C) 2008-2011 StefanT (http://www.mybbcoder.info)
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

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
if(!is_super_admin($mybb->user['uid']))
{
	flash_message('No Permission!', 'error');
	admin_redirect("index.php?module=config");
}
if($mybb->request_method == 'post')
{
	if(!is_writable(MYBB_ROOT.'inc/wiki/config.php'))
	{
		flash_message('inc/wiki/config.php is not writable', 'error');
		admin_redirect("index.php?module=config/wiki");
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
	public \$start = '".addslashes($mybb->input['start'])."';
	public \$cache = 'db';
	public \$output = 'theme';
	public \$input = '".addslashes($mybb->input['input'])."';
	public \$fid = ".intval($mybb->input['fid']).";
	public \$uid = ".intval($mybb->input['uid']).";
	public \$root = '".addslashes($mybb->input['root'])."';
	public \$thumb = array('small' => array('width' => ".intval($mybb->input['stw']).",
			'height' => ".intval($mybb->input['sth'])."),
			'big' => array('width' => ".intval($mybb->input['btw']).",
			'height' => ".intval($mybb->input['bth']).")
		);
}
?>"))
	{
		flash_message('Cannot save settings', 'error');
		admin_redirect("index.php?module=config/wiki");
	}
	fclose($file);
	flash_message('Done', 'success');
	admin_redirect("index.php?module=config/wiki");
}
else
{
	require MYBB_ROOT.'inc/wiki/config.php';
	$wiki_config = new wiki_config;
	$page->add_breadcrumb_item('Wiki', "index.php?module=config/wiki");
	$page->output_header('Wiki Settings');
	$form = new Form("index.php?module=config/wiki", "post");
	$form_container = new FormContainer('Wiki Installation');
	$form_container->output_row("Forum-ID for Diskussions <em>*</em>", '', $form->generate_text_box('fid', $wiki_config->fid), '');
	$form_container->output_row("User-ID for Diskussions <em>*</em>", '', $form->generate_text_box('uid', $wiki_config->uid), '');
	$form_container->output_row("Start Page <em>*</em>", 'Must exists!', $form->generate_text_box('start', $wiki_config->start), '');
	$form_container->output_row("Small Thumbnail Height <em>*</em>", '', $form->generate_text_box('sth', $wiki_config->thumb['small']['height']), '');
	$form_container->output_row("Small Thumbnail Width <em>*</em>", '', $form->generate_text_box('stw', $wiki_config->thumb['small']['width']), '');
	$form_container->output_row("Big Thumbnail Height <em>*</em>", '', $form->generate_text_box('bth', $wiki_config->thumb['big']['height']), '');
	$form_container->output_row("Big Thumbnail Width <em>*</em>", '', $form->generate_text_box('btw', $wiki_config->thumb['big']['width']), '');
	$form_container->output_row("URL Type<em>*</em>", '"HTTP REQUEST STRING" for rewrite', $form->generate_select_box('input', array('request' => 'HTTP REQUEST STRING', 'query' => 'QUERY STRING'), $wiki_config->input), '');
	$form_container->output_row("URL <em>*</em>", 'Must match with setting above', $form->generate_text_box('root', $wiki_config->root), '');
	$form_container->end();
	$form->output_submit_wrapper(array($form->generate_submit_button('Install')));
	$form->end();
	echo "Powered by <a href=\"http://www.mybbcoder.info\" target=\"_blank\">MyBB-Wiki</a>, &copy; 2006-{$copy_year} <a href=\"http://www.mybbcoder.info\" target=\"_blank\">StefanT</a>";
	$page->output_footer();
}
?>
