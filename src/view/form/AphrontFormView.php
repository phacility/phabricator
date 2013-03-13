<?php

final class AphrontFormView extends AphrontView {

  private $action;
  private $method = 'POST';
  private $header;
  private $data = array();
  private $encType;
  private $workflow;
  private $id;
  private $flexible;
  private $noShading;
  private $sigils = array();

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function setNoShading($shading) {
    $this->noShading = $shading;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
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

  public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  public function render() {
    if ($this->flexible) {
      require_celerity_resource('phabricator-form-view-css');
    }
    require_celerity_resource('aphront-form-view-css');

    $layout = new AphrontFormLayoutView();

    if ((!$this->flexible) && (!$this->noShading)) {
      $layout
        ->setBackgroundShading(true)
        ->setPadded(true);
    }

    $layout
      ->appendChild($this->renderDataInputs())
      ->appendChild($this->renderChildren());

    if (!$this->user) {
      throw new Exception(pht('You must pass the user to AphrontFormView.'));
    }

    $sigils = $this->sigils;
    if ($this->workflow) {
      $sigils[] = 'workflow';
    }

    return phabricator_form(
      $this->user,
      array(
        'class'   => $this->flexible ? 'phabricator-form-view' : null,
        'action'  => $this->action,
        'method'  => $this->method,
        'enctype' => $this->encType,
        'sigil'   => $sigils ? implode(' ', $sigils) : null,
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
      $inputs[] = phutil_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $key,
          'value' => $value,
        ));
    }
    return $inputs;
  }

}
