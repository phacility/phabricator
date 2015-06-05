<?php

final class PhabricatorSearchUsersField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function newDatasource() {
    return new PhabricatorPeopleUserFunctionDatasource();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $list = $this->getListFromRequest($request, $key);
    $allow_types = array();

    $phids = array();
    $names = array();
    $allow_types = array_fuse($allow_types);
    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    foreach ($list as $item) {
      $type = phid_get_type($item);
      if ($type == $user_type) {
        $phids[] = $item;
      } else if (isset($allow_types[$type])) {
        $phids[] = $item;
      } else {
        if (PhabricatorTypeaheadDatasource::isFunctionToken($item)) {
          // If this is a function, pass it through unchanged; we'll evaluate
          // it later.
          $phids[] = $item;
        } else {
          $names[] = $item;
        }
      }
    }

    if ($names) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames($names)
        ->execute();
      foreach ($users as $user) {
        $phids[] = $user->getPHID();
      }
      $phids = array_unique($phids);
    }

    return $phids;
  }

}
