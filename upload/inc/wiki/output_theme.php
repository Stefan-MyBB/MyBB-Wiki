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
class wiki_output
{
	private $core;
	private $root;
	private $theme;
	private $breadcrumb = array();
	private $img = '';
	public $js;
	public $ver = 002;
	private $cache = array();
	public $meta = '';
	public $canonical = '';

	public final function __construct(&$core)
	{
		global $mybb, $theme;
		$this->core = &$core;
		$this->root = $mybb->settings['bburl'].$core->config->root;
		$this->img = $mybb->settings['bburl'].'/images/wiki/';
		$this->js = $mybb->settings['bburl'].'/jscripts/';
		$this->theme = &$theme;
	}

	public final function page($content, $popup=false)
	{
		global $mybb, $headerinclude, $header, $footer, $theme, $lang, $copy_year;
		if($popup == true)
		{
			$header = $footer = '';
		}
		foreach($this->breadcrumb as $bit)
		{
			$title .= ' - '.$bit;
		}
		$output = "<html>\n"
		."<head>\n"
		."<title>{$mybb->settings['bbname']}{$title}</title>\n"
		."{$headerinclude}\n"
		.$this->canonical
		.$this->meta
		."<style type=\"text/css\">\n"
		.".wikinew, .wikinew:link, .wikinew:visited {\n"
		."\tcolor: #934900;\n"
		."}\n"
		.".wikiimage {\n"
		."\tborder: 1px solid #999999;\n"
		."\tbackground: #f5f5f5;\n"
		."\tclear: both;\n"
		."\tfloat: right;\n"
		."\tpadding: 3px;\n"
		."\tmargin: 3px;\n"
		."}\n"
		."</style>\n"
		."</head>\n"
		."<body>\n"
		."{$header}\n"
		."{$content}\n"
		."<div class=\"smalltext\">Powered by <a href=\"http://www.mybbcoder.info\" target=\"_blank\">MyBB-Wiki</a>, "
		."&copy; 2006-{$copy_year} <a href=\"http://www.mybbcoder.info\" target=\"_blank\">Dragon</a> - <a href=\"http://www.famfamfam.com/lab/icons/silk/\">Icons</a></div>\n"
		."{$footer}\n"
		."</body>\n"
		."</html>";
		output_page($output);
	}

	public final function error($error, $header=true)
	{
		global $lang;
		if(!error_reporting())
		{
			return;
		}
		if(isset($lang->$error))
		{
			$error = $lang->error;
		}
		if($header == true)
		{
			header("HTTP/1.0 404 Not Found");
		}
		error($error);
	}

	public final function error_array($errors)
	{
		if(count($errors) != 0)
		{
			return inline_error($errors);
		}
		return false;
	}

	public final function breadcrumb($title, $url='')
	{
		add_breadcrumb($title, $url);
		$this->breadcrumb[] = $title;
	}

