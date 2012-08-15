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

final class AphrontFormView extends AphrontView {

  private $action;
  private $method = 'POST';
  private $header;
  private $data = array();
  private $encType;
  private $user;
  private $workflow;
  private $id;
  private $flexible;

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

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

  public function addHiddenInput($key, $value) {
    $this->data[$key] = $value;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function render() {
    if ($this->flexible) {
      require_celerity_resource('phabricator-form-view-css');
    }
    require_celerity_resource('aphront-form-view-css');

    Javelin::initBehavior('aphront-form-disable-on-submit');

    $layout = new AphrontFormLayoutView();

    if (!$this->flexible) {
      $layout
        ->setBackgroundShading(true)
        ->setPadded(true);
    }

    $layout
      ->appendChild($this->renderDataInputs())
      ->appendChild($this->renderChildren());

    if (!$this->user) {
      throw new Exception('You must pass the user to AphrontFormView.');
    }

    return phabricator_render_form(
      $this->user,
      array(
        'class'   => $this->flexible ? 'phabricator-form-view' : null,
        'action'  => $this->action,
        'method'  => $this->method,
        'enctype' => $this->encType,
        'sigil'   => $this->workflow ? 'workflow' : null,
        'id'      => $this->id,
      ),
      $layout->render());
  }

  private function renderDataInputs() {
    $inputs = array();
    foreach ($this->data as $key => $value) {
      if ($value === null) {
        continue;
      }
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
