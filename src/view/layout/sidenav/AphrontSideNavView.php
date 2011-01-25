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

final class AphrontSideNavView extends AphrontView {

  protected $items = array();

  public function addNavItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->items);

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
