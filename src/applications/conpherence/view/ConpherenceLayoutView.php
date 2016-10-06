<?php

final class ConpherenceLayoutView extends AphrontTagView {

  private $thread;
  private $baseURI;
  private $threadView;
  private $role;
  private $header;
  private $messages;
  private $replyForm;
  private $latestTransactionID;

  public function setMessages($messages) {
    $this->messages = $messages;
    return $this;
  }

  public function setReplyForm($reply_form) {
    $this->replyForm = $reply_form;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setRole($role) {
    $this->role = $role;
    return $this;
  }

  public function getThreadView() {
    return $this->threadView;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function setThread(ConpherenceThread $thread) {
    $this->thread = $thread;
    return $this;
  }

  public function setThreadView(ConpherenceThreadListView $thead_view) {
    $this->threadView = $thead_view;
    return $this;
  }

  public function setLatestTransactionID($id) {
    $this->latestTransactionID = $id;
    return $this;
  }

  public function getWidgetColumnVisible() {
    $widget_key = PhabricatorConpherenceWidgetVisibleSetting::SETTINGKEY;
    $user = $this->getUser();
    return (bool)$user->getUserSetting($widget_key, false);
  }

  protected function getTagAttributes() {
    $classes = array();
    if (!$this->getWidgetColumnVisible()) {
      $classes[] = 'hide-widgets';
    }

    return array(
        'id'    => 'conpherence-main-layout',
        'sigil' => 'conpherence-layout',
        'class' => 'conpherence-layout '.
                    implode(' ', $classes).
                    ' conpherence-role-'.$this->role,
      );

  }

  protected function getTagContent() {
    require_celerity_resource('conpherence-menu-css');
    require_celerity_resource('conpherence-message-pane-css');
    require_celerity_resource('conpherence-participant-pane-css');

    $selected_id = null;
    $selected_thread_id = null;
    $selected_thread_phid = null;
    $can_edit_selected = null;
    $nux = null;
    if ($this->thread) {
      $selected_id = $this->thread->getPHID().'-nav-item';
      $selected_thread_id = $this->thread->getID();
      $selected_thread_phid = $this->thread->getPHID();
      $can_edit_selected = PhabricatorPolicyFilter::hasCapability(
        $this->getUser(),
        $this->thread,
        PhabricatorPolicyCapability::CAN_EDIT);
    } else {
      $nux = $this->buildNUXView();
    }
    $this->initBehavior('conpherence-menu',
      array(
        'baseURI' => $this->baseURI,
        'layoutID' => 'conpherence-main-layout',
        'selectedID' => $selected_id,
        'selectedThreadID' => $selected_thread_id,
        'selectedThreadPHID' => $selected_thread_phid,
        'canEditSelectedThread' => $can_edit_selected,
        'latestTransactionID' => $this->latestTransactionID,
        'role' => $this->role,
        'hasThreadList' => (bool)$this->threadView,
        'hasThread' => (bool)$this->messages,
        'hasWidgets' => false,
      ));

    $this->initBehavior('conpherence-participant-pane');

    return
      array(
        javelin_tag(
          'div',
          array(
            'id' => 'conpherence-menu-pane',
            'class' => 'conpherence-menu-pane phabricator-side-menu',
            'sigil' => 'conpherence-menu-pane',
          ),
          $this->threadView),
        javelin_tag(
          'div',
          array(
            'class' => 'conpherence-content-pane',
          ),
          array(
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-header-pane',
                'id' => 'conpherence-header-pane',
                'sigil' => 'conpherence-header-pane',
              ),
              nonempty($this->header, '')),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-no-threads',
                'sigil' => 'conpherence-no-threads',
                'style' => 'display: none;',
              ),
              $nux),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-participant-pane',
                'id' => 'conpherence-participant-pane',
                'sigil' => 'conpherence-participant-pane',
              ),
              array(
                phutil_tag(
                  'div',
                  array(
                    'class' => 'widgets-loading-mask',
                  ),
                  ''),
                javelin_tag(
                  'div',
                  array(
                    'sigil' => 'conpherence-widgets-holder',
                  ),
                  ''),
              )),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-message-pane',
                'id' => 'conpherence-message-pane',
                'sigil' => 'conpherence-message-pane',
              ),
              array(
                javelin_tag(
                  'div',
                  array(
                    'class' => 'conpherence-messages',
                    'id' => 'conpherence-messages',
                    'sigil' => 'conpherence-messages',
                  ),
                  nonempty($this->messages, '')),
                phutil_tag(
                  'div',
                  array(
                    'class' => 'messages-loading-mask',
                  ),
                  ''),
                javelin_tag(
                  'div',
                  array(
                    'id' => 'conpherence-form',
                    'sigil' => 'conpherence-form',
                  ),
                  nonempty($this->replyForm, '')),
              )),
          )),
      );
  }

  private function buildNUXView() {
    $viewer = $this->getViewer();

    $engine = new ConpherenceThreadSearchEngine();
    $engine->setViewer($viewer);
    $saved = $engine->buildSavedQueryFromBuiltin('all');
    $query = $engine->buildQueryFromSavedQuery($saved);
    $pager = $engine->newPagerForSavedQuery($saved);
    $pager->setPageSize(10);
    $results = $engine->executeQuery($query, $pager);
    $view = $engine->renderResults($results, $saved);

    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Room'))
      ->setHref('/conpherence/new/')
      ->setWorkflow(true)
      ->setColor(PHUIButtonView::GREEN);

    if ($results) {
      $create_button->setIcon('fa-comments');

      $header = id(new PHUIHeaderView())
        ->setHeader(pht('Joinable Rooms'))
        ->addActionLink($create_button);

      $box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->setObjectList($view->getObjectList());
      if ($viewer->isLoggedIn()) {
        $info = id(new PHUIInfoView())
          ->appendChild(pht('You have not joined any rooms yet.'))
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $box->setInfoView($info);
      }

      return $box;
    } else {

      $view = id(new PHUIBigInfoView())
        ->setIcon('fa-comments')
        ->setTitle(pht('Welcome to Conpherence'))
        ->setDescription(
          pht('Conpherence lets you create public or private rooms to '.
            'communicate with others.'))
        ->addAction($create_button);

        return $view;
    }
  }

}
