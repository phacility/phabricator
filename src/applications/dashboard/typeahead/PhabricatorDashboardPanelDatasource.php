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
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }


  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  public function buildResults() {
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
      $id = $panel->getID();
      $monogram = $panel->getMonogram();
      $properties = $panel->getProperties();

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($panel->getName())
        ->setDisplayName($monogram.' '.$panel->getName())
        ->setPHID($id)
        ->setIcon($impl->getIcon())
        ->addAttribute($type_text);

      if (!empty($properties['class'])) {
        $result->addAttribute($properties['class']);
      }

      if ($panel->getIsArchived()) {
        $result->setClosed(pht('Archived'));
      }

      $results[$id] = $result;
    }

    return $results;
  }

}
