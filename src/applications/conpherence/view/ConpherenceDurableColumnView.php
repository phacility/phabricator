<?php

final class ConpherenceDurableColumnView extends AphrontTagView {

  private $conpherences = array();
  private $draft;
  private $selectedConpherence;
  private $transactions;
  private $visible;
  private $initialLoad = false;

  public function setConpherences(array $conpherences) {
    assert_instances_of($conpherences, 'ConpherenceThread');
    $this->conpherences = $conpherences;
    return $this;
  }

  public function getConpherences() {
    return $this->conpherences;
  }

  public function setDraft(PhabricatorDraft $draft) {
    $this->draft = $draft;
    return $this;
  }

  public function getDraft() {
    return $this->draft;
  }

  public function setSelectedConpherence(
    ConpherenceThread $conpherence = null) {
    $this->selectedConpherence = $conpherence;
    return $this;
  }

  public function getSelectedConpherence() {
    return $this->selectedConpherence;
  }

  public function setTransactions(array $transactions) {
    assert_instances_of($transactions, 'ConpherenceTransaction');
    $this->transactions = $transactions;
    return $this;
  }

  public function getTransactions() {
    return $this->transactions;
  }

  public function setVisible($visible) {
    $this->visible = $visible;
    return $this;
  }

  public function getVisible() {
    return $this->visible;
  }

  public function setInitialLoad($bool) {
    $this->initialLoad = $bool;
    return $this;
  }

  public function getInitialLoad() {
    return $this->initialLoad;
  }

  protected function getTagAttributes() {
    if ($this->getVisible()) {
      $style = null;
    } else {
      $style = 'display: none;';
    }
    $classes = array('conpherence-durable-column');
    if ($this->getInitialLoad()) {
      $classes[] = 'loading';
    }

    return array(
      'id' => 'conpherence-durable-column',
      'class' => implode(' ', $classes),
      'style' => $style,
      'sigil' => 'conpherence-durable-column',
    );
  }

  protected function getTagContent() {
    $column_key = PhabricatorUserPreferences::PREFERENCE_CONPHERENCE_COLUMN;
    require_celerity_resource('font-source-sans-pro');

    Javelin::initBehavior(
      'durable-column',
      array(
        'visible' => $this->getVisible(),
        'settingsURI' => '/settings/adjust/?key='.$column_key,
      ));

    $classes = array();
    $classes[] = 'conpherence-durable-column-header';
    $classes[] = 'sprite-main-header';
    $classes[] = 'main-header-'.PhabricatorEnv::getEnvConfig('ui.header-color');

    $loading_mask = phutil_tag(
      'div',
      array(
        'class' => 'loading-mask',
      ),
      '');

    $header = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->buildHeader());
    $icon_bar = phutil_tag(
      'div',
      array(
        'class' => 'conpherence-durable-column-icon-bar',
      ),
      $this->buildIconBar());

    $transactions = $this->buildTransactions();

    $content = javelin_tag(
      'div',
      array(
        'class' => 'conpherence-durable-column-main',
        'sigil' => 'conpherence-durable-column-main',
      ),
      phutil_tag(
        'div',
        array(
          'id' => 'conpherence-durable-column-content',
          'class' => 'conpherence-durable-column-frame',
        ),
        javelin_tag(
          'div',
          array(
            'class' => 'conpherence-durable-column-transactions',
            'sigil' => 'conpherence-durable-column-transactions',
          ),
          $transactions)));

    $input = $this->buildTextInput();

    $footer = phutil_tag(
      'div',
      array(
        'class' => 'conpherence-durable-column-footer',
      ),
      array(
        $this->buildSendButton(),
        phutil_tag(
          'div',
          array(
            'class' => 'conpherence-durable-column-status',
          ),
          $this->buildStatusText()),
      ));

