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

final class AphrontContextBarView extends AphrontView {

  protected $buttons = array();

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->buttons);

    require_celerity_resource('aphront-contextbar-view-css');

    return
      '<div class="aphront-contextbar-view">'.
        '<div class="aphront-contextbar-core">'.
          '<div class="aphront-contextbar-buttons">'.
            $view->render().
          '</div>'.
          '<div class="aphront-contextbar-content">'.
            $this->renderChildren().
          '</div>'.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>';
  }

}
