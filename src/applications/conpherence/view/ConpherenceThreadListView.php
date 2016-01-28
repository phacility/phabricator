<?php

final class ConpherenceThreadListView extends AphrontView {

  const SEE_MORE_LIMIT = 15;

  private $baseURI;
  private $threads;

  public function setThreads(array $threads) {
    assert_instances_of($threads, 'ConpherenceThread');
    $this->threads = $threads;
    return $this;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('conpherence-menu-css');

    $menu = id(new PHUIListView())
      ->addClass('conpherence-menu')
      ->setID('conpherence-menu');

    $policy_objects = ConpherenceThread::loadViewPolicyObjects(
      $this->getUser(),
      $this->threads);

    $this->addRoomsToMenu($menu, $this->threads, $policy_objects);

    return $menu;
  }

  public function renderSingleThread(
    ConpherenceThread $thread,
    array $policy_objects) {
    assert_instances_of($policy_objects, 'PhabricatorPolicy');
    return $this->renderThread($thread, $policy_objects);
  }

  private function renderThreadItem(
    ConpherenceThread $thread,
    array $policy_objects) {
    return id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_CUSTOM)
      ->setName($this->renderThread($thread, $policy_objects));
  }

  private function renderThread(
    ConpherenceThread $thread,
    array $policy_objects) {

    $user = $this->getUser();

    $uri = '/'.$thread->getMonogram();
    $data = $thread->getDisplayData($user);
    $icon = id(new PHUIIconView())
      ->addClass('msr')
      ->setIcon($thread->getPolicyIconName($policy_objects));
    $title = phutil_tag(
      'span',
      array(),
      array(
        $icon,
        $data['title'],
      ));
    $subtitle = $data['subtitle'];
    $unread_count = $data['unread_count'];
    $epoch = $data['epoch'];
    $image = $data['image'];
    $dom_id = $thread->getPHID().'-nav-item';
    $glyph_pref = PhabricatorUserPreferences::PREFERENCE_TITLES;
    $preferences = $user->loadPreferences();
    if ($preferences->getPreference($glyph_pref) == 'glyph') {
      $glyph = id(new PhabricatorConpherenceApplication())
        ->getTitleGlyph().' ';
    } else {
      $glyph = null;
    }

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
          'title' => $glyph.$data['title'],
          'id' => $dom_id,
          'threadID' => $thread->getID(),
          ));
  }

  private function addRoomsToMenu(
    PHUIListView $menu,
    array $rooms,
    array $policy_objects) {

    $header = $this->renderMenuItemHeader(
      pht('Rooms'),
      'conpherence-room-list-header');
    $header->appendChild(
      id(new PHUIIconView())
      ->setIcon('fa-search')
      ->setHref('/conpherence/search/')
      ->setText(pht('Search')));
    $menu->addMenuItem($header);

    if (empty($rooms)) {
      $join_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref('/conpherence/search/')
        ->setName(pht('Join a Room'));
      $menu->addMenuItem($join_item);

      $create_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref('/conpherence/new/')
        ->setWorkflow(true)
        ->setName(pht('Create a Room'));
      $menu->addMenuItem($create_item);

      return $menu;
    }

    $this->addThreadsToMenu($menu, $rooms, $policy_objects);
    return $menu;
  }

  private function addThreadsToMenu(
    PHUIListView $menu,
    array $threads,
    array $policy_objects) {

    // If we have self::SEE_MORE_LIMIT or less, we can just render
    // all the threads at once. Otherwise, we render a "See more"
    // UI element, which toggles a show / hide on the remaining rooms
    $show_threads = $threads;
    $more_threads = array();
    if (count($threads) > self::SEE_MORE_LIMIT) {
      $show_threads = array_slice($threads, 0, self::SEE_MORE_LIMIT);
      $more_threads = array_slice($threads, self::SEE_MORE_LIMIT);
    }

    foreach ($show_threads as $thread) {
      $item = $this->renderThreadItem($thread, $policy_objects);
      $menu->addMenuItem($item);
    }

    if ($more_threads) {
      $search_uri = '/conpherence/search/query/participant/';
      $sigil = 'more-room';

      $more_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref($search_uri)
        ->addSigil('conpherence-menu-see-more')
        ->setMetadata(array('moreSigil' => $sigil))
        ->setName(pht('See More'));
      $menu->addMenuItem($more_item);
      $show_more_threads = $more_threads;
      $even_more_threads = array();
      if (count($more_threads) > self::SEE_MORE_LIMIT) {
        $show_more_threads = array_slice(
          $more_threads,
          0,
          self::SEE_MORE_LIMIT);
        $even_more_threads = array_slice(
          $more_threads,
          self::SEE_MORE_LIMIT);
      }
      foreach ($show_more_threads as $thread) {
        $item = $this->renderThreadItem($thread, $policy_objects)
          ->addSigil($sigil)
          ->addClass('hidden');
        $menu->addMenuItem($item);
      }

      if ($even_more_threads) {
        // kick them to application search here
        $even_more_item = id(new PHUIListItemView())
          ->setType(PHUIListItemView::TYPE_LINK)
          ->setHref($search_uri)
          ->addSigil($sigil)
          ->addClass('hidden')
          ->setName(pht('See More'));
        $menu->addMenuItem($even_more_item);
      }
    }

    return $menu;
  }

  private function renderMenuItemHeader($title, $class = null) {
    $item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName($title)
      ->addClass($class);
    return $item;
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
