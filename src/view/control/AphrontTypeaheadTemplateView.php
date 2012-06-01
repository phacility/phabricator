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

final class AphrontTypeaheadTemplateView extends AphrontView {

  private $value;
  private $name;
  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setValue(array $value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function render() {
    require_celerity_resource('aphront-typeahead-control-css');

    $id = $this->id;
    $name = $this->getName();
    $values = nonempty($this->getValue(), array());

    $tokens = array();
    foreach ($values as $key => $value) {
      $tokens[] = $this->renderToken($key, $value);
    }

    $input = javelin_render_tag(
      'input',
      array(
        'name'          => $name,
        'class'         => 'jx-typeahead-input',
        'sigil'         => 'typeahead',
        'type'          => 'text',
        'value'         => $this->value,
        'autocomplete'  => 'off',
      ));

    return javelin_render_tag(
      'div',
      array(
        'id'    => $id,
        'sigil' => 'typeahead-hardpoint',
        'class' => 'jx-typeahead-hardpoint',
      ),
      $input.
      '<div style="clear: both;"></div>');
  }
}
