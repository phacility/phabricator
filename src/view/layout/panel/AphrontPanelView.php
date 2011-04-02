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

final class AphrontPanelView extends AphrontView {

  const WIDTH_FULL = 'full';
  const WIDTH_FORM = 'form';
  const WIDTH_WIDE = 'wide';

  private $createButton;
  private $header;
  private $width;

  public function setCreateButton($create_button, $href) {

    $this->createButton = phutil_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'create-button button green',
      ),
      $create_button);

    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function render() {
    if ($this->header !== null) {
      $header = '<h1>'.$this->header.'</h1>';
    } else {
      $header = null;
    }

    if ($this->createButton !== null) {
      $button = $this->createButton;
    } else {
      $button = null;
    }

    $table = $this->renderChildren();

    require_celerity_resource('aphront-panel-view-css');

    $class = array('aphront-panel-view');
    if ($this->width) {
      $class[] = 'aphront-panel-width-'.$this->width;
    }

    return
      '<div class="'.implode(' ', $class).'">'.
        $button.
        $header.
        $table.
      '</div>';
  }

}
