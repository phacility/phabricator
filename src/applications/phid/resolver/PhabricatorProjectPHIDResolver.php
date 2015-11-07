<?php

final class PhabricatorProjectPHIDResolver
  extends PhabricatorPHIDResolver {

  protected function getResolutionMap(array $names) {
    // This is a little awkward but we want to pick up the normalization
    // rules from the PHIDType. This flow could perhaps be made cleaner.

    foreach ($names as $key => $name) {
      $names[$key] = '#'.$name;
    }

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer());

    $projects = id(new PhabricatorProjectProjectPHIDType())
      ->loadNamedObjects($query, $names);

    $results = array();
    foreach ($projects as $hashtag => $project) {
      $results[substr($hashtag, 1)] = $project->getPHID();
    }

    return $results;
  }

}
