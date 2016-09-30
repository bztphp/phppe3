<?php
/**
 * @file vendor/phppe/ClusterSrv/ctrl/clustersrv.php
 * @author bzt
 * @date 27 Sep 2016
 * @brief
 */

namespace PHPPE\Ctrl;
use PHPPE\Core as Core;
use PHPPE\View as View;
use PHPPE\DS as DS;

class ClusterSrv extends \PHPPE\ClusterSrv
{
	static $cli="cluster [server|status|takeover|refresh|help]";

	function help($item=null)
	{
		//! check if executed from CLI
		if(\PHPPE\Core::$client->ip!="CLI")
			\PHPPE\Http::redirect("403");
		die("cluster status    - prints the current status\n".
			"cluster server    - checks management server (called from cron)\n".
			"cluster takeover  - force this management server to became master.\n".
			"cluster refresh   - flush worker cache.\n".
			"cluster client    - checks worker server (called from cron).\n"
		);
	}

	function takeover($item=null)
	{
		//! check if executed from CLI
		if(\PHPPE\Core::$client->ip!="CLI")
			\PHPPE\Http::redirect("403");
		$node=Core::lib("ClusterSrv");
		DS::exec("UPDATE ".self::$_table." SET type='slave',viewd=CURRENT_TIMESTAMP WHERE type='master'");
		DS::exec("UPDATE ".self::$_table." SET type='master',modifyd=CURRENT_TIMESTAMP WHERE id=?", [$node->id]);
		$node->resources("start");
		DS::exec("UPDATE ".self::$_table." SET cmd='reload' WHERE type='lb'");
	}

	function refresh($item=null)
	{
		//! check if executed from CLI
		if(\PHPPE\Core::$client->ip!="CLI")
			\PHPPE\Http::redirect("403");
		$node=Core::lib("ClusterSrv");
		DS::exec("UPDATE ".self::$_table." SET cmd='invalidate' WHERE type='worker'");
		DS::exec("UPDATE ".self::$_table." SET cmd='reload' WHERE type='lb'");
		if($node->_master)
			$node->resources("reload");
	}

	function server($item=null)
	{
		//! check if executed from CLI
		if(\PHPPE\Core::$client->ip!="CLI")
			\PHPPE\Http::redirect("403");
		$node=Core::lib("ClusterSrv");
		// get command
		$cmd=DS::field( "cmd",self::$_table,"id=?","","",[$node->id]);
		if($cmd=="restart") {
			exec("sudo restart");
		}
		// keep alive signal
		$d=@file_get_contents("/proc/loadavg");
		$l=!empty($d)?explode(" ",$d)[0]:"1.0";
		if(empty(DS::exec("UPDATE ".self::$_table." SET modifyd=CURRENT_TIMESTAMP,cmd='',load=? WHERE id=?",[$l,$node->id]))){
			DS::exec("INSERT INTO ".self::$_table." (id,name,load,type,created,modifyd) VALUES (?,?,?,'".($node->_master?"master":"slave")."',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",[$node->id,gethostbyaddr($node->id),$l]);
		};
		$master=DS::field("id",self::$_table,"type='master' AND modifyd>CURRENT_TIMESTAMP-120");
		if (strtolower(trim($node->id)) == strtolower(trim($master))) {
			/* Master */
			$node->resources("check");
			//! purge old entries
			DS::exec("DELETE FROM ".self::$_table." WHERE modifyd<CURRENT_TIMESTAMP-900");
			DS::exec("UPDATE ".self::$_table." SET cmd='restart' WHERE modifyd<CURRENT_TIMESTAMP-120");

			//! stop unused worker nodes
			DS::exec("UPDATE ".self::$_table." SET cmd='shutdown' WHERE type='worker' AND viewd<CURRENT_TIMESTAMP-900 AND load<0.05");

			//! if there are overloaded worker nodes, start new nodes
			$overloaded=DS::field("id",self::$_table,"type='worker' AND load>1.0 AND modifyd>CURRENT_TIMESTAMP-120");
			if(!empty($overloaded))
				$node->resources("worker");
		} else {
			/* Slave */
			$node->resources("stop");
			// no master?
			if (empty($master)) {
				// am I the first slave?
				$slave=DS::field("id",self::$_table,"type='slave' AND modifyd>CURRENT_TIMESTAMP-120","","id");
				if (strtolower(trim($node->id)) == strtolower(trim($slave))) {
					$this->takeover();
				}
			}
		}
	}

/**
 * Action handler
 */
	function action($item)
	{
		$lib=Core::lib("ClusterSrv");
		$nodes=DS::query("*",self::$_table,"","","type,id");
		$master=DS::field("id",self::$_table,"type='master' AND modifyd>CURRENT_TIMESTAMP-120");

		//! check if executed from CLI
		if(\PHPPE\Core::$client->ip!="CLI") {
			header("Content-type:application/json");
			$loadavg=0.0; $waspeek=0;
			if(!empty($nodes)){
				foreach($nodes as $k=>$node) {
					unset($nodes[$k]["cmd"]);
					$loadavg+=floatval($node['load']);
					if($node['load']>=0.5)
						$waspeek=1;
					if($node['load']>=0.75)
						$waspeek=2;
				}
				$loadavg/=count($nodes);
			}
			die(json_encode(["status"=>($loadavg<0.1?"idle":($loadavg>0.5||$waspeek?($loadavg>0.75||$waspeek==2?"error":"warn"):"ok")),"loadavg"=>$loadavg,"master"=>$master,"nodes"=>$nodes]));
		} else {
			echo(chr(27)."[96mId              Type        Load  Last seen            Last viewed          Name\n--------------  ---------  -----  -------------------  -------------------  -----------------------------".chr(27)."[0m\n");
			foreach($nodes as $node) {
				echo(sprintf("%-16s%-8s%8s",$node['id'],$node['type'],$node['load'])."  ".$node['modifyd']."  ".$node['viewd']."  ".$node['name']."\n");
			}
	        echo("\n".chr(27)."[96mThis server is: ".chr(27)."[0m".$lib->id." ".chr(27)."[".(strtolower(trim($this->id)) == strtolower(trim($master))?"91mMASTER":"92mSLAVE").chr(27)."[0m\n");
		}
	}
}
