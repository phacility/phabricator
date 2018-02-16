<?php

final class PhabricatorPeopleDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function newJumpURI($query) {
    $viewer = $this->getViewer();

    // Send "u" to the user directory.
    if (preg_match('/^u\z/i', $query)) {
      return '/people/';
    }

    // Send "u <string>" to the user's profile page.
    $matches = null;
    if (preg_match('/^u\s+(.+)\z/i', $query, $matches)) {
      $raw_query = $matches[1];

      // TODO: We could test that this is a valid username and jump to
      // a search in the user directory if it isn't.

      return urisprintf(
        '/p/%s/',
        $raw_query);
    }

    return null;
  }

}
