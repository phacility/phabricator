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
      array_keys($unread));
    $this->setUnreadConpherences($unread_conpherences);

    $read_conpherences = array_select_keys(
      $all_conpherences,
      array_keys($read));
    $this->setReadConpherences($read_conpherences);

    if (!$this->getSelectedConpherencePHID()) {
      $this->setSelectedConpherencePHID(reset($all_conpherence_phids));
    }

    return $this;
  }

  public function buildSideNavView($filter = null, $for_application = false) {
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
      $this->getApplicationURI('new/'));
    $nav->addLabel(pht('Unread'));
    $nav = $this->addConpherencesToNav(
      $unread_conpherences,
      $nav,
      false,
      $for_application);
    $nav->addLabel(pht('Read'));
    $nav = $this->addConpherencesToNav(
      $read_conpherences,
      $nav,
      true,
      $for_application);
    $nav->selectFilter($filter);
    return $nav;
  }

  private function addConpherencesToNav(
    array $conpherences,
    AphrontSideNavFilterView $nav,
    $read = false,
    $for_application = false) {

    $user = $this->getRequest()->getUser();
    $id_suffix = $for_application ? '-menu-item' : '-nav-item';
    foreach ($conpherences as $conpherence) {
      $selected = false;
      if ($this->getSelectedConpherencePHID() == $conpherence->getPHID()) {
        $selected = true;
     }
      $item = $this->buildConpherenceMenuItem(
        $conpherence,
        $id_suffix,
        $selected);

      $nav->addCustomBlock($item->render());
    }
    if (empty($conpherences) || $read) {
      $nav->addCustomBlock($this->getNoConpherencesBlock());
    }

    return $nav;
  }

  private function getNoConpherencesBlock() {
    return phutil_tag(
      'div',
      array(
        'class' => 'no-conpherences-menu-item'
      ),
      pht('No more conpherences.'));
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(
      $filter = null,
      $for_application = true)->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setName(pht('New Conversation'))
          ->setHref($this->getApplicationURI('new/'))
          ->setIcon('create'))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Conpherence')));

    return $crumbs;
  }

  protected function buildConpherenceMenuItem(
    $conpherence,
    $id_suffix,
    $selected) {

    $user = $this->getRequest()->getUser();
    $uri = $this->getApplicationURI('view/'.$conpherence->getID().'/');
    $data = $conpherence->getDisplayData(
      $user,
      null);
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
      ->setID($conpherence->getPHID().$id_suffix)
      ->addSigil('conpherence-menu-click')
      ->setMetadata(array('id' => $conpherence->getID()));

    if ($selected) {
      $item
        ->addClass('conpherence-selected')
        ->addClass('hide-unread-count');
    }

    return $item;
  }

  protected function buildHeaderPaneContent(ConpherenceThread $conpherence) {
    $user = $this->getRequest()->getUser();
    $display_data = $conpherence->getDisplayData(
      $user,
      ConpherenceImageData::SIZE_HEAD);
    $edit_href = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $class_mod = $display_data['image_class'];

    $header =
    phutil_tag(
      'div',
      array(
        'class' => 'upload-photo'
      ),
      pht('Drop photo here to change this Conpherence photo.')).
    javelin_tag(
      'a',
      array(
        'class' => 'edit',
        'href' => $edit_href,
        'sigil' => 'conpherence-edit-metadata',
        'meta' => array(
          'action' => 'metadata'
        )
      ),
      '').
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'header-image',
        'style' => 'background-image: url('.$display_data['image'].');'
      ),
      '').
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'title',
      ),
      $display_data['title']).
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'subtitle',
      ),
      $display_data['subtitle']);

    return $header;
  }

  protected function renderConpherenceTransactions(
    ConpherenceThread $conpherence) {

    $user = $this->getRequest()->getUser();
    $transactions = $conpherence->getTransactions();
    $handles = $conpherence->getHandles();
    $rendered_transactions = array();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($transactions as $key => $transaction) {
      if ($transaction->shouldHide()) {
        unset($transactions[$key]);
        continue;
      }
      if ($transaction->getComment()) {
        $engine->addObject(
          $transaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();
    foreach ($transactions as $transaction) {
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->setMarkupEngine($engine)
        ->render();
    }
    $latest_transaction_id = $transaction->getID();
    $rendered_transactions = phutil_implode_html(' ', $rendered_transactions);

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $latest_transaction_id
    );

  }

  protected function initJavelinBehaviors($more_than_menu = false) {

    Javelin::initBehavior('conpherence-menu',
      array(
        'base_uri' => $this->getApplicationURI(''),
        'header' => 'conpherence-header-pane',
        'messages' => 'conpherence-messages',
        'messages_pane' => 'conpherence-message-pane',
        'widgets_pane' => 'conpherence-widget-pane',
        'form_pane' => 'conpherence-form',
        'menu_pane' => 'conpherence-menu',
        'selected_conpherence_id' => $this->getSelectedConpherencePHID(),
        'fancy_ajax' => (bool) $this->getSelectedConpherencePHID()
      ));
    if ($more_than_menu) {
      Javelin::initBehavior('conpherence-drag-and-drop-photo',
        array(
          'target' => 'conpherence-header-pane',
          'form_pane' => 'conpherence-form',
          'upload_uri' => '/file/dropupload/',
          'activated_class' => 'conpherence-header-upload-photo',
        ));
      Javelin::initBehavior('conpherence-pontificate',
        array(
          'messages' => 'conpherence-messages',
          'header' => 'conpherence-header-pane',
          'menu_pane' => 'conpherence-menu',
          'form_pane' => 'conpherence-form',
          'file_widget' => 'widgets-files',
        ));
    }
  }
}
