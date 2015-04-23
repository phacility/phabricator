<?php

final class PhabricatorSearchDocumentTypeDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Document Types');
  }

  public function getPlaceholderText() {
    return pht('Select a document type...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  public function renderTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  private function buildResults() {
    $types =
      PhabricatorSearchApplicationSearchEngine::getIndexableDocumentTypes();

    $icons = mpull(
      PhabricatorPHIDType::getAllTypes(),
      'getTypeIcon',
      'getTypeConstant');

    $results = array();
    foreach ($types as $type => $name) {
      $results[$type] = id(new PhabricatorTypeaheadResult())
        ->setPHID($type)
        ->setName($name)
        ->setIcon(idx($icons, $type));
    }

    return $results;
  }

}
