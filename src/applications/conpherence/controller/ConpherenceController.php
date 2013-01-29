<?php

/**
 * @group conpherence
 */
abstract class ConpherenceController extends PhabricatorController {
  private $conpherences;
  private $selectedConpherencePHID;
  private $readConpherences;
  private $unreadConpherences;

  public function setUnreadConpherences(array $conpherences) {
    assert_instances_of($conpherences, 'ConpherenceThread');
    $this->unreadConpherences = $conpherences;
    return $this;
  }
  public function getUnreadConpherences() {
    return $this->unreadConpherences;
  }

  public function setReadConpherences(array $conpherences) {
    assert_instances_of($conpherences, 'ConpherenceThread');
    $this->readConpherences = $conpherences;
    return $this;
  }
  public function getReadConpherences() {
    return $this->readConpherences;
  }

  public function setSelectedConpherencePHID($phid) {
    $this->selectedConpherencePHID = $phid;
    return $this;
  }
  public function getSelectedConpherencePHID() {
    return $this->selectedConpherencePHID;
  }

  /**
   * Try for a full set of unread conpherences, and if we fail
   * load read conpherences. Additional conpherences in either category
   * are loaded asynchronously.
   */
  public function loadStartingConpherences($current_selection_epoch = null) {
    $user = $this->getRequest()->getUser();

    $read_participant_query = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()));
    $read_status =  ConpherenceParticipationStatus::UP_TO_DATE;
    if ($current_selection_epoch) {
      $read_one = $read_participant_query
        ->withParticipationStatus($read_status)
        ->withDateTouched($current_selection_epoch, '>')
        ->execute();

      $read_two = $read_participant_query
        ->withDateTouched($current_selection_epoch, '<=')
        ->execute();

      $read = array_merge($read_one, $read_two);

    } else {
      $read = $read_participant_query
        ->withParticipationStatus($read_status)
        ->execute();
    }

    $unread_status = ConpherenceParticipationStatus::BEHIND;
    $unread = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()))
      ->withParticipationStatus($unread_status)
      ->execute();

    $all_participation = $unread + $read;
    $all_conpherence_phids = array_keys($all_participation);
    $all_conpherences = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withPHIDs($all_conpherence_phids)
      ->execute();
    $unread_conpherences = array_select_keys(
      $all_conpherences,
      array_keys($unread)
    );
    $this->setUnreadConpherences($unread_conpherences);

    $read_conpherences = array_select_keys(
      $all_conpherences,
      array_keys($read)
    );
    $this->setReadConpherences($read_conpherences);

    if (!$this->getSelectedConpherencePHID()) {
      $this->setSelectedConpherencePHID(reset($all_conpherence_phids));
    }

    return $this;
  }

  public function buildSideNavView($filter = null) {
    require_celerity_resource('conpherence-menu-css');
    $unread_conpherences = $this->getUnreadConpherences();
    $read_conpherences = $this->getReadConpherences();

    $user = $this->getRequest()->getUser();

    $menu = new PhabricatorMenuView();
    $nav = AphrontSideNavFilterView::newFromMenu($menu);
    $nav->addClass('conpherence-menu');
    $nav->setMenuID('conpherence-menu');

    $nav->addButton(
      'new',
      pht('New Conversation'),
      $this->getApplicationURI('new/')
    );
    $nav->addLabel(pht('Unread'));
    $nav = $this->addConpherencesToNav($unread_conpherences, $nav);

    $nav->addLabel(pht('Read'));
    $nav = $this->addConpherencesToNav($read_conpherences, $nav, true);

    $nav->selectFilter($filter);

    return $nav;
  }

  private function addConpherencesToNav(
    array $conpherences,
    AphrontSideNavFilterView $nav,
    $read = false) {

    $user = $this->getRequest()->getUser();
    foreach ($conpherences as $conpherence) {
      $uri = $this->getApplicationURI('view/'.$conpherence->getID().'/');
      $data = $conpherence->getDisplayData($user);
      $title = $data['title'];
      $subtitle = $data['subtitle'];
      $unread_count = $data['unread_count'];
      $epoch = $data['epoch'];
      $image = $data['image'];
      $snippet = $data['snippet'];

      $item = id(new ConpherenceMenuItemView())
        ->setUser($user)
        ->setTitle($title)
        ->setSubtitle($subtitle)
        ->setHref($uri)
        ->setEpoch($epoch)
        ->setImageURI($image)
        ->setMessageText($snippet)
        ->setUnreadCount($unread_count)
        ->setID($conpherence->getPHID())
        ->addSigil('conpherence-menu-click')
        ->setMetadata(array('id' => $conpherence->getID()));
      if ($this->getSelectedConpherencePHID() == $conpherence->getPHID()) {
        $item->addClass('conpherence-selected');
        $item->addClass('hide-unread-count');
      }
      $nav->addCustomBlock($item->render());
    }
    if (empty($conpherences) || $read) {
      $nav->addCustomBlock($this->getNoConpherencesBlock());
    }

    return $nav;
  }

  private function getNoConpherencesBlock() {

    return phutil_render_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item'
      ),
      pht('No more conversations.')
    );
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setName(pht('New Conversation'))
          ->setHref($this->getApplicationURI('new/'))
          ->setIcon('create')
      )
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Conpherence'))
      );

    return $crumbs;
  }

  protected function initJavelinBehaviors() {

    Javelin::initBehavior('conpherence-menu',
      array(
        'base_uri' => $this->getApplicationURI(''),
        'header' => 'conpherence-header-pane',
        'messages' => 'conpherence-messages',
        'widgets_pane' => 'conpherence-widget-pane',
        'form_pane' => 'conpherence-form',
        'fancy_ajax' => (bool) $this->getSelectedConpherencePHID()
      )
    );
    Javelin::initBehavior('conpherence-init',
      array(
        'selected_conpherence_id' => $this->getSelectedConpherencePHID(),
        'menu_pane' => 'conpherence-menu',
        'messages_pane' => 'conpherence-message-pane',
        'messages' => 'conpherence-messages',
        'widgets_pane' => 'conpherence-widget-pane',
        'form_pane' => 'conpherence-form'
      )
    );
  }

}
