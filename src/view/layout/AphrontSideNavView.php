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

final class AphrontSideNavView extends AphrontView {

  protected $items = array();
  protected $flexNav;
  protected $isFlexible;

  public function addNavItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function setFlexNav($flex) {
    $this->flexNav = $flex;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->items);

    if ($this->flexNav) {
      require_celerity_resource('phabricator-nav-view-css');

      $nav_id = celerity_generate_unique_node_id();
      $drag_id = celerity_generate_unique_node_id();
      $content_id = celerity_generate_unique_node_id();

      if ($this->flexible) {
        Javelin::initBehavior(
          'phabricator-nav',
          array(
            'navID'     => $nav_id,
            'dragID'    => $drag_id,
            'contentID' => $content_id,
          ));
        $flex_bar = phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-nav-drag',
            'id' => $drag_id,
          ),
          '');
      } else {
        $flex_bar = null;
      }

      return
        '<div class="phabricator-nav">'.
          phutil_render_tag(
            'div',
            array(
              'class' => 'phabricator-nav-col',
              'id'    => $nav_id,
            ),
            $view->render()).
          $flex_bar.
          phutil_render_tag(
            'div',
            array(
              'class' => 'phabricator-nav-content',
              'id' => $content_id,
            ),
            $this->renderChildren()).
        '</div>';
    } else {

      require_celerity_resource('aphront-side-nav-view-css');

      return
        '<table class="aphront-side-nav-view">'.
          '<tr>'.
            '<th class="aphront-side-nav-navigation">'.
              $view->render().
            '</th>'.
            '<td class="aphront-side-nav-content">'.
              $this->renderChildren().
            '</td>'.
          '</tr>'.
        '</table>';
    }
  }

}
