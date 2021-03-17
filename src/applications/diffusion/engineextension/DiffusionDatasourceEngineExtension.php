<?php

final class DiffusionDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new DiffusionRepositoryDatasource(),
      new DiffusionSymbolDatasource(),
    );
  }

  public function newJumpURI($query) {
    $viewer = $this->getViewer();

    // Send "r" to Diffusion.
    if (preg_match('/^r\z/i', $query)) {
      return '/diffusion/';
    }

    // Send "a" to the commit list ("Audit").
    if (preg_match('/^a\z/i', $query)) {
      return '/diffusion/commit/';
    }

    // Send "r <string>" to a search for a matching repository.
    $matches = null;
    if (preg_match('/^r\s+(.+)\z/i', $query, $matches)) {
      $raw_query = $matches[1];

      $engine = id(new PhabricatorRepository())
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

      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withFerretConstraint($engine, $fulltext_tokens)
        ->execute();
      if (count($repositories) == 1) {
        // Just one match, jump to repository.
        return head($repositories)->getURI();
      } else {
        // More than one match, jump to search.
        return urisprintf(
          '/diffusion/?order=relevance&query=%s#R',
          $raw_query);
      }
    }

    // Send "s <string>" to a symbol search.
    $matches = null;
    if (preg_match('/^s\s+(.+)\z/i', $query, $matches)) {
      $symbol = $matches[1];

      $parts = null;
      if (preg_match('/(.*)(?:\\.|::|->)(.*)/', $symbol, $parts)) {
        return urisprintf(
          '/diffusion/symbol/%p/?jump=true&context=%s',
          $parts[2],
          $parts[1]);
      } else {
        return urisprintf(
          '/diffusion/symbol/%p/?jump=true',
          $symbol);
      }
    }

    return null;
  }

}
