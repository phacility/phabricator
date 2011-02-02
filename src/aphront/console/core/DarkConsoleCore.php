<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DarkConsoleCore {

  const PLUGIN_ERRORLOG     = 'ErrorLog';
  const PLUGIN_SERVICES     = 'Services';
  const PLUGIN_XHPROF       = 'XHProf';
  const PLUGIN_REQUEST      = 'Request';

  public static function getPlugins() {
    return array(
      self::PLUGIN_ERRORLOG,
      self::PLUGIN_REQUEST,
      self::PLUGIN_SERVICES,
      self::PLUGIN_XHPROF,
    );
  }

  private $plugins = array();
  private $settings;
  private $coredata;

  public function setConsoleSetting($key, $value) {
/*
    $guard = new WriteOnHttpGet();
      $okay = user_set_pref(
        $this->getCoreData()->getViewerContext()->getUserID(),
        self::APPLICATION_ID,
        $key,
        $value);
    $guard->release();
    if (!$okay) {
      throw new Exception('Failed to set preference setting.');
    }
*/
  }

  public function getConsoleSetting($key) {
//    $viewer_id = $this->getCoreData()->getViewerContext()->getUserID();
//    return idx(idx($this->settings[$viewer_id], $key), 'value');
    return true;
  }

  public function getPlugin($plugin_name) {
    return idx($this->plugins, $plugin_name);
  }

  public function __construct() {

/*
    $this->settings = users_multiget_prefs_info(
      array($coredata->getViewerContext()->getUserID()),
      self::APPLICATION_ID);

    $disabled = $this->getConsoleSetting(self::SETTING_PLUGINS);
    $disabled = array_flip(explode(',', $disabled));
*/
    foreach (self::getPlugins() as $plugin_name) {
      $plugin = self::newPlugin($plugin_name);
      if ($plugin->isPermanent() || !isset($disabled[$plugin_name])) {
        if ($plugin->shouldStartup()) {
          $plugin->didStartup();
          $plugin->setConsoleCore($this);
          $this->plugins[$plugin_name] = $plugin;
        }
      }
    }
  }

  public static function newPlugin($plugin) {
    $class = 'DarkConsole'.$plugin.'Plugin';
    PhutilSymbolLoader::loadClass($class);
    return newv($class, array());
  }

  public function getEnabledPlugins() {
    return $this->plugins;
  }

  public function render(AphrontRequest $request) {

    $plugins = $this->getEnabledPlugins();

    foreach ($plugins as $plugin) {
      $plugin->setRequest($request);
      $plugin->willShutdown();
    }

    foreach ($plugins as $plugin) {
      $plugin->didShutdown();
    }

    foreach ($plugins as $plugin) {
      $plugin->setData($plugin->generateData());
    }

    $selected = 'XHProf';//true;//$this->getConsoleSetting(DarkConsoleCore::SETTING_TAB);
    $visible  = true;//$this->getConsoleSetting(DarkConsoleCore::SETTING_VISIBLE);

    if (!isset($plugins[$selected])) {
      $selected = key($plugins);
    }

    $tabs = array();
    foreach ($plugins as $key => $plugin) {
      $tabs[$key] = array(
        'name'  => $plugin->getName(),
        'panel' => $plugin->render(),
      );
    }

    $tabs_markup   = array();
    $panel_markup = array();
    foreach ($tabs as $key => $data) {
      $is_selected = ($key == $selected);
      if ($is_selected) {
        $style    = null;
        $tabclass = 'dark-console-tab-selected';
      } else {
        $style    = 'display: none;';
        $tabclass = null;
      }

      $tabs_markup[] = javelin_render_tag(
        'a',
        array(
          'class' => "dark-console-tab {$tabclass}",
          'sigil' => 'dark-console-tab',
          'meta'  => array(
            'key' => $key,
          ),
        ),
        (string)$data['name']);

      $panel_markup[] = javelin_render_tag(
        'div',
        array(
          'class' => 'dark-console-panel',
          'style' => $style,
          'sigil' => 'dark-console-panel',
          'meta'  => array(
            'key' => $key,
          ),
        ),
        (string)$data['panel']);
    }

    $console = javelin_render_tag(
      'table',
      array(
        'class' => 'dark-console',
        'sigil' => 'dark-console',
        'meta' => array(
          'visible' => true,
        ),
        'style' => '',
      ),
      '<tr>'.
        '<th class="dark-console-tabs">'.
          implode("\n", $tabs_markup).
        '</th>'.
        '<td>'.implode("\n", $panel_markup).'</td>'.
      '</tr>');

    Javelin::initBehavior('dark-console');

    return "\n\n\n\n".$console."\n\n\n\n";
  }
}

