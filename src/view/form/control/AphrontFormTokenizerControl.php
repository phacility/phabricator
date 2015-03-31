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

    // Values may either be handles (which are now legacy/deprecated) or
    // strings. Load handles for any PHIDs.
    $load = array();
    $handles = array();
    $select = array();
    foreach ($values as $value) {
      if ($value instanceof PhabricatorObjectHandle) {
        $handles[$value->getPHID()] = $value;
        $select[] = $value->getPHID();
      } else {
        $load[] = $value;
        $select[] = $value;
      }
    }

    // TODO: Once this code path simplifies, move this prefetch to setValue()
    // so we can bulk load across multiple controls.

    if ($load) {
      $viewer = $this->getUser();
      if (!$viewer) {
        // TODO: Clean this up when handles go away.
        throw new Exception(
          pht('Call setUser() before rendering tokenizer string values.'));
      }
      $loaded_handles = $viewer->loadHandles($load);
      $handles = $handles + iterator_to_array($loaded_handles);
    }

    // Reorder the list into input order.
    $handles = array_select_keys($handles, $select);

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

}
