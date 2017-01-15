<?php

final class PhabricatorEditEngineDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Forms');
  }

  public function getPlaceholderText() {
    return pht('Type a form name...');
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
    $query = id(new PhabricatorEditEngineConfigurationQuery());

    $forms = $this->executeQuery($query);
    $results = array();
    foreach ($forms as $form) {
      if ($form->getID()) {
        $key = $form->getEngineKey().'/'.$form->getID();
      } else {
        $key = $form->getEngineKey().'/'.$form->getBuiltinKey();
      }
      $result = id(new PhabricatorTypeaheadResult())
        ->setName($form->getName())
        ->setPHID($key)
        ->setIcon($form->getIcon());

      if ($form->getIsDisabled()) {
        $result->setClosed(pht('Archived'));
      }

      if ($form->getIsDefault()) {
        $result->addAttribute(pht('Create Form'));
      }

      if ($form->getIsEdit()) {
        $result->addAttribute(pht('Edit Form'));
      }

      $results[$key] = $result;
    }

    return $results;
  }

}
