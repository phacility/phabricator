<?php

final class ConpherenceLayoutView extends AphrontView {

  private $thread;
  private $baseURI;
  private $threadView;
  private $role;
  private $header;
  private $messages;
  private $replyForm;

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

  public function render() {
    require_celerity_resource('conpherence-menu-css');
    require_celerity_resource('conpherence-message-pane-css');
    require_celerity_resource('conpherence-widget-pane-css');

    $layout_id = celerity_generate_unique_node_id();

    $selected_id = null;
    $selected_thread_id = null;
    if ($this->thread) {
      $selected_id = $this->thread->getPHID() . '-nav-item';
      $selected_thread_id = $this->thread->getID();
    }
    Javelin::initBehavior('conpherence-menu',
      array(
        'baseURI' => $this->baseURI,
        'layoutID' => $layout_id,
        'selectedID' => $selected_id,
        'selectedThreadID' => $selected_thread_id,
        'role' => $this->role,
        'hasThreadList' => (bool)$this->threadView,
        'hasThread' => (bool)$this->messages,
        'hasWidgets' => false,
      ));

    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'widgetBaseUpdateURI' => $this->baseURI . 'update/',
        'widgetRegistry' => array(
          'conpherence-message-pane' => array(
            'name' => pht('Thread'),
            'deviceOnly' => true,
            'hasCreate' => false
          ),
          'widgets-people' => array(
            'name' => pht('Participants'),
            'deviceOnly' => false,
            'hasCreate' => true,
            'createData' => array(
              'refreshFromResponse' => true,
              'action' => ConpherenceUpdateActions::ADD_PERSON,
              'customHref' => null
            )
          ),
          'widgets-files' => array(
            'name' => pht('Files'),
            'deviceOnly' => false,
            'hasCreate' => false
          ),
          'widgets-calendar' => array(
            'name' => pht('Calendar'),
            'deviceOnly' => false,
            'hasCreate' => true,
            'createData' => array(
              'refreshFromResponse' => false,
              'action' => ConpherenceUpdateActions::ADD_STATUS,
              'customHref' => '/calendar/status/create/'
            )
          ),
          'widgets-settings' => array(
            'name' => pht('Settings'),
            'deviceOnly' => false,
            'hasCreate' => false
          ),
        )));


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
                    'class' => 'text'
                  ),
                  pht('You do not have any messages yet.')),
                javelin_tag(
                  'a',
                  array(
                    'href' => '/conpherence/new/',
                    'class' => 'button',
                    'sigil' => 'workflow',
                  ),
                  pht('Send a Message'))
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
                    'class' => 'widgets-loading-mask'
                  ),
                  ''),
                javelin_tag(
                  'div',
                  array(
                    'sigil' => 'conpherence-widgets-holder'
                  ),
                  ''))),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-message-pane',
                'id' => 'conpherence-message-pane',
                'sigil' => 'conpherence-message-pane'
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
                    'sigil' => 'conpherence-form'
                  ),
                  nonempty($this->replyForm, ''))
              )),
          )),
      ));
  }

}
