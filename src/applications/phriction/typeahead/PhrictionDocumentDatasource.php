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

    $raw_query = $this->getRawQuery();

    $engine = id(new PhrictionDocument())
      ->newFerretEngine();

    $compiler = id(new PhutilSearchQueryCompiler())
      ->setEnableFunctions(true);

    $raw_tokens = $compiler->newTokens($raw_query);

    $fulltext_tokens = array();
    foreach ($raw_tokens as $raw_token) {

      // This is a little hacky and could maybe be cleaner. We're treating
      // every search term as though the user had entered "title:dog" insead
      // of "dog".

      $alternate_token = PhutilSearchQueryToken::newFromDictionary(
        array(
          'quoted' => $raw_token->isQuoted(),
          'value' => $raw_token->getValue(),
          'operator' => PhutilSearchQueryCompiler::OPERATOR_SUBSTRING,
          'function' => 'title',
        ));

      $fulltext_token = id(new PhabricatorFulltextToken())
        ->setToken($alternate_token);
      $fulltext_tokens[] = $fulltext_token;
    }

    $documents = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withFerretConstraint($engine, $fulltext_tokens)
      ->needContent(true)
      ->execute();

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
