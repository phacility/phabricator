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

    Javelin::initBehavior('conpherence-drag-and-drop-photo',
      array(
        'target' => 'conpherence-header-pane',
        'form_pane' => 'conpherence-form',
        'upload_uri' => '/file/dropupload/',
        'activated_class' => 'conpherence-header-upload-photo',
      ));

    $all_views = 1;
    $devices_only = 0;
    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'allViews' => $all_views,
        'devicesOnly' => $devices_only,
        'widgetRegistery' => array(
          'conpherence-menu-pane' => $devices_only,
          'conpherence-message-pane' => $devices_only,
          'widgets-people' => $all_views,
          'widgets-files' => $all_views,
          'widgets-calendar' => $all_views,
          'widgets-settings' => $all_views,
        ),
        'widgetExtraNodes' => array(
          'conpherence-menu-pane' => array(
            array(
              'tagname' => 'div',
              'sigil' => 'phabricator-nav-column-background',
              'showstyle' => 'block',
              'hidestyle' => 'none',
              'desktopstyle' => 'block'),
            array(
              'tagname' => 'a',
              'sigil' =>  'conpherence-new-conversation',
              'showstyle' => 'none',
              'hidestyle' => 'none',
              'desktopstyle' => 'block'),
          )
        ),
        'widgetToggleMap' => array(
          'conpherence-menu-pane' => 'conpherence_list_on',
          'conpherence-message-pane' => 'conpherence_conversation_on',
          'widgets-people' => 'conpherence_people_on',
          'widgets-files' => 'conpherence_files_on',
          'widgets-calendar' => 'conpherence_calendar_on',
          'widgets-settings' => 'conpherence_settings_on',
        )
      ));

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
                  pht('You do not have any conpherences yet.')),
                javelin_tag(
                  'a',
                  array(
                    'href' => '/conpherence/new/',
                    'class' => 'button',
                    'sigil' => 'workflow',
                  ),
                  pht('Start a Conpherence'))
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
