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

    $menu = id(new PhabricatorMenuView())
      ->addClass('conpherence-menu')
      ->setID('conpherence-menu');

    $menu->addMenuItem(
      id(new PhabricatorMenuItemView())
        ->addSigil('conpherence-new-conversation')
        ->setName(pht('New Message'))
        ->setWorkflow(true)
        ->setKey('new')
        ->setHref($this->baseURI.'new/')
        ->setType(PhabricatorMenuItemView::TYPE_BUTTON));

    $menu->newLabel('');
    $this->addThreadsToMenu($menu, $this->threads);

    return $menu;
  }

  public function renderSingleThread(ConpherenceThread $thread) {
    return $this->renderThread($thread);
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

  private function renderThreadItem(ConpherenceThread $thread) {
    return id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_CUSTOM)
      ->setName($this->renderThread($thread));
  }

  private function renderThread(ConpherenceThread $thread) {
    $user = $this->getUser();

    $uri = $this->baseURI.$thread->getID().'/';
    $data = $thread->getDisplayData($user, null);
    $title = $data['title'];
    $subtitle = $data['subtitle'];
    $unread_count = $data['unread_count'];
    $epoch = $data['epoch'];
    $image = $data['image'];

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
          'title' => $title,
          'id' => $thread->getID(),
          ));
  }

  private function addThreadsToMenu(
    PhabricatorMenuView $menu,
    array $conpherences) {

    if ($this->scrollUpParticipant->getID()) {
      $item = $this->getScrollMenuItem($this->scrollUpParticipant, 'up');
      $menu->addMenuItem($item);
    }

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

  public function getScrollMenuItem(
    ConpherenceParticipant $participant,
    $direction) {

    if ($direction == 'up') {
      $name = pht('Load Newer Threads');
    } else {
      $name = pht('Load Older Threads');
    }
    $item = id(new PhabricatorMenuItemView())
      ->addSigil('conpherence-menu-scroller')
      ->setName($name)
      ->setHref($this->baseURI)
      ->setType(PhabricatorMenuItemView::TYPE_BUTTON)
      ->setMetadata(array(
        'participant_id' => $participant->getID(),
        'conpherence_phid' => $participant->getConpherencePHID(),
        'date_touched' => $participant->getDateTouched(),
        'direction' => $direction));
    return $item;
  }

  private function getNoConpherencesMenuItem() {
    $message = phutil_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item'
      ),
      pht('No conpherences.'));

    return id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_CUSTOM)
      ->setName($message);
  }

}
