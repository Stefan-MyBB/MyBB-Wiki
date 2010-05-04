<?php
/*
	Wiki-System
	(c) 2007-2010 by StefanT
	http://www.mybbcoder.info
*/
class wiki_cache
{
	private $core;
	private $tid = 0;

	public final function __construct(&$core, $tid)
	{
		$this->core = &$core;
		$this->tid = intval($tid);
	}

	public final function get()
	{
		$query = $this->core->db->simple_select('wiki_cache', 'content', 'tid='.$this->tid.' AND active=1');
		return $this->core->db->fetch_field($query, 'content');
	}

	public final function update()
	{
		$query = $this->core->db->simple_select('wiki_cache', 'content', 'tid='.$this->tid);
		if($this->core->db->fetch_array($query))
		{
			$this->core->db->update_query('wiki_cache', array('active' => 0), 'tid='.$this->tid);
		}
		else
		{
			$this->core->db->insert_query('wiki_cache', array('tid' => $this->tid, 'active' => 0));
		}
	}

	public final function insert($content)
	{
		$query = $this->core->db->simple_select('wiki_cache', 'content', 'tid='.$this->tid);
		if($this->core->db->fetch_array($query))
		{
			$this->core->db->update_query('wiki_cache', array('active' => 1, 'content' => $this->core->db->escape_string($content)), 'tid='.$this->tid);
		}
		else
		{
			$this->core->db->insert_query('wiki_cache', array('tid' => $this->tid, 'active' => 1, 'content' => $this->core->db->escape_string($content)));
		}
	}
}
?>
