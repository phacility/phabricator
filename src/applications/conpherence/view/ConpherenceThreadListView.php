<?php

final class ConpherenceThreadListView extends AphrontView {

  private $baseURI;
  private $unreadThreads;
  private $readThreads;

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function setUnreadThreads(array $unread_threads) {
    assert_instances_of($unread_threads, 'ConpherenceThread');
    $this->unreadThreads = $unread_threads;
    return $this;
  }

  public function setReadThreads(array $read_threads) {
    assert_instances_of($read_threads, 'ConpherenceThread');
    $this->readThreads = $read_threads;
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

    $menu->newLabel(pht('Unread'));
    $this->addThreadsToMenu($menu, $this->unreadThreads, $read = false);
    $menu->newLabel(pht('Read'));
    $this->addThreadsToMenu($menu, $this->readThreads, $read = true);

    return $menu;
  }

  public function renderSingleThread(ConpherenceThread $thread) {
    return $this->renderThread($thread);
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
    array $conpherences,
    $read = false) {

    foreach ($conpherences as $conpherence) {
      $item = $this->renderThreadItem($conpherence);
      $menu->addMenuItem($item);
    }

    if (empty($conpherences) || $read) {
      $menu->addMenuItem($this->getNoConpherencesBlock());
    }

    return $menu;
  }

  private function getNoConpherencesBlock() {
    $message = phutil_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item'
      ),
      pht('No more conpherences.'));

    return id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_CUSTOM)
      ->setName($message);
  }

}
