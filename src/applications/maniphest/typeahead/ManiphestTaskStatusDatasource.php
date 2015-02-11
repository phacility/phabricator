<?php

final class ManiphestTaskStatusDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a task status name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    foreach ($status_map as $value => $name) {
      // NOTE: $value is not a PHID but is unique. This'll work.
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setPHID($value)
        ->setName($name);
    }

    return $results;
  }
}
