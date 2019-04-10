<?php

final class PhrictionDocumentDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Documents');
  }

  public function getPlaceholderText() {
    return pht('Type a document name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();

    $query = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->needContent(true);

    $this->applyFerretConstraints(
      $query,
      id(new PhrictionDocument())->newFerretEngine(),
      'title',
      $this->getRawQuery());

    $documents = $query->execute();

    $results = array();
    foreach ($documents as $document) {
      $content = $document->getContent();

      if (!$document->isActive()) {
        $closed = $document->getStatusDisplayName();
      } else {
        $closed = null;
      }

      $slug = $document->getSlug();
      $title = $content->getTitle();

      $sprite = 'phabricator-search-icon phui-font-fa phui-icon-view fa-book';
      $autocomplete = '[[ '.$slug.' ]]';

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($title)
        ->setDisplayName($title)
        ->setURI($document->getURI())
        ->setPHID($document->getPHID())
        ->setDisplayType($slug)
        ->setPriorityType('wiki')
        ->setImageSprite($sprite)
        ->setAutocomplete($autocomplete)
        ->setClosed($closed);

      $results[] = $result;
    }

    return $results;
  }

}
