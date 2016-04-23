<?php

final class PhabricatorOAuthServerScope extends Phobject {

  public static function getScopeMap() {
    return array();
  }

  public static function filterScope(array $scope) {
    $valid_scopes = self::getScopeMap();

    foreach ($scope as $key => $scope_item) {
      if (!isset($valid_scopes[$scope_item])) {
        unset($scope[$key]);
      }
    }

    return $scope;
  }

}
