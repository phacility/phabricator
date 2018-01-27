<?php

abstract class PhabricatorTokenizerEditField
  extends PhabricatorPHIDListEditField {

  abstract protected function newDatasource();

  protected function newControl() {
    $control = id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());

    $initial_value = $this->getInitialValue();
    if ($initial_value !== null) {
      $control->setInitialValue($initial_value);
    }

    if ($this->getIsSingleValue()) {
      $control->setLimit(1);
    }

    return $control;
  }

  protected function getInitialValueFromSubmit(AphrontRequest $request, $key) {
    return $request->getArr($key.'.initial');
  }

  protected function newEditType() {
    $type = parent::newEditType();

    $datasource = $this->newDatasource()
      ->setViewer($this->getViewer());
    $type->setDatasource($datasource);

    return $type;
  }

  protected function newCommentAction() {
    $viewer = $this->getViewer();

    $datasource = $this->newDatasource()
      ->setViewer($viewer);

    $action = id(new PhabricatorEditEngineTokenizerCommentAction())
      ->setDatasource($datasource);

    if ($this->getIsSingleValue()) {
      $action->setLimit(1);
    }

    $initial_value = $this->getInitialValue();
    if ($initial_value !== null) {
      $action->setInitialValue($initial_value);
    }

    return $action;
  }

  protected function newBulkParameterType() {
    $datasource = $this->newDatasource()
      ->setViewer($this->getViewer());

    if ($this->getIsSingleValue()) {
      $datasource->setLimit(1);
    }

    return id(new BulkTokenizerParameterType())
      ->setDatasource($datasource);
  }

}
