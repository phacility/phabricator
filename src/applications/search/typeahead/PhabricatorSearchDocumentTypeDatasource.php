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
    $results = $this->buildResults();
    $results = array_select_keys($results, $values);

    $tokens = array();
    foreach ($results as $result) {
      $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $result);
    }

    return $tokens;
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
