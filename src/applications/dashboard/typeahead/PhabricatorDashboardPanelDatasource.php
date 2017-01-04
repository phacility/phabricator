<?php

final class PhabricatorDashboardPanelDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Dashboard Panels');
  }

  public function getPlaceholderText() {
    return pht('Type a panel name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  public function loadResults() {
    $query = id(new PhabricatorDashboardPanelQuery());

    $panels = $this->executeQuery($query);
    $results = array();
    foreach ($panels as $panel) {
      $impl = $panel->getImplementation();
      if ($impl) {
        $type_text = $impl->getPanelTypeName();
      } else {
        $type_text = nonempty($panel->getPanelType(), pht('Unknown Type'));
      }

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($panel->getName())
        ->setPHID($panel->getPHID())
        ->addAttribute($type_text);

      if ($panel->getIsArchived()) {
        $result->setClosed(pht('Archived'));
      }

      $results[] = $result;
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
