<?php

final class PasteLanguageSelectDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Languages');
  }

  public function getPlaceholderText() {
    return pht('Type a language name or leave blank to auto-detect...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }


  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  private function buildResults() {
    $results = array();
    $languages = PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    foreach ($languages as $value => $name) {
      $result = id(new PhabricatorTypeaheadResult())
        ->setPHID($value)
        ->setName($name);

      $results[$value] = $result;
    }
    return $results;
  }

}
