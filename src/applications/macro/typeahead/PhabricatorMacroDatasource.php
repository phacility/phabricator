<?php

final class PhabricatorMacroDatasource extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a macro name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $macros = id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE)
      ->execute();

    foreach ($macros as $macro) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setPHID($macro->getPHID())
        ->setName($macro->getName());
    }

    return $results;
  }

}
