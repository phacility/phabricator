<?php

final class HarbormasterBuildDependencyDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type another build step name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();

    $plan_phid = $this->getParameter('planPHID');
    $step_phid = $this->getParameter('stepPHID');

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withBuildPlanPHIDs(array($plan_phid))
      ->execute();
    $steps = mpull($steps, null, 'getPHID');

    if (count($steps) === 0) {
      return array();
    }

    $results = array();
    foreach ($steps as $phid => $step) {
      if ($step->getPHID() === $step_phid) {
        continue;
      }

      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($step->getName())
        ->setURI('/')
        ->setPHID($phid);
    }

    return $results;
  }

}
