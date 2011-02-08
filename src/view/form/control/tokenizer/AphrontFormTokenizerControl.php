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

class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;

  public function setDatasource($datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function setDisableBehavior($disable) {
    $this->disableBehavior = $disable;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-tokenizer';
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  protected function renderInput() {
    require_celerity_resource('aphront-tokenizer-control-css');
    require_celerity_resource('javelin-typeahead-dev');

    $tokens = array();
    $values = nonempty($this->getValue(), array());
    foreach ($values as $key => $value) {
      $tokens[] = $this->renderToken($key, $value);
    }

    $name = $this->getName();

    $input = javelin_render_tag(
      'input',
      array(
        'mustcapture' => true,
        'name'        => $name,
        'class'       => 'jx-tokenizer-input',
        'sigil'       => 'tokenizer',
        'style'       => 'width: 0px;',
        'disabled'    => 'disabled',
        'type'        => 'text',
      ));

    if ($this->getID()) {
      $id = $this->getID();
    } else {
      $id = celerity_generate_unique_node_id();
    }

    if (!$this->disableBehavior) {
      Javelin::initBehavior('aphront-basic-tokenizer', array(
        'id'    => $id,
        'src'   => $this->datasource,
        'value' => $values,
        'limit' => $this->limit,
      ));
    }

    return phutil_render_tag(
      'div',
      array(
        'id' => $id,
        'class' => 'jx-tokenizer-container',
      ),
      implode('', $tokens).
      $input.
      '<div style="clear: both;"></div>');

    return phutil_render_tag(
      'input',
      array(
        'type'      => 'text',
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
      ));
  }

  private function renderToken($key, $value) {
    $input_name = $this->getName();
    if ($input_name) {
      $input_name .= '[]';
    }
    return phutil_render_tag(
      'a',
      array(
        'class' => 'jx-tokenizer-token',
      ),
      phutil_escape_html($value).
      phutil_render_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $input_name,
          'value' => $key,
        )).
      '<span class="jx-tokenizer-x-placeholder"></span>');
  }


}
