<?php

final class PhabricatorOAuthServerScope {

  const SCOPE_OFFLINE_ACCESS = 'offline_access';
  const SCOPE_WHOAMI         = 'whoami';
  const SCOPE_NOT_ACCESSIBLE = 'not_accessible';

  /*
   * Note this does not contain SCOPE_NOT_ACCESSIBLE which is magic
   * used to simplify code for data that is not currently accessible
   * via OAuth.
   */
  static public function getScopesDict() {
    return array(
      self::SCOPE_OFFLINE_ACCESS => 1,
      self::SCOPE_WHOAMI         => 1,
    );
  }

  static public function getCheckboxControl($current_scopes) {
    $scopes = self::getScopesDict();
    $scope_keys = array_keys($scopes);
    sort($scope_keys);

    $checkboxes = new AphrontFormCheckboxControl();
    foreach ($scope_keys as $scope) {
      $checkboxes->addCheckbox(
        $name = $scope,
        $value = 1,
        $label = self::getCheckboxLabel($scope),
        $checked = isset($current_scopes[$scope])
      );
    }
    $checkboxes->setLabel('Scope');

    return $checkboxes;
  }

  static private function getCheckboxLabel($scope) {
    $label = null;
    switch ($scope) {
      case self::SCOPE_OFFLINE_ACCESS:
        $label = 'Make access tokens granted to this client never expire.';
        break;
      case self::SCOPE_WHOAMI:
        $label = 'Read access to Conduit method user.whoami.';
        break;
    }

    return $label;
  }

  static public function getScopesFromRequest(AphrontRequest $request) {
    $scopes = self::getScopesDict();
    $requested_scopes = array();
    foreach ($scopes as $scope => $bit) {
      if ($request->getBool($scope)) {
        $requested_scopes[$scope] = 1;
      }
    }
    return $requested_scopes;
  }

  /**
   * A scopes list is considered valid if each scope is a known scope
   * and each scope is seen only once.  Otherwise, the list is invalid.
   */
  static public function validateScopesList($scope_list) {
    $scopes       = explode(' ', $scope_list);
    $known_scopes = self::getScopesDict();
    $seen_scopes  = array();
    foreach ($scopes as $scope) {
      if (!isset($known_scopes[$scope])) {
        return false;
      }
      if (isset($seen_scopes[$scope])) {
        return false;
      }
      $seen_scopes[$scope] = 1;
    }

    return true;
  }

  /**
   * A scopes dictionary is considered valid if each key is a known scope.
   * Otherwise, the dictionary is invalid.
   */
  static public function validateScopesDict($scope_dict) {
    $known_scopes   = self::getScopesDict();
    $unknown_scopes = array_diff_key($scope_dict,
                                     $known_scopes);
    return empty($unknown_scopes);
  }

  /**
   * Transforms a space-delimited scopes list into a scopes dict. The list
   * should be validated by @{method:validateScopesList} before
   * transformation.
   */
   static public function scopesListToDict($scope_list) {
    $scopes = explode(' ', $scope_list);
    return array_fill_keys($scopes, 1);
  }

}
