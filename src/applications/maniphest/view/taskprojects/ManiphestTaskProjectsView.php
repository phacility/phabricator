<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/**
 * @group maniphest
 */
final class ManiphestTaskProjectsView extends ManiphestView {

  private $handles;

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-project-tag-css');


    $show = array_slice($this->handles, 0, 2);

    $tags = array();
    foreach ($show as $handle) {
      $tags[] = phutil_render_tag(
        'a',
        array(
          'href'  => $handle->getURI(),
          'class' => 'phabricator-project-tag',
        ),
        phutil_escape_html(
          phutil_utf8_shorten($handle->getName(), 24)));
    }

    if (count($this->handles) > 2) {
      require_celerity_resource('aphront-tooltip-css');
      Javelin::initBehavior('phabricator-tooltips');

      $all = array();
      foreach ($this->handles as $handle) {
        $all[] = $handle->getName();
      }

      $tags[] = javelin_render_tag(
        'span',
        array(
          'class' => 'phabricator-project-tag',
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip' => implode(', ', $all),
            'size' => 200,
          ),
        ),
        "\xE2\x80\xA6");
    }

    return implode("\n", $tags);
  }

}
