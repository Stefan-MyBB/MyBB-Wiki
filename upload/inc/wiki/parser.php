<?php
/*
	Wiki-System
	(c) 2008-2010 by StefanT
	http://www.mybbcoder.info
*/
class wiki_parser
{
	private $options = array(
		'allow_html' => 0,
		'allow_mycode' => 1,
		'allow_smilies' => 0,
		'allow_imgcode' => 1,
		'filter_badwords' => 1,
		'strip_tags' => 0,
		'nl2br' => 1
		);
	private $parser;
	private $core;

	public final function __construct(&$core)
	{
		$this->core = &$core;

		require_once MYBB_ROOT.'inc/class_parser.php';
		$this->parser = new postParser;
	}

	public final function parse(&$contents, $url, $pid=0, $posthash='', $files='')
	{
		$contents = $this->parser->parse_message($contents, $this->options);
		$contents = preg_replace('#\[h1\](.*?)\[/h1\]#esi', "'[h1:'.\$this->core->output->get_url(stripslashes('$1'), 1).']'.stripslashes('$1').'[/h1]'", $contents);
		$contents = preg_replace('#\[h2\](.*?)\[/h2\]#esi', "'[h2:'.\$this->core->output->get_url(stripslashes('$1'), 1).']'.stripslashes('$1').'[/h2]'", $contents);
		$contents = preg_replace('#(<br />\n|)\[toc\](<br />\n|)#esi', "\$this->toc(\$contents)", $contents);
		$contents = preg_replace('#(<br />\n|)\[h1:(.*?)\](.*?)\[/h1\](<br />\n|)#esi', "\$this->core->output->parser_h1(stripslashes('$3'), stripslashes('$2'))", $contents);
		$contents = preg_replace('#(<br />\n|)\[h2:(.*?)\](.*?)\[/h2\](<br />\n|)#esi', "\$this->core->output->parser_h2(stripslashes('$3'), stripslashes('$2'))", $contents);
		$this->links($contents);
		$this->files($contents, $url, $pid, $posthash, $files);
		$contents = $this->core->output->parser_article($contents);
		$contents = preg_replace('#'.$this->core->output->table_row_start().$this->core->output->table_row_end().'#si', "", $contents);
	}

	private final function toc($contents)
	{
		preg_match_all("#\[h(1|2):(.*?)\](.*?)\[/h(1|2)\]#si", $contents, $toc);
		if(!is_array($toc[2]))
		{
			return '';
		}
		$toc_cache = array();
		$parent = 0;
		foreach($toc[3] as $key => $bit)
		{
			if($toc[1][$key] == 1)
			{
				$parent++;
				$toc_cache[$parent] = array('title' => $bit,
					'url' => $toc[2][$key],
					'sub' => array()
					);
			}
			elseif($parent != 0)
			{
				$toc_cache[$parent]['sub'][] = array('title' => $bit, 'url' => $toc[2][$key]);
			}
		}

		$output = '';
		foreach($toc_cache as $bit)
		{
			$output .= $this->core->output->parser_tocitem($bit);
		}
		return $this->core->output->parser_toc($output);
	}

	private final function links(&$contents)
	{
		preg_match_all("#\[wiki=(.*?)\#(.*?)\](.*?)\[/wiki\]#si", $contents, $links);
		$array = array();
		foreach($links[1] as $link)
		{
			$array[] = $this->core->db->escape_string(str_replace('&amp;', '&', $link));
		}
		$where = implode('\',\'', $array);
		if($where == '')
		{
			return;
		}
		$query = $this->core->db->simple_select('threads', 'subject,wurl', "subject IN ('{$where}') AND wid!=0");
		while($article = $this->core->db->fetch_array($query))
		{
			$articles[$article['subject']] = $article;
		}
		foreach($links[1] as $link)
		{
			$preg = preg_quote($link, '#');
			$link = str_replace(array('\'', '&amp;'), array('\\\'', '&'), $link);
			if(isset($articles[$link]))
			{
				$contents = preg_replace("#\[wiki={$preg}\#(.*?)\](.*?)\[/wiki\]#esi", "\$this->core->output->link('{$articles[$link]['wurl']}', '$2', '$1')", $contents);
			}
			else
			{
				$contents = preg_replace("#\[wiki={$preg}\#(.*?)\](.*?)\[/wiki\]#esi", "\$this->core->output->link_new('$link', '$2')", $contents);
			}
		}
	}

	private final function files(&$contents, $url, $pid, $posthash, $files)
	{
		$url = str_replace('.html', '', $url);
		if($pid != 0)
		{
			$where = 'pid='.intval($pid);
		}
		else
		{
			$where = 'posthash=\''.$this->core->db->escape_string($posthash).'\' AND pid=0';
		}
		if($files != '')
		{
			$where .= ' AND aid IN ('.$this->core->escape_list($files).')';
		}
		else
		{
			$where .= ' AND visible=1';
		}
		$files = array();
		$query = $this->core->db->simple_select('attachments', 'aid, filename, attachname', $where);
		while($file = $this->core->db->fetch_array($query))
		{
			$files[$file['attachname']] = $file;
		}
		foreach($files as $file)
		{
			$ext = get_extension($file['filename']);
			if($this->core->output->is_image($ext))
			{
				if(preg_match('#.small.attach#', $file['attachname']))
				{
					$link = $this->core->output->url($url.'/'.$this->core->output->get_full_url($files[str_replace('.small.attach', '.attach', $file['attachname'])]['filename']), $this->core->lang->url_file);
				}
				elseif(preg_match('#.big.attach#', $file['attachname']))
				{
					$link = $this->core->output->url($url.'/'.$this->core->output->get_full_url($files[str_replace('.big.attach', '.attach', $file['attachname'])]['filename']), $this->core->lang->url_file);
				}
				else
				{
					$link = $this->core->output->url($url.'/'.$this->core->output->get_full_url($file['filename']), $this->core->lang->url_file);
				}
				$contents = preg_replace("#\[f={$file['aid']}\](.*?)\[/f\]#esi", "\$this->core->output->image('".$this->core->output->url($url.'/'.$this->core->output->get_full_url($file['filename']), $this->core->lang->url_file)."', '$1', '{$link}')", $contents);
			}
			else
			{
				$contents = preg_replace("#\[f={$file['aid']}\]#esi", "'<a href=\"'.\$this->core->output->url(\$url.'/'.\$this->core->output->get_full_url(\$file['filename']), \$this->core->lang->url_file).'\">'.\$this->core->output->html(\$file['filename']).'</a>'", $contents);
			}
		}
	}
}
?>
