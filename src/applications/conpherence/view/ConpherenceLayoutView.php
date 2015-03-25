<?php

final class ConpherenceLayoutView extends AphrontView {

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

  public function render() {
    require_celerity_resource('conpherence-menu-css');
    require_celerity_resource('conpherence-message-pane-css');
    require_celerity_resource('conpherence-widget-pane-css');

    require_celerity_resource('phui-fontkit-css');
    require_celerity_resource('font-source-sans-pro');

    $layout_id = celerity_generate_unique_node_id();

    $selected_id = null;
    $selected_thread_id = null;
    $selected_thread_phid = null;
    $can_edit_selected = null;
    if ($this->thread) {
      $selected_id = $this->thread->getPHID().'-nav-item';
      $selected_thread_id = $this->thread->getID();
      $selected_thread_phid = $this->thread->getPHID();
      $can_edit_selected = PhabricatorPolicyFilter::hasCapability(
        $this->getUser(),
        $this->thread,
        PhabricatorPolicyCapability::CAN_EDIT);
    }
    $this->initBehavior('conpherence-menu',
      array(
        'baseURI' => $this->baseURI,
        'layoutID' => $layout_id,
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

    $this->initBehavior(
      'conpherence-widget-pane',
      ConpherenceWidgetConfigConstants::getWidgetPaneBehaviorConfig());

    return javelin_tag(
      'div',
      array(
        'id'    => $layout_id,
        'sigil' => 'conpherence-layout',
        'class' => 'conpherence-layout conpherence-role-'.$this->role,
      ),
      array(
        javelin_tag(
          'div',
          array(
            'class' => 'phabricator-nav-column-background',
            'sigil' => 'phabricator-nav-column-background',
          ),
          ''),
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
              array(
                phutil_tag(
                  'div',
                  array(
                    'class' => 'text',
                  ),
                  pht('You do not have any messages yet.')),
                javelin_tag(
                  'a',
                  array(
                    'href' => '/conpherence/new/',
                    'class' => 'button grey',
                    'sigil' => 'workflow',
                  ),
                  pht('Send a Message')),
            )),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-widget-pane',
                'id' => 'conpherence-widget-pane',
                'sigil' => 'conpherence-widget-pane',
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
                'class' => 'conpherence-message-pane phui-font-source-sans',
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
      ));
  }

}
