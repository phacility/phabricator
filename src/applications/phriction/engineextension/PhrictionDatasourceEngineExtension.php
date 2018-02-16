<?php

final class PhrictionDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhrictionDocumentDatasource(),
    );
  }

  public function newJumpURI($query) {
    $viewer = $this->getViewer();

    // Send "w" to Phriction.
    if (preg_match('/^w\z/i', $query)) {
      return '/w/';
    }

    // Send "w <string>" to a search for similar wiki documents.
    $matches = null;
    if (preg_match('/^w\s+(.+)\z/i', $query, $matches)) {
      $raw_query = $matches[1];

      $engine = id(new PhrictionDocument())
        ->newFerretEngine();

      $compiler = id(new PhutilSearchQueryCompiler())
        ->setEnableFunctions(true);

      $raw_tokens = $compiler->newTokens($raw_query);

      $fulltext_tokens = array();
      foreach ($raw_tokens as $raw_token) {
        $fulltext_token = id(new PhabricatorFulltextToken())
          ->setToken($raw_token);
        $fulltext_tokens[] = $fulltext_token;
      }

      $documents = id(new PhrictionDocumentQuery())
        ->setViewer($viewer)
        ->withFerretConstraint($engine, $fulltext_tokens)
        ->execute();
      if (count($documents) == 1) {
        return head($documents)->getURI();
      } else {
        // More than one match, jump to search.
        return urisprintf(
          '/phriction/?order=relevance&query=%s#R',
          $raw_query);
      }
    }

    return null;
  }
}
