<?php
/**
 *  PHP Portal Engine v3.0.0
 *  https://github.com/bztsrc/phppe3/
 *
 *  Copyright LGPL 2016 bzt
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *   <http://www.gnu.org/licenses/>
 *
 * @file vendor/phppe/GPIO/01_GPIO.php
 * @author bzt
 * @date 1 Jan 2016
 * @brief Interface for Raspberry Pi GPIO
 */
namespace PHPPE;
use PHPPE\Core as Core;

/**
 * Exception class
 */
class GPIOException extends \Exception
{
    public function __construct($message="", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Main class
 *
 * @usage $gpio=GPIO::mode(3,"out")->mode(4,"in"); $gpio->write(3,true);
 */
class GPIO
{
	const PATH_GPIO = '/sys/class/gpio';
	const PATH_EXPORT = '/sys/class/gpio/export';
	const PATH_UNEXPORT = '/sys/class/gpio/unexport';

	public $pins=[];
	public $hdlr=[];
	static private $self;

/**
 * Register GPIO
 *
 * @param cfg not used
 * @return true if GPIO interface found
 */
	function init($cfg) {
		if(!@is_dir(self::PATH_GPIO)) return false;
		self::$self=$this;
		return true;
	}

/**
 * Constructor, loads pin mapping
 *
 * @param cfg not used
 */
	public function __construct($cfg=[])
	{
        if(!@is_dir(self::PATH_GPIO)) return null;
		//! get configuration and fallback to hardcoded values
		if(!empty($cfg['pins']))
			$this->pins=Core::str2arr($cfg['pins']);
		if(empty($this->pins)) {
			$rpi=self::RPiPCB();
			if ($rpi < 16)
				//! original GPIO without DNC
            			$this->pins = [ 2=>3,3=>5,4=>7,7=>26,8=>24,9=>21,10=>19,11=>23,17=>11,18=>12,22=>15,23=>16,24=>18,25=>22,27=>13 ];
	    		else
    	    			//! new GPIO layout (B+ J8)
            			$this->pins = [ 2=>3,3=>5,4=>7,5=>29,6=>31,7=>26,8=>24,9=>21,10=>19,11=>23,12=>32,13=>33,14=>8,15=>10,16=>36,17=>11,18=>12,19=>35,20=>38,21=>40,22=>15,23=>16,24=>18,25=>22,26=>37,27=>13 ];
		}
	}

/**
 * reset all pins to in mode and unexport
 */
	function reset()
	{
		foreach($this->hdlr as $k=>$v) {
			$this->mode($k,"in");
			@file_put_contents(self::PATH_UNEXPORT, $k);
		}
		$this->hdlr=[];
	}

 /**
 * Get Raspberry Pi version
 *
 * @return numeric PCB version
 */
    static public function RPiPCB()
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (preg_match('/^Revision[^0-9a-fA-F]+([0-9a-fA-F]+)/', $cpuinfo, $m))
            return hexdec($m[1]);
        return 0;
    }

 /**
 * Get CPU temperature
 *
 * @return float, SI Celsius
 */
    static public function temp()
    {
        return floatval(@file_get_contents('/sys/class/thermal/thermal_zone0/temp')/1000);
    }

 /**
 * Get CPU Frequency
 *
 * @return float
 */
    static public function freq()
    {
        return floatval(@file_get_contents('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')/1000);
    }

/**
 * Setup pin for direction (in or out)
 *
 * @param pin number
 * @param "in"/"out"
 * @return GPIO instance
 */
    static public function mode($pin, $dir="out")
    {
        $pin=intval($pin);
    	if($dir!="in"&&$dir!="out") throw new GPIOException("bad dir");
        if(empty(self::$self->pins[$pin])) throw new GPIOException("bad pin");
        try {
            if(!is_dir(self::PATH_GPIO.$pin)) {
                // Export pin
                @file_put_contents(self::PATH_EXPORT,$pin);
            }
            if(trim(@file_get_contents(self::PATH_GPIO.$pin.'/direction'))!=$dir){
                @file_put_contents(self::PATH_GPIO.$pin.'/direction', $dir);
    		//@file_put_contents(self::PATH_GPIO.$pin.'/value',"1");
	    }
            self::$self->hdlr[$pin] = $dir;
        } catch(\Exception $e) {
            throw new GPIOException($e->getMessage());
        }
        return self::$self;
    }

/**
 * Read input value
 *
 * @param  pin
 * @return GPIO value
 */
    static public function read($pin)
    {
        $pin=intval($pin);
        return intval(@file_get_contents(self::PATH_GPIO.$pin.'/value'))==1?false:true;
    }

/**
 * Write output value
 *
 * @param  pin
 * @param  value
 * @return false|string (GPIO value)
 */
    static public function write($pin,$value=true)
    {
        $pin=intval($pin);
        if(empty(self::$self->pins[$pin])||@self::$self->hdlr[$pin]!="out") throw new GPIOException("bad pin");
        file_put_contents(self::PATH_GPIO.$pin.'/value',empty($value)?"0":"1");
    }

/**
 * String representation of the object.
 */
	function __toString()
	{
		return __CLASS__."/RPi".self::RPiPCB();
	}
}
