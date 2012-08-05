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

final class PhabricatorMainMenuIconView extends AphrontView {

  private $classes = array();
  private $href;
  private $name;
  private $sortOrder = 0.5;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  /**
   * Provide a float, where 0.0 is the profile item and 1.0 is the logout
   * item. Normally you should pick something between the two.
   *
   * @param float Sort order.
   * @return this
   */
  public function setSortOrder($sort_order) {
    $this->sortOrder = $sort_order;
    return $this;
  }

  public function getSortOrder() {
    return $this->sortOrder;
  }

  public function render() {
    $name = $this->getName();
    $href = $this->getHref();

    $classes = $this->classes;
    $classes[] = 'phabricator-main-menu-icon';

    $label = javelin_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'phabricator-main-menu-icon-label',
      ),
      phutil_escape_html($name));

    $item = javelin_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => implode(' ', $classes),
      ),
      '');

    $group = new PhabricatorMainMenuGroupView();
    $group->appendChild($item);
    $group->appendChild($label);

    return $group->render();
  }

}
