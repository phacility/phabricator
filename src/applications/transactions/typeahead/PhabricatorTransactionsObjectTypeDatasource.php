<?php

final class PhabricatorTransactionsObjectTypeDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Forms');
  }

  public function getPlaceholderText() {
    return pht('Type an object type name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  private function buildResults() {
    $queries = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationTransactionQuery')
      ->execute();

    $phid_types = PhabricatorPHIDType::getAllTypes();

    $results = array();
    foreach ($queries as $query) {
      $query_type = $query->getTemplateApplicationTransaction()
        ->getApplicationTransactionType();

      $phid_type = idx($phid_types, $query_type);

      if ($phid_type) {
        $name = $phid_type->getTypeName();
        $icon = $phid_type->getTypeIcon();
      } else {
        $name = pht('%s ("%s")', $query_type, get_class($query));
        $icon = null;
      }

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($name)
        ->setPHID($query_type);

      if ($icon) {
        $result->setIcon($icon);
      }

      $results[$query_type] = $result;
    }

    return $results;
  }

}
