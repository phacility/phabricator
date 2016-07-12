<?php

final class PhabricatorUsersSearchField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getUsersFromRequest($request, $key);
  }

  protected function newDatasource() {
    return new PhabricatorPeopleUserFunctionDatasource();
  }

  protected function newConduitParameterType() {
    return new ConduitUserListParameterType();
  }

}
