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
