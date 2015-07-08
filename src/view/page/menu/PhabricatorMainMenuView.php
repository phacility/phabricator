<?php

final class PhabricatorMainMenuView extends AphrontView {

  private $controller;
  private $applicationMenu;

  public function setApplicationMenu(PHUIListView $application_menu) {
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

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-main-menu-view');
    require_celerity_resource('sprite-main-header-css');

    $header_id = celerity_generate_unique_node_id();
    $menus = array();
    $alerts = array();
    $search_button = '';
    $app_button = '';
    $aural = null;

    if ($user->isLoggedIn() && $user->isUserActivated()) {
      list($menu, $dropdowns, $aural) = $this->renderNotificationMenu();
      if (array_filter($menu)) {
        $alerts[] = $menu;
      }
      $menus = array_merge($menus, $dropdowns);
      $app_button = $this->renderApplicationMenuButton($header_id);
      $search_button = $this->renderSearchMenuButton($header_id);
    } else {
      $app_button = $this->renderApplicationMenuButton($header_id);
      if (PhabricatorEnv::getEnvConfig('policy.allow-public')) {
        $search_button = $this->renderSearchMenuButton($header_id);
      }
    }

    $search_menu = $this->renderPhabricatorSearchMenu();

    if ($alerts) {
      $alerts = javelin_tag(
        'div',
        array(
          'class' => 'phabricator-main-menu-alerts',
          'aural' => false,
        ),
        $alerts);
    }

    if ($aural) {
      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        phutil_implode_html(' ', $aural));
    }

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $menus[] = $application->buildMainMenuExtraNodes(
        $user,
        $this->getController());
    }

    $application_menu = $this->renderApplicationMenu();
    $classes = array();
    $classes[] = 'phabricator-main-menu sprite-main-header';
    $classes[] = 'phabricator-main-menu-background';

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
        'id'    => $header_id,
      ),
      array(
        $app_button,
        $search_button,
        $this->renderPhabricatorLogo(),
        $alerts,
        $aural,
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
      $show_search = $user->isUserActivated();
    } else {
      $show_search = PhabricatorEnv::getEnvConfig('policy.allow-public');
    }

    if ($show_search) {
      $search = new PhabricatorMainMenuSearchView();
      $search->setUser($user);

      $application = null;
      $controller = $this->getController();
      if ($controller) {
        $application = $controller->getCurrentApplication();
      }
      if ($application) {
        $search->setApplication($application);
      }

      $result = $search;

      $pref_shortcut = PhabricatorUserPreferences::PREFERENCE_SEARCH_SHORTCUT;
      if ($user->loadPreferences()->getPreference($pref_shortcut, true)) {
        $keyboard_config['searchID'] = $search->getID();
      }
    }

    Javelin::initBehavior('phabricator-keyboard-shortcuts', $keyboard_config);

    if ($result) {
      $result = id(new PHUIListItemView())
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
            $button_id => 'menu-icon-selected',
          ),
        ),
      ),
      phutil_tag(
        'span',
        array(
          'class' => 'phabricator-menu-button-icon phui-icon-view '.
                     'phui-font-fa fa-bars',
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
      $app_actions = $application->buildMainMenuItems($user, $controller);
      foreach ($app_actions as $action) {
        $actions[] = $action;
      }
    }

    $actions = msort($actions, 'getOrder');

    $view = $this->getApplicationMenu();

    if (!$view) {
      $view = new PHUIListView();
    }

    $view->addClass('phabricator-dark-menu');
    $view->addClass('phabricator-application-menu');

    if ($actions) {
      $view->addMenuItem(
        id(new PHUIListItemView())
          ->setType(PHUIListItemView::TYPE_LABEL)
          ->setName(pht('Actions')));
      foreach ($actions as $action) {
        $view->addMenuItem($action);
      }
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
            $button_id => 'menu-icon-selected',
          ),
        ),
      ),
      phutil_tag(
      'span',
      array(
        'class' => 'phabricator-menu-button-icon phui-icon-view '.
                   'phui-font-fa fa-search',
        'id' => $button_id,
      ),
      ''));
  }

  private function renderPhabricatorSearchMenu() {

    $view = new PHUIListView();
    $view->addClass('phabricator-dark-menu');
    $view->addClass('phabricator-search-menu');

    $search = $this->renderSearch();
    if ($search) {
      $view->addMenuItem($search);
    }

    return $view;
  }

  private function renderPhabricatorLogo() {
    $style_logo = null;
    $custom_header = PhabricatorEnv::getEnvConfig('ui.custom-header');
    if ($custom_header) {
      $cache = PhabricatorCaches::getImmutableCache();
      $cache_key_logo = 'ui.custom-header.logo-phid.v1.'.$custom_header;
      $logo_uri = $cache->getKey($cache_key_logo);
      if (!$logo_uri) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($this->getUser())
          ->withPHIDs(array($custom_header))
          ->executeOne();
        if ($file) {
          $logo_uri = $file->getViewURI();
          $cache->setKey($cache_key_logo, $logo_uri);
        }
      }
      if ($logo_uri) {
        $style_logo =
          'background-size: 96px 40px; '.
          'background-position: 0px 0px; '.
          'background-image: url('.$logo_uri.');';
      }
    }

    $color = PhabricatorEnv::getEnvConfig('ui.header-color');
    if ($color == 'light') {
      $color = 'dark';
    } else {
      $color = 'light';
    }

    return phutil_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-brand',
        'href'  => '/',
      ),
      array(
        javelin_tag(
          'span',
          array(
            'aural' => true,
          ),
          pht('Home')),
        phutil_tag(
          'span',
          array(
            'class' => 'sprite-menu phabricator-main-menu-eye '.$color.'-eye',
          ),
          ''),
          phutil_tag(
          'span',
          array(
            'class' => 'sprite-menu phabricator-main-menu-logo '.$color.'-logo',
            'style' => $style_logo,
          ),
          ''),
      ));
  }

  private function renderNotificationMenu() {
    $user = $this->user;

    require_celerity_resource('phabricator-notification-css');
    require_celerity_resource('phabricator-notification-menu-css');

    $container_classes = array('alert-notifications');
    $aural = array();

    $dropdown_query = id(new AphlictDropdownDataQuery())
      ->setViewer($user);
    $dropdown_data = $dropdown_query->execute();

    $message_tag = '';
    $message_notification_dropdown = '';
    $conpherence_app = 'PhabricatorConpherenceApplication';
    $conpherence_data = $dropdown_data[$conpherence_app];
    if ($conpherence_data['isInstalled']) {
      $message_id = celerity_generate_unique_node_id();
      $message_count_id = celerity_generate_unique_node_id();
      $message_dropdown_id = celerity_generate_unique_node_id();

      $message_count_number = $conpherence_data['rawCount'];

      if ($message_count_number) {
        $aural[] = phutil_tag(
          'a',
          array(
            'href' => '/conpherence/',
          ),
          pht(
            '%s unread messages.',
            new PhutilNumber($message_count_number)));
      } else {
        $aural[] = pht('No messages.');
      }

      $message_count_tag = phutil_tag(
        'span',
        array(
          'id'    => $message_count_id,
          'class' => 'phabricator-main-menu-message-count',
        ),
        $conpherence_data['count']);

      $message_icon_tag = javelin_tag(
        'span',
        array(
          'class' => 'phabricator-main-menu-message-icon phui-icon-view '.
                     'phui-font-fa fa-comments',
          'sigil' => 'menu-icon',
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

      Javelin::initBehavior(
        'aphlict-dropdown',
        array(
          'bubbleID'    => $message_id,
          'countID'     => $message_count_id,
          'dropdownID'  => $message_dropdown_id,
          'loadingText' => pht('Loading...'),
          'uri'         => '/conpherence/panel/',
          'countType'   => $conpherence_data['countType'],
          'countNumber' => $message_count_number,
          'unreadClass' => 'message-unread',
        ));

      $message_notification_dropdown = javelin_tag(
        'div',
        array(
          'id'    => $message_dropdown_id,
          'class' => 'phabricator-notification-menu',
          'sigil' => 'phabricator-notification-menu',
          'style' => 'display: none;',
        ),
        '');
    }

    $bubble_tag = '';
    $notification_dropdown = '';
    $notification_app = 'PhabricatorNotificationsApplication';
    $notification_data = $dropdown_data[$notification_app];
    if ($notification_data['isInstalled']) {
      $count_id = celerity_generate_unique_node_id();
      $dropdown_id = celerity_generate_unique_node_id();
      $bubble_id = celerity_generate_unique_node_id();

      $count_number = $notification_data['rawCount'];

      if ($count_number) {
        $aural[] = phutil_tag(
          'a',
          array(
            'href' => '/notification/',
          ),
          pht(
            '%s unread notifications.',
            new PhutilNumber($count_number)));
      } else {
        $aural[] = pht('No notifications.');
      }

      $count_tag = phutil_tag(
        'span',
        array(
          'id'    => $count_id,
          'class' => 'phabricator-main-menu-alert-count',
        ),
        $notification_data['count']);

      $icon_tag = javelin_tag(
        'span',
        array(
          'class' => 'phabricator-main-menu-alert-icon phui-icon-view '.
                     'phui-font-fa fa-bell',
          'sigil' => 'menu-icon',
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
          'uri'         => '/notification/panel/',
          'countType'   => $notification_data['countType'],
          'countNumber' => $count_number,
          'unreadClass' => 'alert-unread',
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
    }

    $dropdowns = array(
      $notification_dropdown,
      $message_notification_dropdown,
    );

    return array(
      array(
        $bubble_tag,
        $message_tag,
      ),
      $dropdowns,
      $aural,
    );
  }

}
