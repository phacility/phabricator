<?php

final class ConpherenceThreadSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Threads');
  }

  public function getApplicationClassName() {
    return 'PhabricatorConpherenceApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'participantPHIDs',
      $this->readUsersFromRequest($request, 'participants'));

    $saved->setParameter(
      'threadType',
      $request->getStr('threadType'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ConpherenceThreadQuery())
      ->needParticipantCache(true);

    $participant_phids = $saved->getParameter('participantPHIDs', array());
    if ($participant_phids && is_array($participant_phids)) {
      $query->withParticipantPHIDs($participant_phids);
    }

    $thread_type = $saved->getParameter('threadType');
    if (idx($this->getTypeOptions(), $thread_type)) {
      switch ($thread_type) {
        case 'rooms':
          $query->withIsRoom(true);
          break;
        case 'messages':
          $query->withIsRoom(false);
          break;
        case 'both':
          $query->withIsRoom(null);
          break;
      }
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $participant_phids = $saved->getParameter('participantPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('participants')
          ->setLabel(pht('Participants'))
          ->setValue($participant_phids))
      ->appendControl(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Type'))
        ->setName('threadType')
        ->setOptions($this->getTypeOptions())
        ->setValue($saved->getParameter('threadType')));
  }

  protected function getURI($path) {
    return '/conpherence/search/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names = array(
      'all' => pht('All Rooms'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['participant'] = pht('Joined Rooms');
      $names['messages'] = pht('All Messages');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        $query->setParameter('threadType', 'rooms');
        return $query;
      case 'participant':
        $query->setParameter('threadType', 'rooms');
        return $query->setParameter(
          'participantPHIDs',
          array($this->requireViewer()->getPHID()));
      case 'messages':
        $query->setParameter('threadType', 'messages');
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

    $policy_objects = ConpherenceThread::loadPolicyObjects(
      $viewer,
      $conpherences);

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($conpherences as $conpherence) {
      $created = phabricator_date($conpherence->getDateCreated(), $viewer);
      $data = $conpherence->getDisplayData($viewer);
      $title = $data['title'];

      if ($conpherence->getIsRoom()) {
        $icon_name = $conpherence->getPolicyIconName($policy_objects);
      } else {
        $icon_name = 'fa-envelope-o';
      }
      $icon = id(new PHUIIconView())
        ->setIconFont($icon_name);
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
            $icon,
            ' ',
            pht(
              'Last updated %s',
              phabricator_datetime($conpherence->getDateModified(), $viewer)),
          ));

      $list->addItem($item);
    }

    return $list;
  }

  private function getTypeOptions() {
    return array(
      'rooms' => pht('Rooms'),
      'messages' => pht('Messages'),
      'both' => pht('Both'),);
  }

}
