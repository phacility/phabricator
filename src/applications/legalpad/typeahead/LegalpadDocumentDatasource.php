<?php

final class LegalpadDocumentDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a document name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorApplicationLegalpad';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $documents = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->execute();
    foreach ($documents as $document) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setPHID($document->getPHID())
        ->setIcon('fa-file-text-o')
        ->setName($document->getMonogram().' '.$document->getTitle());
    }

    return $results;
  }

}
