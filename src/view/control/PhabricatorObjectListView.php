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

final class PhabricatorObjectListView extends AphrontView {

  private $handles = array();
  private $buttons = array();

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $this->handles = $handles;
    return $this;
  }

  public function addButton(PhabricatorObjectHandle $handle, $button) {
    $this->buttons[$handle->getPHID()][] = $button;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    require_celerity_resource('phabricator-object-list-view-css');

    $out = array();
    foreach ($handles as $handle) {
      $buttons = idx($this->buttons, $handle->getPHID(), array());
      if ($buttons) {
        $buttons =
          '<div class="phabricator-object-list-view-buttons">'.
            implode('', $buttons).
          '</div>';
      } else {
        $buttons = null;
      }

      $out[] = javelin_render_tag(
        'div',
        array(
          'class' => 'phabricator-object-list-view-item',
          'style' => 'background-image: url('.$handle->getImageURI().');',
        ),
        $handle->renderLink().$buttons);
    }

    return
      '<div class="phabricator-object-list-view">'.
        implode("\n", $out).
      '</div>';
  }

}
