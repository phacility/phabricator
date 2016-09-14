<?php

final class HeraldRuleDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a Herald rule name...');
  }

  public function getBrowseTitle() {
    return pht('Browse Herald Rules');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withDatasourceQuery($raw_query)
      ->execute();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(mpull($rules, 'getPHID'))
      ->execute();

    $results = array();
    foreach ($rules as $rule) {
      $handle = $handles[$rule->getPHID()];

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($handle->getFullName())
        ->setPHID($handle->getPHID());

      if ($rule->getIsDisabled()) {
        $result->setClosed(pht('Archived'));
      }

      $results[] = $result;
    }

    return $results;
  }
}
