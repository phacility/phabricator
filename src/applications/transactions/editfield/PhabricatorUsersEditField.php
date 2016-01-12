<?php

final class PhabricatorUsersEditField
  extends PhabricatorTokenizerEditField {

  protected function newDatasource() {
    return new PhabricatorPeopleDatasource();
  }

  protected function newHTTPParameterType() {
    return new AphrontUserListHTTPParameterType();
  }

  protected function newConduitParameterType() {
    if ($this->getIsSingleValue()) {
      return new ConduitUserParameterType();
    } else {
      return new ConduitUserListParameterType();
    }
  }

}
