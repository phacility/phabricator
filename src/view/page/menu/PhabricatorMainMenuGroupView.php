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

final class PhabricatorMainMenuGroupView extends AphrontView {

  private $collapsible = true;
  private $classes = array();

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setCollapsible($collapsible) {
    $this->collapsible = $collapsible;
    return $this;
  }

  public function render() {
    $classes = array(
      'phabricator-main-menu-group',
    );

    if ($this->collapsible) {
      $classes[] = 'phabricator-main-menu-collapsible';
    }

    if ($this->classes) {
      $classes = array_merge($classes, $this->classes);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->renderChildren());
  }

}
