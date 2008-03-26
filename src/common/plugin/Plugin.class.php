<?php
require_once('PluginInfo.class.php');
require_once('common/include/String.class.php');
require_once('common/collection/Map.class.php');
require_once('PluginManager.class.php');
/**
 * Copyright (c) Xerox Corporation, CodeX Team, 2001-2005. All rights reserved
 * 
 * 
 *
 * Plugin
 */
class Plugin {
    
    var $id;
    var $pluginInfo;
    var $hooks;
    var $_scope;
    
    var $SCOPE_SYSTEM;
    var $SCOPE_PROJECT;
    var $SCOPE_USER;
    
    function Plugin($id = -1) {
        $this->id            = $id;
        $this->hooks         =& new Map();
        
        $this->SCOPE_SYSTEM  = 0;
        $this->SCOPE_PROJECT = 1;
        $this->SCOPE_USER    = 2;
        
        $this->_scope = $this->SCOPE_SYSTEM;
    }
    
    function getId() {
        return $this->id;
    }
    
    function &getPluginInfo() {
        if (!is_a($this->pluginInfo, 'PluginInfo')) {
            $this->pluginInfo =& new PluginInfo($this);
        }
        return $this->pluginInfo;
    }
    
    function &getHooks() {
        return $this->hooks->getKeys();
    }
    
    function &getHooksAndCallbacks() {
        return $this->hooks->getValues();
    }
    
    function _addHook($hook, $callback = 'CallHook', $recallHook = true) {
        $value = array();
        $value['hook']       = $hook;
        $value['callback']   = $callback;
        $value['recallHook'] = $recallHook;
        $this->hooks->put(new String($hook), $value);
    }
    function _removeHook($hook) {
        $this->hooks->removeKey(new String($hook));
    }
    function CallHook($hook, $param) {
    }
    
    function getScope() {
        return $this->_scope;
    }

    function setScope($s) {
        $this->_scope = $s;
    }

    function getPluginEtcRoot() {
        $pm =& $this->_getPluginManager();
        return $GLOBALS['sys_custompluginsroot'] . '/' . $pm->getNameForPlugin($this) .'/etc';
    }
    function _getPluginPath() {
        $pm =& $this->_getPluginManager();
        if (isset($GLOBALS['sys_pluginspath']))
            $path = $GLOBALS['sys_pluginspath'];
        else $path=""; 
        if ($pm->pluginIsCustom($this)) {
            $path = $GLOBALS['sys_custompluginspath'];
        }
        return $path.'/'.$pm->getNameForPlugin($this);
    }
    function _getThemePath() {
        $pm =& $this->_getPluginManager();
        $paths  = array($GLOBALS['sys_custompluginspath'], $GLOBALS['sys_pluginspath']);
        $roots  = array($GLOBALS['sys_custompluginsroot'], $GLOBALS['sys_pluginsroot']);
        $dir    = '/'.$pm->getNameForPlugin($this).'/www/themes/';
        $dirs   = array($dir.$GLOBALS['sys_user_theme'], $dir.'default');
        $dir    = '/'.$pm->getNameForPlugin($this).'/themes/';
        $themes = array($dir.$GLOBALS['sys_user_theme'], $dir.'default');
        $found = false;
        while (!$found && (list($kd, $dir) = each($dirs))) {
            reset($roots);
            while (!$found && (list($kr, $root) = each($roots))) {
                if (is_dir($root.$dir)) {
                    $found = $paths[$kr].$themes[$kd];
                }
            }
        }
        return $found;
    }
    function &_getPluginManager() {
        $pm =& PluginManager::instance();
        return $pm;
    }
    /**
     * Function called when a plugin is set as available or unavailable
     *
     * @param boolean $available true if the plugin is available, false if unavailable
     */
    function /*abstract*/ setAvailable($available) {
    }
}
?>