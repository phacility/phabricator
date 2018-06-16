<?php

final class PhabricatorSearchSubscribersField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $allow_types = array(
      PhabricatorProjectProjectPHIDType::TYPECONST,
      PhabricatorOwnersPackagePHIDType::TYPECONST,
    );
    return $this->getUsersFromRequest($request, $key, $allow_types);
  }

  protected function newDatasource() {
    return new PhabricatorMetaMTAMailableFunctionDatasource();
  }

  protected function newConduitParameterType() {
    // TODO: Ideally, this should eventually be a "Subscribers" type which
    // accepts projects as well.
    return new ConduitUserListParameterType();
  }

}
