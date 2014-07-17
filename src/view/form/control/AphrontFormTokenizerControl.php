<?php

final class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;
  private $placeholder;

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

    if (!strlen($this->placeholder)) {
      $placeholder = $this->getDefaultPlaceholder();
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

    if ($this->datasource instanceof PhabricatorTypeaheadDatasource) {
      $datasource_uri = $this->datasource->getDatasourceURI();
    } else {
      $datasource_uri = $this->datasource;
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

  private function getDefaultPlaceholder() {
    $datasource = $this->datasource;

    if ($datasource instanceof PhabricatorTypeaheadDatasource) {
      return $datasource->getPlaceholderText();
    }

    $matches = null;
    if (!preg_match('@^/typeahead/common/(.*)/$@', $datasource, $matches)) {
      return null;
    }

    $request = $matches[1];

    $map = array(
      'usersorprojects' => pht('Type a user or project name...'),
      'searchowner'     => pht('Type a user name...'),
      'searchproject'   => pht('Type a project name...'),
      'accountsorprojects' => pht('Type a user or project name...'),
      'usersprojectsorpackages' =>
        pht('Type a user, project, or package name...'),
    );

    return idx($map, $request);
  }


}