	public final function get_url($url, $ref=0)
	{
		$url = my_strtolower($url);
		$url = str_replace(array(' ', '/', '.', ':', '&', '_'), '-', $url);
		$url = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $url);
		$url = preg_replace('/([^a-zA-Z-]+)/', '', $url);
		while(strstr($url, '--') !== false)
		{
		    $url = str_replace('--', '-', $url);
		}
		if($ref == 1)
		{
			if(isset($this->cache[$url]))
			{
				$this->cache[$url]++;
				$url .= $this->cache[$url];
			}
			$this->cache[$url] = 0;
		}
		return $url;
	}

	public final function get_full_url($url)
	{
		return urlencode($url);
	}

	public final function return_full_url($url)
	{
		return urldecode($url);
	}

	public final function url($url='', $action='')
	{
		if($action != '')
		{
			$action .= '/';
		}
		return $this->root.$action.$url;
	}

	public final function link($title, $text, $anchor)
	{
		if($anchor != '')
		{
			$anchor = "#{$anchor}";
		}
		return "<a href=\"".$this->url($title.'.html')."{$anchor}\">{$text}</a>";
	}

	public final function link_new($title, $text='')
	{
		if($text == '')
		{
			$text = $title;
		}
		return "<a href=\"".$this->url($this->core->lang->url_new.'?'.$this->get_full_url($title))."\" class=\"wikinew\">{$text}</a>";
	}

	public final function thread_link($tid)
	{
		global $mybb;
		return $mybb->settings['bburl'].'/'.get_thread_link($tid);
	}

	public final function user_link($user)
	{
		global $mybb;
		return "<a href=\"{$mybb->settings['bburl']}/".get_profile_link($user['uid'])."\">".format_name($user['username'], $user['usergroup'], $user['displaygroup'])."</a>";
	}

	public final function time_link($time)
	{
		return date('Y.m.d_H:i:s', $time);
	}

	public final function return_time($dateline)
	{
		$dateline = explode('_', str_replace('.html', '', $dateline));
		$time = explode(':', $dateline[1]);
		$date = explode('.', $dateline[0]);
		return intval(@mktime($time[0], $time[1], $time[2], $date[1], $date[2], $date[0]));
	}

	public final function html($text)
	{
		return htmlspecialchars_uni($text);
	}

	public final function is_image($ext)
	{
		if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
		{
			return true;
		}
		return false;
	}

	public final function button($link, $text, $action='')
	{
		return "<a href=\"".$this->url($link, $action)."\"><img alt=\"\" src=\"{$this->img}{$text}.png\" /> {$this->core->lang->$text}</a>";
	}

	public function get_icon($ext)
	{
		global $mybb, $cache, $attachtypes, $theme;
	
		if(!$attachtypes)
		{
			$attachtypes = $cache->read("attachtypes");
		}
	
		$ext = my_strtolower($ext);
	
		if($attachtypes[$ext]['icon'])
		{
			$icon = "{$mybb->settings['bburl']}/".str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
			return "<img src=\"{$icon}\" border=\"0\" alt=\".{$ext}\" />";
		}
		else
		{
			return "<img src=\"{$mybb->settings['bburl']}/{$theme['imgdir']}/attachtypes/unknown.gif\" border=\"0\" alt=\".{$ext}\" />";
		}
	}

	public final function discussion($thread)
	{
		return "<i>{$this->core->lang->discussion_of} \"<a href=\"".$this->url($thread['wurl'].'.html')."\">{$thread['subject']}</a>\"</i>";
	}

	public final function parser_article($article)
	{
		return $this->table_start().$this->table_row_start().$article.$this->table_row_end().$this->table_end();
	}

	public final function parser_h1($title, $ref)
	{
		return $this->table_row_end().$this->table_head_start()."<a name=\"{$ref}\">{$title}</a>".$this->table_head_end().$this->table_row_start();
	}

	public final function parser_h2($title, $ref)
	{
		return $this->table_row_end().$this->table_cat_start()."<a name=\"{$ref}\">{$title}</a>".$this->table_cat_end().$this->table_row_start();
	}

	public final function parser_toc($content)
	{
		return $this->table_start().$this->table_head_start().$this->core->lang->toc.$this->table_head_end().$this->table_row_start()."<ul>\n{$content}</ul>\n".$this->table_row_end().$this->table_end();
	}

	public final function parser_tocitem($array)
	{
		if(isset($array['sub'][0]))
		{
			$add = "\n<ul>\n";
			foreach($array['sub'] as $bit)
			{
				$add .= "<li><a href=\"#{$bit['url']}\">{$bit['title']}</a></li>\n";
			}
			$add .= "</ul>\n";
		}
		return "<li><a href=\"#{$array['url']}\">{$array['title']}</a>{$add}</li>\n";
	}

	public final function image($url, $text, $link='')
	{
		$text = $this->html($text);
		if($link != '')
		{
			return "<div class=\"wikiimage\"><a href=\"{$link}\" rel=\"lightbox\"><img src=\"{$url}\" alt=\"{$text}\" /></a><br />{$text}</div>\n";
		}
		else
		{
			return "<div class=\"wikiimage\"><img src=\"{$url}\" alt=\"{$text}\" /><br />{$text}</div>\n";
		}
	}

	public final function table_start()
	{
		return "<table border=\"0\" cellspacing=\"{$this->theme['borderwidth']}\" cellpadding=\"{$this->theme['tablespace']}\" class=\"tborder\">\n";
	}

	public final function table_end()
	{
		return "</table><br />\n";
	}

	public final function table_head_start()
	{
		return "<tr><td class=\"thead\"><strong>\n";
	}

	public final function table_head_end()
	{
		return "</strong></td></tr>\n";
	}

	public final function table_cat_start()
	{
		return "<tr><td class=\"tcat\"><span class=\"smalltext\"><strong>";
	}

	public final function table_cat_end()
	{
		return "</strong></span></td></tr>\n";
	}

	public final function table_row_start()
	{
		return "<tr><td class=\"trow1\"><div class=\"smalltext\">\n";
	}

	public final function table_row_end()
	{
		return "</div></td></tr>\n";
	}

	public final function table_right_start()
	{
		return "<div style=\"float:right;\">";
	}

	public final function table_right_end()
	{
		return "</div>\n";
	}

	public final function form_start($url)
	{
		return "<form action=\"{$url}\" method=\"post\" enctype=\"multipart/form-data\">\n";
	}

	public final function form_end()
	{
		return "</form>";
	}

	public final function input_hidden($name, $value, $id='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\"{$id} />\n";
	}

	public final function input_textbox($name, $value, $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		return "<input type=\"text\" class=\"textbox\" name=\"{$name}\"{$id} size=\"40\" value=\"{$value}\"{$extra} />\n";
	}

	public final function input_checkbox($name, $checked='', $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		if($checked != '')
		{
			$checked = ' checked="checked"';
		}
		return "<input type=\"checkbox\" class=\"checkbox\" name=\"{$name}\"{$id}{$checked}{$extra} />\n";
	}

	public final function input_radio($name, $value, $checked='', $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		if($checked != '')
		{
			$checked = ' checked="checked"';
		}
		return "<input type=\"radio\" class=\"radio\" name=\"{$name}\" value=\"{$value}\"{$id}{$checked}{$extra} />\n";
	}

	public final function input_file($name, $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		return "<input type=\"file\" class=\"file\" name=\"{$name}\"{$id} size=\"40\"{$extra} />\n";
	}

	public final function input_button($name, $value, $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		return "<input type=\"button\" class=\"button\" name=\"{$name}\"{$id} value=\"{$value}\"{$extra} />\n";
	}

	public final function input_submit($name, $value, $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		return "<input type=\"submit\" class=\"submit\" name=\"{$name}\"{$id} value=\"{$value}\"{$extra} />\n";
	}

	public final function input_textarea($name, $value, $id='', $extra='', $big=true)
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		if($big == true)
		{
			return "<textarea name=\"{$name}\"{$id} rows=\"20\" cols=\"100\"{$extra}>{$value}</textarea>\n";
		}
		else
		{
			return "<textarea name=\"{$name}\"{$id} rows=\"5\" cols=\"100\"{$extra}>{$value}</textarea>\n";
		}
	}

	public final function input_select($name, $values, $id='', $extra='')
	{
		if($id != '')
		{
			$id = " id=\"{$id}\"";
		}
		$select = '';
		foreach($values as $value)
		{
			$select .= "<option value=\"{$value['value']}\"{$extra}>{$value['title']}</option>\n";
		}
		return "<select name=\"{$name}\"{$id}>\n{$select}</select>\n";
	}
	
	public final function editor()
	{
		global $db, $mybb, $theme, $templates, $lang;

		$editor_lang_strings = array(
			"editor_title_bold",
			"editor_title_italic",
			"editor_title_underline",
			"editor_title_left",
			"editor_title_center",
			"editor_title_right",
			"editor_title_justify",
			"editor_title_numlist",
			"editor_title_bulletlist",
			"editor_title_image",
			"editor_title_hyperlink",
			"editor_title_email",
			"editor_title_quote",
			"editor_title_code",
			"editor_title_php",
			"editor_title_close_tags",
			"editor_enter_list_item",
			"editor_enter_url",
			"editor_enter_url_title",
			"editor_enter_email",
			"editor_enter_email_title",
			"editor_enter_image",
			"editor_size_xx_small",
			"editor_size_x_small",
			"editor_size_small",
			"editor_size_medium",
			"editor_size_large",
			"editor_size_x_large",
			"editor_size_xx_large",
			"editor_font",
			"editor_size",
			"editor_color"
		);
		$editor_language = "var editor_language = {\n";

		foreach($editor_lang_strings as $key => $lang_string)
		{
			$js_lang_string = preg_replace("#^editor_#i", "", $lang_string);
			$string = str_replace("\"", "\\\"", $lang->$lang_string);
			$editor_language .= "\t{$js_lang_string}: \"{$string}\",\n";
		}

		$editor_language .= "	title_h1: \"{$this->core->lang->editor_title_h1}\",\n"
		."	title_h2: \"{$this->core->lang->editor_title_h2}\",\n"
		."	title_wiki: \"{$this->core->lang->editor_title_wiki}\",\n"
		."	title_toc: \"{$this->core->lang->editor_title_toc}\",\n"
		."	enter_description: \"{$this->core->lang->editor_enter_description}\"\n};";

		return "\n<script type=\"text/javascript\" src=\"{$mybb->settings['bburl']}/jscripts/wiki.js?ver={$this->ver}\"></script>\n"
		."<script type=\"text/javascript\">\n"
		."<!--\n"
		.$editor_language
		."\npopup_path = '".$this->url($this->core->lang->url_popup)."';\n"
		."var editor = new Wiki(\"text\", {lang: editor_language, rtl: {$lang->settings['rtl']}});\n"
		."// -->\n"
		."</script>\n";
	}
}
?>
