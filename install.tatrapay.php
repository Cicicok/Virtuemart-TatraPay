<?php
// No direct access to this file
defined('_JEXEC') or die;

/**
 * @version: 1.0.0 27.04.2018
 * @package: Tatrapay Virtuemart plugin
 * @copyright: 2018 Richard Forro, All rights reserved.
 * @author: 2018 Richard Forro(riki49@gmail.com)
 * @license: http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @description: TatraPay (Tatra banka Slovakia) payment plugin for Virtuemart
 * 
 * This plugin is was inpired by stardard payment, skrill, tco, paypal and others Virtuemart payment plugins. 
 */

class plgVmpaymentTatrapayInstallerScript{
  
  /**
	 * Method to install the extension
	 * $parent is the class calling this method
	 */
	function install($parent) {
	}

	/**
	 * Method to uninstall the extension
	 * $parent is the class calling this method
	 */
	function uninstall($parent) {
	}

	/**
	 * Method to update the extension
	 * $parent is the class calling this method
	 */
	function update($parent) {
	}

	/**
	 * Method to run before an install/update/uninstall method
	 * $parent is the class calling this method
	 * $type is the type of change (install, update or discover_install)
	 */
	function preflight($type, $parent) {
	}

	/**
	 * Method to run after an install/update/uninstall method
	 * $parent is the class calling this method
	 * $type is the type of change (install, update or discover_install)
	 */
  function postflight($type, $parent) {
        
    if (strtolower($type) === 'install') {
      $file_name = 'tatrapay-icon.png';
      $target_path = JPATH_SITE . '/' . 'images' . '/' . 'virtuemart' . '/' . 'payment';
      $src_path = JPATH_SITE . '/' . 'plugins' . '/' . 'vmpayment' . '/' . 'tatrapay' . '/' . 'tatrapay' . '/' . 'assets' . '/' . 'images' . '/' . $file_name;
    
      if(!is_dir($target_path)) {
        JFolder::create($target_path);
      }
      JFile::move($src_path, $target_path . '/' . $file_name);
    }
  }
}