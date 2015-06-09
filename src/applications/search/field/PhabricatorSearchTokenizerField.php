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


  protected function getUsersFromRequest(
    AphrontRequest $request,
    $key,
    array $allow_types = array()) {
    $list = $this->getListFromRequest($request, $key);

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
