<?php
/**
 * @file vendor/phppe/CMS/ctrl/layouts.php
 * @author bzt
 * @date 26 May 2016
 * @brief
 */

namespace PHPPE\Ctrl;
use PHPPE\Core as Core;
use PHPPE\View as View;
use PHPPE\Http as Http;

class CMSLayouts
{
/**
 * default action
 */
	function action($item)
	{
		if(empty($item)){
			$this->layouts = \PHPPE\Views::find([],"sitebuild=''","name");
			$this->sitebuilds = \PHPPE\Views::find([],"sitebuild!=''","name");
		} else {
			$this->layout = new \PHPPE\Views($item);
			$this->numPages = \PHPPE\Page::getNum($item);
		}
	}
}
