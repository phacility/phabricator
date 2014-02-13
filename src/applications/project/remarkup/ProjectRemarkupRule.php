<?php

/**
 * @group project
 */
final class ProjectRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return '#';
  }

  protected function getObjectIDPattern() {
    return
      PhabricatorProjectPHIDTypeProject::getProjectMonogramPatternFragment();
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    // Put the "#" back on the front of these IDs.
    $names = array();
    foreach ($ids as $id) {
      $names[] = '#'.$id;
    }

    // Issue a query by object name.
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names);

    $query->execute();
    $projects = $query->getNamedResults();

    // Slice the "#" off again.
    $result = array();
    foreach ($projects as $name => $project) {
      $result[substr($name, 1)] = $project;
    }

    return $result;
  }

}
