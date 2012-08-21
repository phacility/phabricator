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

final class PhabricatorAnchorView extends AphrontView {

  private $anchorName;
  private $navigationMarker;

  public function setAnchorName($name) {
    $this->anchorName = $name;
    return $this;
  }

  public function setNavigationMarker($marker) {
    $this->navigationMarker = $marker;
    return $this;
  }

  public function render() {
    $marker = null;
    if ($this->navigationMarker) {
      $marker = javelin_render_tag(
        'legend',
        array(
          'class' => 'phabricator-anchor-navigation-marker',
          'sigil' => 'marker',
          'meta'  => array(
            'anchor' => $this->anchorName,
          ),
        ),
        '');
    }

    $anchor = phutil_render_tag(
      'a',
      array(
        'name'  => $this->anchorName,
        'id'    => $this->anchorName,
        'class' => 'phabricator-anchor-view',
      ),
      '');

    return $marker.$anchor;
  }

}
