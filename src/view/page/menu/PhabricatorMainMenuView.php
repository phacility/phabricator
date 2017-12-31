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

  private function getFaviconURI($type = null) {
    switch ($type) {
      case 'message':
        return celerity_get_resource_uri('/rsrc/favicons/favicon-message.ico');
      case 'mention':
        return celerity_get_resource_uri('/rsrc/favicons/favicon-mention.ico');
      default:
        return celerity_get_resource_uri('/rsrc/favicons/favicon.ico');
    }
  }

  public function render() {
    $viewer = $this->getViewer();

    require_celerity_resource('phabricator-main-menu-view');

    $header_id = celerity_generate_unique_node_id();
    $menu_bar = array();
    $alerts = array();
    $search_button = '';
    $app_button = '';
    $aural = null;

    $is_full = $this->isFullSession($viewer);

    if ($is_full) {
      list($menu, $dropdowns, $aural) = $this->renderNotificationMenu();
      if (array_filter($menu)) {
        $alerts[] = $menu;
      }
      $menu_bar = array_merge($menu_bar, $dropdowns);
      $app_button = $this->renderApplicationMenuButton();
      $search_button = $this->renderSearchMenuButton($header_id);
    } else if (!$viewer->isLoggedIn()) {
      $app_button = $this->renderApplicationMenuButton();
      if (PhabricatorEnv::getEnvConfig('policy.allow-public')) {
        $search_button = $this->renderSearchMenuButton($header_id);
      }
    }

    if ($search_button) {
      $search_menu = $this->renderPhabricatorSearchMenu();
    } else {
      $search_menu = null;
    }

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

    $extensions = PhabricatorMainMenuBarExtension::getAllEnabledExtensions();
    foreach ($extensions as $extension) {
      $extension
        ->setViewer($viewer)
        ->setIsFullSession($is_full);

      $controller = $this->getController();
      if ($controller) {
        $extension->setController($controller);
        $application = $controller->getCurrentApplication();
        if ($application) {
          $extension->setApplication($application);
        }
      }
    }

    if (!$is_full) {
      foreach ($extensions as $key => $extension) {
        if ($extension->shouldRequireFullSession()) {
          unset($extensions[$key]);
        }
      }
    }

    foreach ($extensions as $key => $extension) {
      if (!$extension->isExtensionEnabledForViewer($extension->getViewer())) {
        unset($extensions[$key]);
      }
    }

    $menus = array();
    foreach ($extensions as $extension) {
      foreach ($extension->buildMainMenus() as $menu) {
        $menus[] = $menu;
      }
    }

    // Because we display these with "float: right", reverse their order before
    // rendering them into the document so that the extension order and display
    // order are the same.
    $menus = array_reverse($menus);

    foreach ($menus as $menu) {
      $menu_bar[] = $menu;
    }

    $classes = array();
    $classes[] = 'phabricator-main-menu';
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
        $search_menu,
        $menu_bar,
      ));
  }

  private function renderSearch() {
    $viewer = $this->getViewer();

    $result = null;

    $keyboard_config = array(
      'helpURI' => '/help/keyboardshortcut/',
    );

    if ($viewer->isLoggedIn()) {
      $show_search = $viewer->isUserActivated();
    } else {
      $show_search = PhabricatorEnv::getEnvConfig('policy.allow-public');
    }

    if ($show_search) {
      $search = new PhabricatorMainMenuSearchView();
      $search->setViewer($viewer);

      $application = null;
      $controller = $this->getController();
      if ($controller) {
        $application = $controller->getCurrentApplication();
      }
      if ($application) {
        $search->setApplication($application);
      }

      $result = $search;
      $keyboard_config['searchID'] = $search->getID();
    }

    $keyboard_config['pht'] = array(
      '/' => pht('Give keyboard focus to the search box.'),
      '?' => pht('Show keyboard shortcut help for the current page.'),
    );

    Javelin::initBehavior(
      'phabricator-keyboard-shortcuts',
      $keyboard_config);

    if ($result) {
      $result = id(new PHUIListItemView())
        ->addClass('phabricator-main-menu-search')
        ->appendChild($result);
    }

    return $result;
  }

  public function renderApplicationMenuButton() {
    $dropdown = $this->renderApplicationMenu();
    if (!$dropdown) {
      return null;
    }

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setHref('#')
      ->setIcon('fa-bars')
      ->addClass('phabricator-core-user-menu')
      ->addClass('phabricator-core-user-mobile-menu')
      ->setNoCSS(true)
      ->setDropdownMenu($dropdown);
  }

  private function renderApplicationMenu() {
    $viewer = $this->getViewer();
    $view = $this->getApplicationMenu();
    if ($view) {
      $items = $view->getItems();
      $view = id(new PhabricatorActionListView())
        ->setViewer($viewer);
      foreach ($items as $item) {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setName($item->getName())
            ->setHref($item->getHref())
            ->setType($item->getType()));
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
    $view->addClass('phabricator-search-menu');

    $search = $this->renderSearch();
    if ($search) {
      $view->addMenuItem($search);
    }

    return $view;
  }

  private function renderPhabricatorLogo() {
    $custom_header = PhabricatorCustomLogoConfigType::getLogoImagePHID();

    $logo_style = array();
    if ($custom_header) {
      $cache = PhabricatorCaches::getImmutableCache();
      $cache_key_logo = 'ui.custom-header.logo-phid.v3.'.$custom_header;

      $logo_uri = $cache->getKey($cache_key_logo);
      if (!$logo_uri) {
        // NOTE: If the file policy has been changed to be restrictive, we'll
        // miss here and just show the default logo. The cache will fill later
        // when someone who can see the file loads the page. This might be a
        // little spooky, see T11982.
        $files = id(new PhabricatorFileQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs(array($custom_header))
          ->execute();
        $file = head($files);
        if ($file) {
          $logo_uri = $file->getViewURI();
          $cache->setKey($cache_key_logo, $logo_uri);
        }
      }

      if ($logo_uri) {
        $logo_style[] = 'background-size: 40px 40px;';
        $logo_style[] = 'background-position: 0 0;';
        $logo_style[] = 'background-image: url('.$logo_uri.')';
      }
    }

    $logo_node = phutil_tag(
      'span',
      array(
        'class' => 'phabricator-main-menu-eye',
        'style' => implode(' ', $logo_style),
      ));


    $wordmark_text = PhabricatorCustomLogoConfigType::getLogoWordmark();
    if (!strlen($wordmark_text)) {
      $wordmark_text = pht('Phabricator');
    }

    $wordmark_node = phutil_tag(
      'span',
      array(
        'class' => 'phabricator-wordmark',
      ),
      $wordmark_text);

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
        $logo_node,
        $wordmark_node,
      ));
  }

  private function renderNotificationMenu() {
    $viewer = $this->getViewer();

    require_celerity_resource('phabricator-notification-css');
    require_celerity_resource('phabricator-notification-menu-css');

    $container_classes = array('alert-notifications');
    $aural = array();

    $dropdown_query = id(new AphlictDropdownDataQuery())
      ->setViewer($viewer);
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
          'favicon'     => $this->getFaviconURI('default'),
          'message_favicon' => $this->getFaviconURI('message'),
          'mention_favicon' => $this->getFaviconURI('mention'),
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
          'favicon'     => $this->getFaviconURI('default'),
          'message_favicon' => $this->getFaviconURI('message'),
          'mention_favicon' => $this->getFaviconURI('mention'),
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

    // Admin Level Urgent Notification Channel
    $setup_tag = '';
    $setup_notification_dropdown = '';
    if ($viewer && $viewer->getIsAdmin()) {
      $open = PhabricatorSetupCheck::getOpenSetupIssueKeys();
      if ($open) {
        $setup_id = celerity_generate_unique_node_id();
        $setup_count_id = celerity_generate_unique_node_id();
        $setup_dropdown_id = celerity_generate_unique_node_id();

        $setup_count_number = count($open);

        if ($setup_count_number) {
          $aural[] = phutil_tag(
            'a',
            array(
              'href' => '/config/issue/',
            ),
            pht(
              '%s unresolved issues.',
              new PhutilNumber($setup_count_number)));
        } else {
          $aural[] = pht('No issues.');
        }

        $setup_count_tag = phutil_tag(
          'span',
          array(
            'id'    => $setup_count_id,
            'class' => 'phabricator-main-menu-setup-count',
          ),
          $setup_count_number);

        $setup_icon_tag = javelin_tag(
          'span',
          array(
            'class' => 'phabricator-main-menu-setup-icon phui-icon-view '.
                       'phui-font-fa fa-exclamation-circle',
            'sigil' => 'menu-icon',
          ),
          '');

        if ($setup_count_number) {
          $container_classes[] = 'setup-unread';
        }

        $setup_tag = phutil_tag(
          'a',
          array(
            'href'  => '/config/issue/',
            'class' => implode(' ', $container_classes),
            'id'    => $setup_id,
          ),
          array(
            $setup_icon_tag,
            $setup_count_tag,
          ));

        Javelin::initBehavior(
          'aphlict-dropdown',
          array(
            'bubbleID'    => $setup_id,
            'countID'     => $setup_count_id,
            'dropdownID'  => $setup_dropdown_id,
            'loadingText' => pht('Loading...'),
            'uri'         => '/config/issue/panel/',
            'countType'   => null,
            'countNumber' => null,
            'unreadClass' => 'setup-unread',
            'favicon'     => $this->getFaviconURI('default'),
            'message_favicon' => $this->getFaviconURI('message'),
            'mention_favicon' => $this->getFaviconURI('mention'),
          ));

        $setup_notification_dropdown = javelin_tag(
          'div',
          array(
            'id'    => $setup_dropdown_id,
            'class' => 'phabricator-notification-menu',
            'sigil' => 'phabricator-notification-menu',
            'style' => 'display: none;',
          ),
          '');
      }
    }

    $user_dropdown = null;
    $user_tag = null;
    if ($viewer->isLoggedIn()) {
      if (!$viewer->getIsEmailVerified()) {
        $bubble_id = celerity_generate_unique_node_id();
        $count_id = celerity_generate_unique_node_id();
        $dropdown_id = celerity_generate_unique_node_id();

        $settings_uri = id(new PhabricatorEmailAddressesSettingsPanel())
          ->setViewer($viewer)
          ->setUser($viewer)
          ->getPanelURI();

        $user_icon = javelin_tag(
          'span',
          array(
            'class' => 'phabricator-main-menu-setup-icon phui-icon-view '.
                       'phui-font-fa fa-user',
            'sigil' => 'menu-icon',
          ));

        $user_count = javelin_tag(
          'span',
          array(
            'class' => 'phabricator-main-menu-setup-count',
            'id' => $count_id,
          ),
          1);

        $user_tag = phutil_tag(
          'a',
          array(
            'href' => $settings_uri,
            'class' => 'setup-unread',
            'id' => $bubble_id,
          ),
          array(
            $user_icon,
            $user_count,
          ));

        Javelin::initBehavior(
          'aphlict-dropdown',
          array(
            'bubbleID' => $bubble_id,
            'countID' => $count_id,
            'dropdownID' => $dropdown_id,
            'loadingText' => pht('Loading...'),
            'uri' => '/settings/issue/',
            'unreadClass' => 'setup-unread',
          ));

        $user_dropdown = javelin_tag(
          'div',
          array(
            'id'    => $dropdown_id,
            'class' => 'phabricator-notification-menu',
            'sigil' => 'phabricator-notification-menu',
            'style' => 'display: none;',
          ));
      }
    }

    $dropdowns = array(
      $notification_dropdown,
      $message_notification_dropdown,
      $setup_notification_dropdown,
      $user_dropdown,
    );

    return array(
      array(
        $bubble_tag,
        $message_tag,
        $setup_tag,
        $user_tag,
      ),
      $dropdowns,
      $aural,
    );
  }

  private function isFullSession(PhabricatorUser $viewer) {
    if (!$viewer->isLoggedIn()) {
      return false;
    }

    if (!$viewer->isUserActivated()) {
      return false;
    }

    if (!$viewer->hasSession()) {
      return false;
    }

    $session = $viewer->getSession();
    if ($session->getIsPartial()) {
      return false;
    }

    if (!$session->getSignedLegalpadDocuments()) {
      return false;
    }

    $mfa_key = 'security.require-multi-factor-auth';
    $need_mfa = PhabricatorEnv::getEnvConfig($mfa_key);
    if ($need_mfa) {
      $have_mfa = $viewer->getIsEnrolledInMultiFactor();
      if (!$have_mfa) {
        return false;
      }
    }

    return true;
  }

}
