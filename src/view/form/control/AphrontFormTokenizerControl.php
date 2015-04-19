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

    $datasource = $this->datasource;
    if ($datasource) {
      $datasource->setViewer($this->getUser());
    }

    $placeholder = null;
    if (!strlen($this->placeholder)) {
      if ($datasource) {
        $placeholder = $datasource->getPlaceholderText();
      }
    } else {
      $placeholder = $this->placeholder;
    }

    $tokens = array();
    $values = nonempty($this->getValue(), array());
    foreach ($values as $value) {
      if (isset($handles[$value])) {
        $token = PhabricatorTypeaheadTokenView::newFromHandle($handles[$value]);
      } else {
        $token = null;
        if ($datasource) {
          $function = $datasource->parseFunction($value);
          if ($function) {
            $token_list = $datasource->renderFunctionTokens(
              $function['name'],
              array($function['argv']));
            $token = head($token_list);
          }
        }

        if (!$token) {
          $name = pht('Invalid Function: %s', $value);
          $token = $datasource->newInvalidToken($name);
        }

        $type = $token->getTokenType();
        if ($type == PhabricatorTypeaheadTokenView::TYPE_INVALID) {
          $token->setKey($value);
        }
      }
      $token->setInputName($this->getName());
      $tokens[] = $token;
    }

    $template = new AphrontTokenizerTemplateView();
    $template->setName($name);
    $template->setID($id);
    $template->setValue($tokens);

    $username = null;
    if ($this->user) {
      $username = $this->user->getUsername();
    }

    $datasource_uri = null;
    $browse_uri = null;
    if ($datasource) {
      $datasource->setViewer($this->getUser());

      $datasource_uri = $datasource->getDatasourceURI();

      $browse_uri = $datasource->getBrowseURI();
      if ($browse_uri) {
        $template->setBrowseURI($browse_uri);
      }
    }

    if (!$this->disableBehavior) {
      Javelin::initBehavior('aphront-basic-tokenizer', array(
        'id' => $id,
        'src' => $datasource_uri,
        'value' => mpull($tokens, 'getValue', 'getKey'),
        'icons' => mpull($tokens, 'getIcon', 'getKey'),
        'types' => mpull($tokens, 'getTokenType', 'getKey'),
        'colors' => mpull($tokens, 'getColor', 'getKey'),
        'limit' => $this->limit,
        'username' => $username,
        'placeholder' => $placeholder,
        'browseURI' => $browse_uri,
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

      $phids = array();
      foreach ($values as $value) {
        if (!PhabricatorTypeaheadDatasource::isFunctionToken($value)) {
          $phids[] = $value;
        }
      }

      $this->handles = $viewer->loadHandles($phids);
    }

    return $this->handles;
  }

}
