<?php

final class ManiphestTaskPriorityDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a task priority name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorApplicationManiphest';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
    foreach ($priority_map as $value => $name) {
      // NOTE: $value is not a phid but is unique. This'll work.
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setPHID($value)
        ->setName($name);
    }

    return $results;
  }

}
