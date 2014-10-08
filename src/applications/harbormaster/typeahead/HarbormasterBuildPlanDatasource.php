<?php

final class HarbormasterBuildPlanDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a build plan name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->execute();
    foreach ($plans as $plan) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($plan->getName())
        ->setPHID($plan->getPHID());
    }

    return $results;
  }

}
