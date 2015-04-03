<?php

final class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;
  private $placeholder;
  private $handles;

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

  public function willRender() {
    // Load the handles now so we'll get a bulk load later on when we actually
    // render them.
    $this->loadHandles();
  }

  protected function renderInput() {
    $name = $this->getName();

    $handles = $this->loadHandles();
    $handles = iterator_to_array($handles);

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
    $template->setValue($handles);

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
        'value'       => mpull($handles, 'getFullName', 'getPHID'),
        'icons'       => mpull($handles, 'getIcon', 'getPHID'),
        'limit'       => $this->limit,
        'username'    => $username,
        'placeholder' => $placeholder,
      ));
    }

    return $template->render();
  }

  private function loadHandles() {
    if ($this->handles === null) {
      $viewer = $this->getUser();
      if (!$viewer) {
        throw new Exception(
          pht(
            'Call setUser() before rendering tokenizers. Use appendControl() '.
            'on AphrontFormView to do this easily.'));
      }

      $values = nonempty($this->getValue(), array());
      $this->handles = $viewer->loadHandles($values);
    }

    return $this->handles;
  }

}
