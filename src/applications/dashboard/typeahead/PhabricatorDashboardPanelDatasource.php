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
    $query = new PhabricatorDashboardPanelQuery();

    $raw_query = $this->getRawQuery();
    if ($raw_query !== null && preg_match('/^[wW]\d+\z/', $raw_query)) {
      $id = trim($raw_query, 'wW');
      $id = (int)$id;
      $query->withIDs(array($id));
    } else {
      $this->applyFerretConstraints(
        $query,
        id(new PhabricatorDashboardPanel())->newFerretEngine(),
        'title',
        $this->getRawQuery());
    }

    $panels = $this->executeQuery($query);

    $results = array();
    foreach ($panels as $panel) {
      $impl = $panel->getImplementation();
      if ($impl) {
        $type_text = $impl->getPanelTypeName();
        $icon = $impl->getIcon();
      } else {
        $type_text = nonempty($panel->getPanelType(), pht('Unknown Type'));
        $icon = 'fa-question';
      }
      $phid = $panel->getPHID();
      $monogram = $panel->getMonogram();
      $properties = $panel->getProperties();

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($monogram.' '.$panel->getName())
        ->setPHID($phid)
        ->setIcon($icon)
        ->addAttribute($type_text);

      if (!empty($properties['class'])) {
        $result->addAttribute($properties['class']);
      }

      if ($panel->getIsArchived()) {
        $result->setClosed(pht('Archived'));
      }

      $results[$phid] = $result;
    }

    return $results;
  }

}
