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

final class AphrontFormView extends AphrontView {

  private $action;
  private $method = 'POST';
  private $header;
  private $data = array();
  private $encType;

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function setMethod($method) {
    $this->method = $method;
    return $this;
  }

  public function setEncType($enc_type) {
    $this->encType = $enc_type;
    return $this;
  }

  public function render() {
    return phutil_render_tag(
      'form',
      array(
        'action'  => $this->action,
        'method'  => $this->method,
        'class'   => 'aphront-form-view',
        'enctype' => $this->encType,
      ),
      $this->renderDataInputs().
      $this->renderChildren());
  }

  private function renderDataInputs() {
    $data = $this->data + array(
      '__form__' => 1,
    );
    $inputs = array();
    foreach ($data as $key => $value) {
      $inputs[] = phutil_render_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $key,
          'value' => $value,
        ));
    }
    return implode("\n", $inputs);
  }

}