    return array(
      $loading_mask,
      $header,
      javelin_tag(
        'div',
        array(
          'class' => 'conpherence-durable-column-body',
          'sigil' => 'conpherence-durable-column-body',
        ),
        array(
          $icon_bar,
          $content,
          $input,
          $footer,
        )),
    );
  }

  private function buildIconBar() {
    $icons = array();
    $selected_conpherence = $this->getSelectedConpherence();
    $conpherences = $this->getConpherences();

    foreach ($conpherences as $conpherence) {
      $classes = array('conpherence-durable-column-thread-icon');
      if ($selected_conpherence->getID() == $conpherence->getID()) {
        $classes[] = 'selected';
      }
      $data = $conpherence->getDisplayData($this->getUser());
      $image = $data['image'];
      Javelin::initBehavior('phabricator-tooltips');
      $icons[] =
        javelin_tag(
          'a',
          array(
            'href' => '/conpherence/columnview/',
            'class' => implode(' ', $classes),
            'sigil' => 'conpherence-durable-column-thread-icon has-tooltip',
            'meta' => array(
              'threadID' => $conpherence->getID(),
              'threadTitle' => $data['js_title'],
              'tip' => $data['js_title'],
              'align' => 'S',
            ),
          ),
          phutil_tag(
            'span',
            array(
              'style' => 'background-image: url('.$image.')',
            ),
            ''));
    }
    return $icons;
  }

  private function buildHeader() {
    $conpherence = $this->getSelectedConpherence();

    if (!$conpherence) {

      $title = null;
      $settings_button = null;
      $settings_menu = null;

    } else {

      $bubble_id = celerity_generate_unique_node_id();
      $dropdown_id = celerity_generate_unique_node_id();

      $settings_list = new PHUIListView();
      $header_actions = $this->getHeaderActionsConfig($conpherence);
      foreach ($header_actions as $action) {
        $settings_list->addMenuItem(
          id(new PHUIListItemView())
          ->setHref($action['href'])
          ->setName($action['name'])
          ->setIcon($action['icon'])
          ->addSigil('conpherence-durable-column-header-action')
          ->setMetadata(array(
            'action' => $action['key'],
          )));
      }

      $settings_menu = phutil_tag(
        'div',
        array(
          'id' => $dropdown_id,
          'class' => 'phabricator-main-menu-dropdown phui-list-sidenav '.
          'conpherence-settings-dropdown',
          'sigil' => 'phabricator-notification-menu',
          'style' => 'display: none',
        ),
        $settings_list);

      Javelin::initBehavior(
        'aphlict-dropdown',
        array(
          'bubbleID' => $bubble_id,
          'dropdownID' => $dropdown_id,
          'local' => true,
          'containerDivID' => 'conpherence-durable-column',
        ));

      $item = id(new PHUIListItemView())
        ->setName(pht('Settings'))
        ->setIcon('fa-bars')
        ->addClass('core-menu-item')
        ->addSigil('conpherence-settings-menu')
        ->setID($bubble_id)
        ->setHref('#')
        ->setAural(pht('Settings'))
        ->setOrder(300);
      $settings_button = id(new PHUIListView())
        ->addMenuItem($item)
        ->addClass('phabricator-dark-menu')
        ->addClass('phabricator-application-menu');

      $title = $conpherence->getTitle();
      if (!$title) {
        $title = pht('[No Title]');
      }
    }

    return
      phutil_tag(
        'div',
        array(
          'class' => 'conpherence-durable-column-header',
        ),
        array(
          javelin_tag(
            'div',
            array(
              'sigil' => 'conpherence-durable-column-header-text',
              'class' => 'conpherence-durable-column-header-text',
            ),
            $title),
          $settings_button,
          $settings_menu,));

  }

  private function getHeaderActionsConfig(ConpherenceThread $conpherence) {
    return array(
      array(
        'name' => pht('Add Participants'),
        'href' => '/conpherence/update/'.$conpherence->getID().'/',
        'icon' => 'fa-plus',
        'key' => ConpherenceUpdateActions::ADD_PERSON,
      ),
      array(
        'name' => pht('Rename Thread'),
        'href' => '/conpherence/update/'.$conpherence->getID().'/',
        'icon' => 'fa-pencil',
        'key' => ConpherenceUpdateActions::METADATA,
      ),
      array(
        'name' => pht('View in Conpherence'),
        'href' => '/conpherence/'.$conpherence->getID().'/',
        'icon' => 'fa-comments',
        'key' => 'go_conpherence',
      ),
      array(
        'name' => pht('Hide Column'),
        'href' => '#',
        'icon' => 'fa-times',
        'key' => 'hide_column',
      ),);
  }

  private function buildTransactions() {
    $conpherence = $this->getSelectedConpherence();
    if (!$conpherence) {
      if (!$this->getVisible() || $this->getInitialLoad()) {
        return pht('Loading...');
      }
      return array(
        phutil_tag(
          'div',
          array(
            'class' => 'mmb',
          ),
          pht('You do not have any messages yet.')),
        javelin_tag(
          'a',
          array(
            'href' => '/conpherence/new/',
            'class' => 'button grey',
            'sigil' => 'workflow',
          ),
          pht('Send a Message')),);
    }

    $data = ConpherenceTransactionView::renderTransactions(
      $this->getUser(),
      $conpherence,
      $full_display = false);
    $messages = ConpherenceTransactionView::renderMessagePaneContent(
      $data['transactions'],
      $data['oldest_transaction_id']);

    return $messages;
  }

  private function buildTextInput() {
    $conpherence = $this->getSelectedConpherence();
    if (!$conpherence) {
      return null;
    }

    $draft = $this->getDraft();
    $draft_value = null;
    if ($draft) {
      $draft_value = $draft->getDraft();
    }

    $textarea_id = celerity_generate_unique_node_id();
    $textarea = javelin_tag(
      'textarea',
      array(
        'id' => $textarea_id,
        'name' => 'text',
        'class' => 'conpherence-durable-column-textarea',
        'sigil' => 'conpherence-durable-column-textarea',
        'placeholder' => pht('Send a message...'),
      ),
      $draft_value);
    Javelin::initBehavior(
      'aphront-drag-and-drop-textarea',
      array(
        'target'          => $textarea_id,
        'activatedClass'  => 'aphront-textarea-drag-and-drop',
        'uri'             => '/file/dropupload/',
      ));
    $id = $conpherence->getID();
    return phabricator_form(
      $this->getUser(),
      array(
        'method' => 'POST',
        'action' => '/conpherence/update/'.$id.'/',
        'sigil' => 'conpherence-message-form',
      ),
      array(
        $textarea,
        phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'action',
          'value' => ConpherenceUpdateActions::MESSAGE,
        )),));
  }

  private function buildStatusText() {
    return null;
  }

  private function buildSendButton() {
    $conpherence = $this->getSelectedConpherence();
    if (!$conpherence) {
      return null;
    }

    return javelin_tag(
      'button',
      array(
        'class' => 'grey',
        'sigil' => 'conpherence-send-message',
      ),
      pht('Send'));
  }

}
