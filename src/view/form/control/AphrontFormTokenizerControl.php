<?php

final class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;
  private $user;
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

  public function setUser($user) {
    $this->user = $user;
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

    $placeholder = null;
    if (!$this->placeholder) {
      $placeholder = $this->getDefaultPlaceholder();
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
        'placeholder' => $placeholder,
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
      'users'           => 'Type a user name...',
      'usersorprojects' => 'Type a user or project name...',
      'searchowner'     => 'Type a user name...',
      'accounts'        => 'Type a user name...',
      'mailable'        => 'Type a user or mailing list...',
      'allmailable'     => 'Type a user or mailing list...',
      'searchproject'   => 'Type a project name...',
      'projects'        => 'Type a project name...',
      'repositories'    => 'Type a repository name...',
      'packages'        => 'Type a package name...',
      'arcanistproject' => 'Type an arc project name...',
    );

    return idx($map, $request);
  }


}
