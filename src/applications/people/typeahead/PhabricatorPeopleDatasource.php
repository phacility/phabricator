<?php

final class PhabricatorPeopleDatasource
  extends PhabricatorTypeaheadDatasource {

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

    $query = id(new PhabricatorPeopleQuery())
      ->setOrderVector(array('username'));

    if ($this->getPhase() == self::PHASE_PREFIX) {
      $prefix = $this->getPrefixQuery();
      $query->withNamePrefixes(array($prefix));
    } else {
      $tokens = $this->getTokens();
      if ($tokens) {
        $query->withNameTokens($tokens);
      }
    }

    $users = $this->executeQuery($query);

    $is_browse = $this->getIsBrowse();

    if ($is_browse && $users) {
      $phids = mpull($users, 'getPHID');
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    }

    $results = array();
    foreach ($users as $user) {
      $phid = $user->getPHID();

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
        ->setPHID($phid)
        ->setPriorityString($username)
        ->setPriorityType('user')
        ->setAutocomplete('@'.$username)
        ->setClosed($closed);

      if ($user->getIsMailingList()) {
        $result->setIcon('fa-envelope-o');
      }

      if ($is_browse) {
        $handle = $handles[$phid];

        $result
          ->setIcon($handle->getIcon())
          ->setImageURI($handle->getImageURI())
          ->addAttribute($handle->getSubtitle());

        if ($user->getIsAdmin()) {
          $result->addAttribute(
            array(
              id(new PHUIIconView())->setIcon('fa-star'),
              ' ',
              pht('Administrator'),
            ));
        }

        if ($user->getIsAdmin()) {
          $display_type = pht('Administrator');
        } else {
          $display_type = pht('User');
        }
        $result->setDisplayType($display_type);
      }

      $results[] = $result;
    }

    return $results;
  }

}
