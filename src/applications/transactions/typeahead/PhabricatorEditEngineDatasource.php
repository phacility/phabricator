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

  public function loadResults() {
    $query = id(new PhabricatorEditEngineConfigurationQuery());

    $forms = $this->executeQuery($query);
    $results = array();
    foreach ($forms as $form) {

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($form->getName())
        ->setPHID($form->getPHID());

      if ($form->getIsDisabled()) {
        $result->setClosed(pht('Archived'));
      }

      $results[] = $result;
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
