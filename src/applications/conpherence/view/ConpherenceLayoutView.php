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

    $layout_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('conpherence-menu',
      array(
        'base_uri' => $this->baseURI,
        'layoutID' => $layout_id,
        'selectedID' => ($this->thread ? $this->thread->getID() : null),
        'role' => $this->role,
        'hasThreadList' => (bool)$this->threadView,
        'hasThread' => (bool)$this->messages,
        'hasWidgets' => false,
      ));

    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'selectChar' => "\xE2\x96\xBC",
        'widgetRegistry' => array(
          'conpherence-message-pane' => array(
            'name' => pht('Thread'),
            'deviceOnly' => true,
          ),
          'widgets-people' => array(
            'name' => pht('Participants'),
            'deviceOnly' => false,
          ),
          'widgets-files' => array(
            'name' => pht('Files'),
            'deviceOnly' => false,
          ),
          'widgets-calendar' => array(
            'name' => pht('Calendar'),
            'deviceOnly' => false,
          ),
          'widgets-settings' => array(
            'name' => pht('Settings'),
            'deviceOnly' => false,
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
          nonempty($this->threadView, '')),
        javelin_tag(
          'div',
          array(
            'class' => 'conpherence-content-pane',
          ),
          array(
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
                'class' => 'conpherence-header-pane',
                'id' => 'conpherence-header-pane',
                'sigil' => 'conpherence-header-pane',
              ),
              nonempty($this->header, '')),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-widget-pane',
                'id' => 'conpherence-widget-pane',
                'sigil' => 'conpherence-widget-pane',
              ),
              ''),
            javelin_tag(
              'div',
              array(
                'class' => 'conpherence-message-pane',
                'id' => 'conpherence-message-pane'
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
