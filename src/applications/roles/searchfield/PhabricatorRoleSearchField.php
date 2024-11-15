<?php

final class PhabricatorRoleSearchField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function newDatasource() {
    return new PhabricatorRoleLogicalDatasource();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $list = $this->getListFromRequest($request, $key);

    $phids = array();
    $slugs = array();
    $role_type = PhabricatorRoleRolePHIDType::TYPECONST;
    foreach ($list as $item) {
      $type = phid_get_type($item);
      if ($type == $role_type) {
        $phids[] = $item;
      } else {
        if (PhabricatorTypeaheadDatasource::isFunctionToken($item)) {
          // If this is a function, pass it through unchanged; we'll evaluate
          // it later.
          $phids[] = $item;
        } else {
          $slugs[] = $item;
        }
      }
    }

    if ($slugs) {
      $roles = id(new PhabricatorRoleQuery())
        ->setViewer($this->getViewer())
        ->withSlugs($slugs)
        ->execute();
      foreach ($roles as $role) {
        $phids[] = $role->getPHID();
      }
      $phids = array_unique($phids);
    }

    return $phids;

  }

  protected function newConduitParameterType() {
    return new ConduitRoleListParameterType();
  }

}
