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

class AphrontStandardPageView extends AphrontPageView {

  private $baseURI;
  private $applicationName;
  private $tabs = array();
  private $selectedTab;
  private $glyph;

  public function setApplicationName($application_name) {
    $this->applicationName = $application_name;
    return $this;
  }

  public function getApplicationName() {
    return $this->applicationName;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function setTabs(array $tabs, $selected_tab) {
    $this->tabs = $tabs;
    $this->selectedTab = $selected_tab;
    return $this;
  }

  public function getTitle() {
    return $this->getGlyph().' '.parent::getTitle();
  }

  protected function getHead() {
    return
      '<link rel="stylesheet" type="text/css" href="/rsrc/css/base.css" />';
  }

  public function setGlyph($glyph) {
    $this->glyph = $glyph;
    return $this;
  }

  public function getGlyph() {
    return $this->glyph;
  }

  protected function getBody() {

    $tabs = array();
    foreach ($this->tabs as $name => $tab) {
      $tabs[] = phutil_render_tag(
        'a',
        array(
          'href'  => idx($tab, 'href'),
          'class' => ($name == $this->selectedTab)
            ? 'aphront-selected-tab'
            : null,
        ),
        phutil_escape_html(idx($tab, 'name')));
    }
    $tabs = implode('', $tabs);
    if ($tabs) {
      $tabs = '<span class="aphront-head-tabs">'.$tabs.'</span>';
    }

    return
      '<div class="aphront-standard-page">'.
        '<div class="aphront-standard-header">'.
          '<a href="/">Aphront</a> '.
          phutil_render_tag(
            'a',
            array(
              'href'  => $this->getBaseURI(),
              'class' => 'aphront-head-appname',
            ),
            phutil_escape_html($this->getApplicationName())).
          $tabs.
        '</div>'.
        $this->renderChildren().
        '<div style="clear: both;"></div>'.
      '</div>';
  }

  protected function getTail() {
    return '';
  }

}
