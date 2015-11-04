<?php

final class PhabricatorUserPHIDResolver
  extends PhabricatorPHIDResolver {

  protected function getResolutionMap(array $names) {
    // Pick up the normalization and case rules from the PHID type query.

    foreach ($names as $key => $name) {
      $names[$key] = '@'.$name;
    }

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer());

    $users = id(new PhabricatorPeopleUserPHIDType())
      ->loadNamedObjects($query, $names);

    $results = array();
    foreach ($users as $at_username => $user) {
      $results[substr($at_username, 1)] = $user->getPHID();
    }

    return $results;
  }

}
