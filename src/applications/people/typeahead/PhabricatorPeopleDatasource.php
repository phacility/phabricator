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

  public function getBrowseTitle() {
    return pht('Browse Users');
  }

  public function getPlaceholderText() {
    return pht('Type a username...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $tokens = $this->getTokens();

    $query = id(new PhabricatorPeopleQuery())
      ->setOrderVector(array('username'));

    if ($tokens) {
      $query->withNameTokens($tokens);
    }

    $users = $this->executeQuery($query);

    if ($this->enrichResults && $users) {
      $phids = mpull($users, 'getPHID');
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    }

    $results = array();
    foreach ($users as $user) {
      $closed = null;
      if ($user->getIsDisabled()) {
        $closed = pht('Disabled');
      } else if ($user->getIsSystemAgent()) {
        $closed = pht('Bot');
      } else if ($user->getIsMailingList()) {
        $closed = pht('Mailing List');
      }

      $username = $user->getUsername();

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($user->getFullName())
        ->setURI('/p/'.$username.'/')
        ->setPHID($user->getPHID())
        ->setPriorityString($username)
        ->setPriorityType('user')
        ->setAutocomplete('@'.$username)
        ->setClosed($closed);

      if ($user->getIsMailingList()) {
        $result->setIcon('fa-envelope-o');
      }

      if ($this->enrichResults) {
        $display_type = pht('User');
        if ($user->getIsAdmin()) {
          $display_type = pht('Administrator');
        }
        $result->setDisplayType($display_type);
        $result->setImageURI($handles[$user->getPHID()]->getImageURI());
      }

      $results[] = $result;
    }

    return $results;
  }

}
