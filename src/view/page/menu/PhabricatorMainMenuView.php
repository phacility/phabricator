<?php

final class PhabricatorMainMenuView extends AphrontView {

  private $defaultSearchScope;
  private $controller;
  private $applicationMenu;

  public function setApplicationMenu(PhabricatorMenuView $application_menu) {
    $this->applicationMenu = $application_menu;
    return $this;
  }

  public function getApplicationMenu() {
    return $this->applicationMenu;
  }

  public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  public function setDefaultSearchScope($default_search_scope) {
    $this->defaultSearchScope = $default_search_scope;
    return $this;
  }

  public function getDefaultSearchScope() {
    return $this->defaultSearchScope;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-main-menu-view');

    $header_id = celerity_generate_unique_node_id();
    $menus = array();
    $alerts = array();
    $search_button = '';
    $app_button = '';

    if ($user->isLoggedIn()) {
      list($menu, $dropdown) = $this->renderNotificationMenu();
      $alerts[] = $menu;
      $menus[] = $dropdown;
      $app_button = $this->renderApplicationMenuButton($header_id);
      $search_button = $this->renderSearchMenuButton($header_id);
    }

    $search_menu = $this->renderPhabricatorSearchMenu();

    if ($alerts) {
      $alerts = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-main-menu-alerts',
        ),
        $alerts);
    }

    $application_menu = $this->renderApplicationMenu();

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-main-menu',
        'id'    => $header_id,
      ),
      array(
        $app_button,
        $search_button,
        $this->renderPhabricatorLogo(),
        $alerts,
        $application_menu,
        $search_menu,
        $menus,
      ));
  }

  private function renderSearch() {
    $user = $this->user;

    $result = null;

    $keyboard_config = array(
      'helpURI' => '/help/keyboardshortcut/',
    );

    if ($user->isLoggedIn()) {
      $search = new PhabricatorMainMenuSearchView();
      $search->setUser($user);
      $search->setScope($this->getDefaultSearchScope());
      $result = $search;

      $pref_shortcut = PhabricatorUserPreferences::PREFERENCE_SEARCH_SHORTCUT;
      if ($user->loadPreferences()->getPreference($pref_shortcut, true)) {
        $keyboard_config['searchID'] = $search->getID();
      }
    }

    Javelin::initBehavior('phabricator-keyboard-shortcuts', $keyboard_config);

    if ($result) {
      $result = id(new PhabricatorMenuItemView())
        ->addClass('phabricator-main-menu-search')
        ->appendChild($result);
    }

    return $result;
  }

  public function renderApplicationMenuButton($header_id) {
    $button_id = celerity_generate_unique_node_id();
    return javelin_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-expand-button '.
                   'phabricator-expand-search-menu',
        'sigil' => 'jx-toggle-class',
        'meta'  => array(
          'map' => array(
            $header_id => 'phabricator-application-menu-expanded',
            $button_id => 'menu-icon-app-blue',
          ),
        ),
      ),
      phutil_tag(
        'span',
        array(
          'class' => 'phabricator-menu-button-icon sprite-menu menu-icon-app',
          'id' => $button_id,
        ),
        ''));
  }

  public function renderApplicationMenu() {
    $user = $this->getUser();
    $controller = $this->getController();

    $applications = PhabricatorApplication::getAllInstalledApplications();

    $actions = array();
    foreach ($applications as $application) {
      if ($application->shouldAppearInLaunchView()) {
        $app_actions = $application->buildMainMenuItems($user, $controller);
        foreach ($app_actions as $action) {
          $actions[] = $action;
        }
      }
    }

    $view = $this->getApplicationMenu();

    if (!$view) {
      $view = new PhabricatorMenuView();
    }

    $view->addClass('phabricator-dark-menu');
    $view->addClass('phabricator-application-menu');

    if ($actions) {
      $view->addMenuItem(
        id(new PhabricatorMenuItemView())
          ->addClass('phabricator-core-item-device')
          ->setType(PhabricatorMenuItemView::TYPE_LABEL)
          ->setName(pht('Actions')));
      foreach ($actions as $action) {
        $icon = $action->getIcon();
        if ($icon) {
          if ($action->getSelected()) {
            $action->appendChild($this->renderMenuIcon($icon.'-blue-large'));
          } else {
            $action->appendChild($this->renderMenuIcon($icon.'-light-large'));
          }
        }
        $view->addMenuItem($action);
      }
    }

    if ($user->isLoggedIn()) {
      $view->addMenuItem(
        id(new PhabricatorMenuItemView())
          ->addClass('phabricator-menu-item-type-link')
          ->addClass('phabricator-core-menu-item')
          ->setName(pht('Log Out'))
          ->setHref('/logout/')
          ->appendChild($this->renderMenuIcon('power-light-large')));
    }

    return $view;
  }

  public function renderSearchMenuButton($header_id) {
    $button_id = celerity_generate_unique_node_id();
    return javelin_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-search-button '.
                   'phabricator-expand-application-menu',
        'sigil' => 'jx-toggle-class',
        'meta'  => array(
          'map' => array(
            $header_id => 'phabricator-search-menu-expanded',
            $button_id => 'menu-icon-search-blue',
          ),
        ),
      ),
      phutil_tag(
      'span',
      array(
        'class' => 'phabricator-menu-button-icon sprite-menu menu-icon-search',
        'id' => $button_id,
      ),
      ''));
  }

  private function renderPhabricatorSearchMenu() {

    $view = new PhabricatorMenuView();
    $view->addClass('phabricator-dark-menu');
    $view->addClass('phabricator-search-menu');

    $search = $this->renderSearch();
    if ($search) {
      $view->addMenuItem($search);
    }

    return $view;
  }

  private function renderPhabricatorLogo() {
    return phutil_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-logo',
        'href'  => '/',
      ),
      phutil_tag(
        'span',
        array(
          'class' => 'sprite-menu phabricator-main-menu-logo-image',
        ),
        ''));
  }

  private function renderNotificationMenu() {
    $user = $this->user;

    require_celerity_resource('phabricator-notification-css');
    require_celerity_resource('phabricator-notification-menu-css');
    require_celerity_resource('sprite-menu-css');

    $container_classes = array(
      'sprite-menu',
      'alert-notifications',
    );

    $message_tag = '';
    $conpherence = 'PhabricatorApplicationConpherence';
    if (PhabricatorApplication::isClassInstalled($conpherence)) {
      $message_id = celerity_generate_unique_node_id();
      $message_count_id = celerity_generate_unique_node_id();

      $unread_status = ConpherenceParticipationStatus::BEHIND;
      $unread = id(new ConpherenceParticipantQuery())
        ->withParticipantPHIDs(array($user->getPHID()))
        ->withParticipationStatus($unread_status)
        ->execute();
      $message_count_number = count($unread);
      if ($message_count_number > 999) {
        $message_count_number = "\xE2\x88\x9E";
      }

      $message_count_tag = phutil_tag(
        'span',
        array(
          'id'    => $message_count_id,
          'class' => 'phabricator-main-menu-message-count'
        ),
        $message_count_number);

      $message_icon_tag = phutil_tag(
        'span',
        array(
          'class' => 'sprite-menu phabricator-main-menu-message-icon',
        ),
        '');

      if ($message_count_number) {
        $container_classes[] = 'message-unread';
      }

      $message_tag = phutil_tag(
        'a',
        array(
          'href'  => '/conpherence/',
          'class' => implode(' ', $container_classes),
          'id'    => $message_id,
        ),
        array(
          $message_icon_tag,
          $message_count_tag,
        ));
    }

    $count_id = celerity_generate_unique_node_id();
    $dropdown_id = celerity_generate_unique_node_id();
    $bubble_id = celerity_generate_unique_node_id();

    $count_number = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    if ($count_number > 999) {
      $count_number = "\xE2\x88\x9E";
    }

    $count_tag = phutil_tag(
      'span',
      array(
        'id'    => $count_id,
        'class' => 'phabricator-main-menu-alert-count'
      ),
      $count_number);

    $icon_tag = phutil_tag(
      'span',
      array(
        'class' => 'sprite-menu phabricator-main-menu-alert-icon',
      ),
      '');

    if ($count_number) {
      $container_classes[] = 'alert-unread';
    }

    $bubble_tag = phutil_tag(
      'a',
      array(
        'href'  => '/notification/',
        'class' => implode(' ', $container_classes),
        'id'    => $bubble_id,
      ),
      array($icon_tag, $count_tag));

    Javelin::initBehavior(
      'aphlict-dropdown',
      array(
        'bubbleID'    => $bubble_id,
        'countID'     => $count_id,
        'dropdownID'  => $dropdown_id,
        'loadingText' => pht('Loading...'),
      ));

    $notification_dropdown = javelin_tag(
      'div',
      array(
        'id'    => $dropdown_id,
        'class' => 'phabricator-notification-menu',
        'sigil' => 'phabricator-notification-menu',
        'style' => 'display: none;',
      ),
      '');

    return array(
      hsprintf('%s%s', $bubble_tag, $message_tag),
      $notification_dropdown,
    );
  }

  private function renderMenuIcon($name) {
    return phutil_tag(
      'span',
      array(
        'class' => 'phabricator-core-menu-icon '.
                   'sprite-apps-large apps-'.$name,
      ),
      '');
  }

}
