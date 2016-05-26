<?php
class CacheTest extends PHPUnit_Framework_TestCase
{
	//! driver specific tests
	public function testAPC()
	{
		$apc = new \PHPPE\Cache\APC("apc");
		$apc->set("key2","value",false,1);
		$v = $apc->get("key2");
		if(!empty($_SERVER["SERVER_NAME"]))
			$this->assertNotNull($v,"APC");
	}

	public function testFiles()
	{
		$files = new \PHPPE\Cache\Files("files");
		$files->set("key2","value",false,1);
		$this->assertNotNull($files->get("key2"),"Files set/get");
		sleep(1.01);
		$files->cronMinute("");
		$this->assertNull($files->get("key2"),"Files ttl");
	}

	public function testMemcache()
	{
		\PHPPE\Cache::$mc=null;
		$mem = new \PHPPE\Cache("127.0.0.1:11211");
		$this->assertNotNull(\PHPPE\Cache::$mc,"Memcached connection");
		\PHPPE\Cache::$mc->set("key2","value",false,1);
		$this->assertEquals("value",\PHPPE\Cache::$mc->get("key2"),"Memcached set/get");
	}

	public function testNoServer()
	{
		\PHPPE\Cache::$mc=null;
		$mem = new \PHPPE\Cache("noSuchServer");
		$this->assertNull(\PHPPE\Cache::$mc,"No memcache server");
	}

	//! overall cache tests
	public function testCache()
	{
		\PHPPE\Core::$core->nocache=false;

		//dirty hack required when run through phpunit
		//as it does not call run(), and SCRIPT_FILENAME
		//won't be public/index.php,
		//but /usr/local/bin/phpunit.phar
		\PHPPE\Core::$core->base=str_replace(
			"/usr/local/bin","",
			\PHPPE\Core::$core->base);
		$dir="vendor/phppe/Developer";
		\PHPPE\View::setPath($dir);
		\PHPPE\Core::$user->id="";

		//test if there's no cache
		$mc=\PHPPE\Cache::$mc;
		$cache = new \PHPPE\Cache("files");
		$cache->set('aaa',1);
		$cache->get('aaa');
		\PHPPE\Cache::$mc=null;
		$cache->set('aaa',1);
		$cache->get('aaa');
		\PHPPE\Cache::$mc=$mc;

		//use memcached cache if otherwise not configured
		if(empty($mc) || empty(\PHPPE\Core::$core->cache))
			$mem = new \PHPPE\Cache("127.0.0.1:11211");
		\PHPPE\Core::$core->nocache=false;

		$this->assertNotEmpty(\PHPPE\Cache::$mc,"Cache initialized");

		$var = "t_00".time();

		\PHPPE\Core::$core->nocache=true;
		$this->assertFalse(\PHPPE\Cache::set($var,"aaa"),"Set with nocache");
		$this->assertNull(\PHPPE\Cache::get($var),"Get with nocache");
		\PHPPE\Core::$core->nocache=false;
		$this->assertNotFalse(\PHPPE\Cache::set($var,"aaa"),"Set");
		$this->assertEquals("aaa",\PHPPE\Cache::get($var),"Get");

		$tn = 't_' . sha1(\PHPPE\Core::$core->base."_cachetest");
		\PHPPE\Cache::set($tn,"",1);

		$txt = \PHPPE\View::template("cachetest");
		$this->assertNotEmpty(\PHPPE\Cache::get($tn),"Template caching $tn");

		$url=url("tests","http")."cachetest";

        $sha = \PHPPE\Core::$core->base . "tests/http/cachetest/".\PHPPE\Core::$user->id . "/". \PHPPE\Core::$client->lang;

		$N = 'p_' . sha1($sha);
		\PHPPE\Cache::set($N,"",1);
		$this->assertEmpty(\PHPPE\View::fromCache($N),"Page cache #1");
		file_get_contents($url); //make sure the output gets to the cache
		$d1 = file_get_contents($url); //this must be served from cache

		$this->assertNotEmpty(\PHPPE\View::fromCache($N),"Page cache #2\nCache not configured in config.php or memcached not running");

		$d2 = file_get_contents($url."?skipcache=1"); //trigger nocache flag set in constructor

		$this->assertNotFalse(strpos($d1,", mc -->"),"Output cache #1");
		$this->assertFalse(strpos($d1,"NOCACHE"),"Output cache #2");

		$this->assertFalse(strpos($d2,", mc -->"),"Output cache #3");
		$this->assertNotFalse(strpos($d2,"NOCACHE"),"Output cache #4");

		if(method_exists(\PHPPE\Cache::$mc,"cronMinute")) \PHPPE\Cache::$mc->cronMinute("");

	}

}
?>
