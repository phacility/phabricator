<?php

abstract class PhabricatorSearchTokenizerField
  extends PhabricatorSearchField {

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getListFromRequest($request, $key);
  }

  public function getValueForQuery($value) {
    return $this->newDatasource()
      ->setViewer($this->getViewer())
      ->evaluateTokens($value);
  }

  protected function newControl() {
    return id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());
  }


  abstract protected function newDatasource();

}
