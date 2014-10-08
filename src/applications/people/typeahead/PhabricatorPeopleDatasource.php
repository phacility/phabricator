<?php

final class PhabricatorPeopleDatasource
  extends PhabricatorTypeaheadDatasource {

  private $enrichResults;

  /**
   * Controls enriched rendering, for global search. This is a bit hacky and
   * should probably be handled in a more general way, but is fairly reasonable
   * for now.
   */
  public function setEnrichResults($enrich) {
    $this->enrichResults = $enrich;
    return $this;
  }

  public function getPlaceholderText() {
    return pht('Type a username...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $users = array();
    if (strlen($raw_query)) {
      // This is an arbitrary limit which is just larger than any limit we
      // actually use in the application.

      // TODO: The datasource should pass this in the query.
      $limit = 15;

      $user_table = new PhabricatorUser();
      $conn_r = $user_table->establishConnection('r');
      $ids = queryfx_all(
        $conn_r,
        'SELECT id FROM %T WHERE username LIKE %>
          ORDER BY username ASC LIMIT %d',
        $user_table->getTableName(),
        $raw_query,
        $limit);
      $ids = ipull($ids, 'id');

      if (count($ids) < $limit) {
        // If we didn't find enough username hits, look for real name hits.
        // We need to pull the entire pagesize so that we end up with the
        // right number of items if this query returns many duplicate IDs
        // that we've already selected.

        $realname_ids = queryfx_all(
          $conn_r,
          'SELECT DISTINCT userID FROM %T WHERE token LIKE %>
            ORDER BY token ASC LIMIT %d',
          PhabricatorUser::NAMETOKEN_TABLE,
          $raw_query,
          $limit);
        $realname_ids = ipull($realname_ids, 'userID');
        $ids = array_merge($ids, $realname_ids);

        $ids = array_unique($ids);
        $ids = array_slice($ids, 0, $limit);
      }

      // Always add the logged-in user because some tokenizers autosort them
      // first. They'll be filtered out on the client side if they don't
      // match the query.
      if ($viewer->getID()) {
        $ids[] = $viewer->getID();
      }

      if ($ids) {
        $users = id(new PhabricatorPeopleQuery())
          ->setViewer($viewer)
          ->withIDs($ids)
          ->execute();
      }
    }

    if ($this->enrichResults && $users) {
      $phids = mpull($users, 'getPHID');
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    }

    foreach ($users as $user) {
      $closed = null;
      if ($user->getIsDisabled()) {
        $closed = pht('Disabled');
      } else if ($user->getIsSystemAgent()) {
        $closed = pht('Bot/Script');
      }

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($user->getFullName())
        ->setURI('/p/'.$user->getUsername())
        ->setPHID($user->getPHID())
        ->setPriorityString($user->getUsername())
        ->setPriorityType('user')
        ->setClosed($closed);

      if ($this->enrichResults) {
        $display_type = 'User';
        if ($user->getIsAdmin()) {
          $display_type = 'Administrator';
        }
        $result->setDisplayType($display_type);
        $result->setImageURI($handles[$user->getPHID()]->getImageURI());
      }

      $results[] = $result;
    }

    return $results;
  }

}
