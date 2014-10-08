<?php

final class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;
  private $placeholder;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
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

  public function setPlaceholder($placeholder) {
    $this->placeholder = $placeholder;
    return $this;
  }

  protected function renderInput() {
    $name = $this->getName();
    $values = nonempty($this->getValue(), array());

    assert_instances_of($values, 'PhabricatorObjectHandle');

    if ($this->getID()) {
      $id = $this->getID();
    } else {
      $id = celerity_generate_unique_node_id();
    }

    $placeholder = null;
    if (!strlen($this->placeholder)) {
      if ($this->datasource) {
        $placeholder = $this->datasource->getPlaceholderText();
      }
    } else {
      $placeholder = $this->placeholder;
    }

    $template = new AphrontTokenizerTemplateView();
    $template->setName($name);
    $template->setID($id);
    $template->setValue($values);

    $username = null;
    if ($this->user) {
      $username = $this->user->getUsername();
    }

    $datasource_uri = null;
    if ($this->datasource) {
      $datasource_uri = $this->datasource->getDatasourceURI();
    }

    if (!$this->disableBehavior) {
      Javelin::initBehavior('aphront-basic-tokenizer', array(
        'id'          => $id,
        'src'         => $datasource_uri,
        'value'       => mpull($values, 'getFullName', 'getPHID'),
        'icons'       => mpull($values, 'getIcon', 'getPHID'),
        'limit'       => $this->limit,
        'username'    => $username,
        'placeholder' => $placeholder,
      ));
    }

    return $template->render();
  }

}
