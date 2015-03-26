<?php

final class ConpherenceThreadListView extends AphrontView {

  private $baseURI;
  private $threads;
  private $scrollUpParticipant;
  private $scrollDownParticipant;

  public function setThreads(array $threads) {
    assert_instances_of($threads, 'ConpherenceThread');
    $this->threads = $threads;
    return $this;
  }

  public function setScrollUpParticipant(
    ConpherenceParticipant $participant) {
    $this->scrollUpParticipant = $participant;
    return $this;
  }

  public function setScrollDownParticipant(
    ConpherenceParticipant $participant) {
    $this->scrollDownParticipant = $participant;
    return $this;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('conpherence-menu-css');

    $grouped = mgroup($this->threads, 'getIsRoom');
    $rooms = idx($grouped, true, array());
    $rooms = array_slice($grouped[true], 0, 5);

    $policies = array();
    foreach ($rooms as $room) {
      $policies[] = $room->getViewPolicy();
    }
    $policy_objects = array();
    if ($policies) {
      $policy_objects = id(new PhabricatorPolicyQuery())
        ->setViewer($this->getUser())
        ->withPHIDs($policies)
        ->execute();
    }

    $menu = id(new PHUIListView())
      ->addClass('conpherence-menu')
      ->setID('conpherence-menu');

    $this->addRoomsToMenu($menu, $rooms, $policy_objects);
    $messages = idx($grouped, false, array());
    $this->addThreadsToMenu($menu, $messages);

    return $menu;
  }

  public function renderSingleThread(ConpherenceThread $thread) {
    $policy_objects = id(new PhabricatorPolicyQuery())
      ->setViewer($this->getUser())
      ->setObject($thread)
      ->execute();
    return $this->renderThread($thread, $policy_objects);
  }

  public function renderThreadsHTML() {
    $thread_html = array();

    if ($this->scrollUpParticipant->getID()) {
      $thread_html[] = $this->getScrollMenuItem(
        $this->scrollUpParticipant,
        'up');
    }

    foreach ($this->threads as $thread) {
      $thread_html[] = $this->renderSingleThread($thread);
    }

    if ($this->scrollDownParticipant->getID()) {
      $thread_html[] = $this->getScrollMenuItem(
        $this->scrollDownParticipant,
        'down');
    }

    return phutil_implode_html('', $thread_html);
  }

  private function renderThreadItem(
    ConpherenceThread $thread,
    $policy_objects = array()) {
    return id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_CUSTOM)
      ->setName($this->renderThread($thread, $policy_objects));
  }

  private function renderThread(
    ConpherenceThread $thread,
    array $policy_objects) {

    $user = $this->getUser();

    $uri = $this->baseURI.$thread->getID().'/';
    $data = $thread->getDisplayData($user);
    $title = phutil_tag(
      'span',
      array(),
      array(
        id(new PHUIIconView())
        ->addClass('mmr')
        ->setIconFont($thread->getPolicyIconName($policy_objects)),
        $data['title'],
      ));
    $subtitle = $data['subtitle'];
    $unread_count = $data['unread_count'];
    $epoch = $data['epoch'];
    $image = $data['image'];
    $dom_id = $thread->getPHID().'-nav-item';

    return id(new ConpherenceMenuItemView())
      ->setUser($user)
      ->setTitle($title)
      ->setSubtitle($subtitle)
      ->setHref($uri)
      ->setEpoch($epoch)
      ->setImageURI($image)
      ->setUnreadCount($unread_count)
      ->setID($thread->getPHID().'-nav-item')
      ->addSigil('conpherence-menu-click')
      ->setMetadata(
        array(
          'title' => $data['js_title'],
          'id' => $dom_id,
          'threadID' => $thread->getID(),
          ));
  }

  private function addRoomsToMenu(
    PHUIListView $menu,
    array $conpherences,
    array $policy_objects) {

    $header = $this->renderMenuItemHeader(pht('Rooms'));
    $menu->addMenuItem($header);

    if (empty($conpherences)) {
      $join_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref('/conpherence/room/')
        ->setName(pht('Join a Room'));
      $menu->addMenuItem($join_item);

      $create_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref('/conpherence/room/new/')
        ->setWorkflow(true)
        ->setName(pht('Create a Room'));
      $menu->addMenuItem($create_item);

      return $menu;
    }

    foreach ($conpherences as $conpherence) {
      $item = $this->renderThreadItem($conpherence, $policy_objects);
      $menu->addMenuItem($item);
    }

    $more_item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('/conpherence/room/query/participant/')
      ->setName(pht('See More'));
    $menu->addMenuItem($more_item);

    return $menu;
  }


  private function addThreadsToMenu(
    PHUIListView $menu,
    array $conpherences) {

    if ($this->scrollUpParticipant->getID()) {
      $item = $this->getScrollMenuItem($this->scrollUpParticipant, 'up');
      $menu->addMenuItem($item);
    }

    $header = $this->renderMenuItemHeader(pht('Messages'));
    $menu->addMenuItem($header);

    foreach ($conpherences as $conpherence) {
      $item = $this->renderThreadItem($conpherence);
      $menu->addMenuItem($item);
    }

    if (empty($conpherences)) {
      $menu->addMenuItem($this->getNoConpherencesMenuItem());
    }

    if ($this->scrollDownParticipant->getID()) {
      $item = $this->getScrollMenuItem($this->scrollDownParticipant, 'down');
      $menu->addMenuItem($item);
    }

    return $menu;
  }

  private function renderMenuItemHeader($title) {
    $item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName($title);
    return $item;
  }

  public function getScrollMenuItem(
    ConpherenceParticipant $participant,
    $direction) {

    if ($direction == 'up') {
      $name = pht('Load Newer Threads');
    } else {
      $name = pht('Load Older Threads');
    }
    $item = id(new PHUIListItemView())
      ->addSigil('conpherence-menu-scroller')
      ->setName($name)
      ->setHref($this->baseURI)
      ->setType(PHUIListItemView::TYPE_BUTTON)
      ->setMetadata(array(
        'participant_id' => $participant->getID(),
        'conpherence_phid' => $participant->getConpherencePHID(),
        'date_touched' => $participant->getDateTouched(),
        'direction' => $direction,
      ));
    return $item;
  }

  private function getNoMessagesMenuItem() {
    $message = phutil_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item',
      ),
      pht('No Messages'));

    return id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_CUSTOM)
      ->setName($message);
  }

  private function getNoRoomsMenuItem() {
    $message = phutil_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item',
      ),
      pht('No Rooms'));

    return id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_CUSTOM)
      ->setName($message);
  }


}
