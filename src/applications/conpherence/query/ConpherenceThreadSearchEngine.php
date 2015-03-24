<?php

final class ConpherenceThreadSearchEngine
  extends PhabricatorApplicationSearchEngine {

  // For now, we only search for rooms, but write this code so its easy to
  // change that decision later
  private $isRooms = true;

  public function setIsRooms($bool) {
    $this->isRooms = $bool;
    return $this;
  }

  public function getResultTypeDescription() {
    if ($this->isRooms) {
      $type = pht('Rooms');
    } else {
      $type = pht('Threads');
    }
    return $type;
  }

  public function getApplicationClassName() {
    return 'PhabricatorConpherenceApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'participantPHIDs',
      $this->readUsersFromRequest($request, 'participants'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ConpherenceThreadQuery())
      ->withIsRoom($this->isRooms)
      ->needParticipantCache(true);

    $participant_phids = $saved->getParameter('participantPHIDs', array());
    if ($participant_phids && is_array($participant_phids)) {
      $query->withParticipantPHIDs($participant_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $phids = $saved->getParameter('participantPHIDs', array());
    $participant_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();
    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setDatasource(new PhabricatorPeopleDatasource())
        ->setName('participants')
        ->setLabel(pht('Participants'))
        ->setValue($participant_handles));
  }

  protected function getURI($path) {
    if ($this->isRooms) {
      return '/conpherence/room/'.$path;
    } else {
      // TODO - will need a path if / when "thread" search happens
    }
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->isRooms) {
      $names = array(
        'all' => pht('All Rooms'),
      );

      if ($this->requireViewer()->isLoggedIn()) {
        $names['participant'] = pht('Participated');
      }
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'participant':
        return $query->setParameter(
          'participantPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $conpherences,
    PhabricatorSavedQuery $query) {

    $recent = mpull($conpherences, 'getRecentParticipantPHIDs');
    return array_unique(array_mergev($recent));
  }

  protected function renderResultList(
    array $conpherences,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($conpherences, 'ConpherenceThread');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($conpherences as $conpherence) {
      $created = phabricator_date($conpherence->getDateCreated(), $viewer);
      $data = $conpherence->getDisplayData($viewer);
      $title = $data['title'];

      $item = id(new PHUIObjectItemView())
        ->setObjectName($conpherence->getMonogram())
        ->setHeader($title)
        ->setHref('/conpherence/'.$conpherence->getID().'/')
        ->setObject($conpherence)
        ->addIcon('none', $created)
        ->addIcon(
          'none',
          pht('Messages: %d', $conpherence->getMessageCount()))
        ->addAttribute(
          array(
            id(new PHUIIconView())->setIconFont('fa-envelope-o', 'green'),
            ' ',
            pht(
              'Last updated %s',
              phabricator_datetime($conpherence->getDateModified(), $viewer)),
          ));

      $list->addItem($item);
    }

    return $list;
  }
}
