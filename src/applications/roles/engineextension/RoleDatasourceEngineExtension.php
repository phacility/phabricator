<?php

final class RoleDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorRoleDatasource(),
    );
  }

  public function newJumpURI($query) {
    $viewer = $this->getViewer();

    // Send "p" to Roles.
    if (preg_match('/^p\z/i', $query)) {
      return '/diffusion/';
    }

    // Send "p <string>" to a search for similar roles.
    $matches = null;
    if (preg_match('/^p\s+(.+)\z/i', $query, $matches)) {
      $raw_query = $matches[1];

      $engine = id(new PhabricatorRole())
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

      $roles = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withFerretConstraint($engine, $fulltext_tokens)
        ->execute();
      if (count($roles) == 1) {
        // Just one match, jump to role.
        return head($roles)->getURI();
      } else {
        // More than one match, jump to search.
        return urisprintf(
          '/role/?order=relevance&query=%s#R',
          $raw_query);
      }
    }

    return null;
  }
}
