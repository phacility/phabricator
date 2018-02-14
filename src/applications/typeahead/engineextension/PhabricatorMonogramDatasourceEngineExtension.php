<?php

final class PhabricatorMonogramDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorTypeaheadMonogramDatasource(),
    );
  }

  public function newJumpURI($query) {
    $viewer = $this->getViewer();

    // These first few rules are sort of random but don't fit anywhere else
    // today and don't feel worth adding separate extensions for.

    // Send "f" to Feed.
    if (preg_match('/^f\z/i', $query)) {
      return '/feed/';
    }

    // Send "d" to Differential.
    if (preg_match('/^d\z/i', $query)) {
      return '/differential/';
    }

    // Send "t" to Maniphest.
    if (preg_match('/^t\z/i', $query)) {
      return '/maniphest/';
    }

    // Otherwise, if the user entered an object name, jump to that object.
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($query))
      ->execute();
    if (count($objects) == 1) {
      $object = head($objects);
      $object_phid = $object->getPHID();

      $handles = $viewer->loadHandles(array($object_phid));
      $handle = $handles[$object_phid];

      if ($handle->isComplete()) {
        return $handle->getURI();
      }
    }

    return null;
  }

}
