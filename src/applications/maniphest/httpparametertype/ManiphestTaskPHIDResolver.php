<?php

final class ManiphestTaskPHIDResolver
  extends PhabricatorPHIDResolver {

  protected function getResolutionMap(array $names) {
    foreach ($names as $key => $name) {
      if (ctype_digit($name)) {
        $names[$key] = 'T'.$name;
      }
    }

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer());

    $tasks = id(new ManiphestTaskPHIDType())
      ->loadNamedObjects($query, $names);


    $results = array();
    foreach ($tasks as $task) {
      $task_phid = $task->getPHID();
      $results[$task->getID()] = $task_phid;
      $results[$task->getMonogram()] = $task_phid;
    }

    return $results;
  }

}
