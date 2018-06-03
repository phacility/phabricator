<?php

final class DiffusionIdentityAssigneeEditField
  extends PhabricatorTokenizerEditField {

  protected function newDatasource() {
    return new DiffusionIdentityAssigneeDatasource();
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
