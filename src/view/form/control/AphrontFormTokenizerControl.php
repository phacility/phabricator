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

    if ($this->getID()) {
      $id = $this->getID();
    } else {
      $id = celerity_generate_unique_node_id();
    }

    if (!$this->placeholder) {
      $this->placeholder = $this->getDefaultPlaceholder();
    }

    $template = new AphrontTokenizerTemplateView();
    $template->setName($name);
    $template->setID($id);
    $template->setValue($values);

    $username = null;
    if ($this->user) {
      $username = $this->user->getUsername();
    }

    if (!$this->disableBehavior) {
      Javelin::initBehavior('aphront-basic-tokenizer', array(
        'id'          => $id,
        'src'         => $this->datasource,
        'value'       => $values,
        'limit'       => $this->limit,
        'ondemand'    => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        'username'    => $username,
        'placeholder' => $this->placeholder,
      ));
    }

    return $template->render();
  }

  private function getDefaultPlaceholder() {
    $datasource = $this->datasource;

    $matches = null;
    if (!preg_match('@^/typeahead/common/(.*)/$@', $datasource, $matches)) {
      return null;
    }

    $request = $matches[1];

    $map = array(
      'users'           => pht('Type a user name...'),
      'usersorprojects' => pht('Type a user or project name...'),
      'searchowner'     => pht('Type a user name...'),
      'accounts'        => pht('Type a user name...'),
      'mailable'        => pht('Type a user or mailing list...'),
      'allmailable'     => pht('Type a user or mailing list...'),
      'searchproject'   => pht('Type a project name...'),
      'projects'        => pht('Type a project name...'),
      'repositories'    => pht('Type a repository name...'),
      'packages'        => pht('Type a package name...'),
      'arcanistproject' => pht('Type an arc project name...'),
    );

    return idx($map, $request);
  }


}
