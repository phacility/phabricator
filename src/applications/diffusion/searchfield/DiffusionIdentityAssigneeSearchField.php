<?php

final class DiffusionIdentityAssigneeSearchField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getUsersFromRequest($request, $key);
  }

  protected function newDatasource() {
    return new DiffusionIdentityAssigneeDatasource();
  }

  protected function newConduitParameterType() {
    return new ConduitUserListParameterType();
  }

}
