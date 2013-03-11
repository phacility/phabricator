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

    if ($user->isLoggedIn()) {
      list($menu, $dropdown) = $this->renderNotificationMenu();
      $alerts[] = $menu;
      $menus[] = $dropdown;
    }

    $phabricator_menu = $this->renderPhabricatorMenu();

    if ($alerts) {
      $alerts = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-main-menu-alerts',
        ),
        $alerts);
    }

    $application_menu = $this->getApplicationMenu();
    if ($application_menu) {
      $application_menu->addClass('phabricator-dark-menu');
      $application_menu->addClass('phabricator-application-menu');
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-main-menu',
        'id'    => $header_id,
      ),
      array(
        $this->renderPhabricatorMenuButton($header_id),
        $application_menu
          ? $this->renderApplicationMenuButton($header_id)
          : null,
        $this->renderPhabricatorLogo(),
        $alerts,
        $phabricator_menu,
        $application_menu,
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

  private function renderPhabricatorMenuButton($header_id) {
    $button_id = celerity_generate_unique_node_id();
    return javelin_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-expand-button '.
                   'phabricator-expand-core-menu',
        'sigil' => 'jx-toggle-class',
        'meta'  => array(
          'map' => array(
            $header_id => 'phabricator-core-menu-expanded',
            $button_id => 'menu-icon-eye-blue',
          ),
        ),
      ),
      phutil_tag(
        'span',
        array(
          'class' => 'phabricator-menu-button-icon sprite-menu menu-icon-eye',
          'id' => $button_id,
        ),
        ''));
  }

  public function renderApplicationMenuButton($header_id) {
    $button_id = celerity_generate_unique_node_id();
    return javelin_tag(
      'a',
      array(
        'class' => 'phabricator-main-menu-expand-button '.
                   'phabricator-expand-application-menu',
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

  private function renderPhabricatorMenu() {
    $user = $this->getUser();
    $controller = $this->getController();

    $applications = PhabricatorApplication::getAllInstalledApplications();

    $core = array();
    $more = array();
    $actions = array();

    require_celerity_resource('sprite-apps-large-css');

    $group_core = PhabricatorApplication::GROUP_CORE;
    foreach ($applications as $application) {
      if ($application->shouldAppearInLaunchView()) {
        $icon = $application->getIconName().'-light-large';

        $item = id(new PhabricatorMenuItemView())
          ->setName($application->getName())
          ->setHref($application->getBaseURI())
          ->appendChild($this->renderMenuIcon($icon));
        if ($application->getApplicationGroup() == $group_core) {
          $core[] = $item;
        } else {
          $more[$application->getName()] = $item;
        }
      }

      $app_actions = $application->buildMainMenuItems($user, $controller);
      foreach ($app_actions as $action) {
        $actions[] = $action;
      }
    }


    $view = new PhabricatorMenuView();
    $view->addClass('phabricator-dark-menu');
    $view->addClass('phabricator-core-menu');

    $search = $this->renderSearch();
    if ($search) {
      $view->addMenuItem($search);
    }

    $view
      ->newLabel(pht('Home'))
      ->addClass('phabricator-core-item-device');
    $view->addMenuItem(
      id(new PhabricatorMenuItemView())
        ->addClass('phabricator-core-item-device')
        ->setName(pht('Phabricator Home'))
        ->setHref('/')
        ->appendChild($this->renderMenuIcon('logo-light-large')));
    if ($controller && $controller->getCurrentApplication()) {
      $application = $controller->getCurrentApplication();
      $icon = $application->getIconName().'-light-large';
      $view->addMenuItem(
        id(new PhabricatorMenuItemView())
          ->addClass('phabricator-core-item-device')
          ->setName(pht('%s Home', $application->getName()))
          ->appendChild($this->renderMenuIcon($icon))
          ->setHref($controller->getApplicationURI()));
    }

    if ($core) {
      $view->addMenuItem(
        id(new PhabricatorMenuItemView())
          ->addClass('phabricator-core-item-device')
          ->setType(PhabricatorMenuItemView::TYPE_LABEL)
          ->setName(pht('Core Applications')));
      foreach ($core as $item) {
        $item->addClass('phabricator-core-item-device');
        $view->addMenuItem($item);
      }
    }

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

    if ($more) {
      $view->addMenuItem(
        id(new PhabricatorMenuItemView())
          ->addClass('phabricator-core-item-device')
          ->setType(PhabricatorMenuItemView::TYPE_LABEL)
          ->setName(pht('More Applications')));
      ksort($more);
      foreach ($more as $item) {
        $item->addClass('phabricator-core-item-device');
        $view->addMenuItem($item);
      }
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

    $conpherence = id(new PhabricatorApplicationConpherence())->isBeta();
    $allow_beta =
      PhabricatorEnv::getEnvConfig('phabricator.show-beta-applications');
    $message_tag = '';

    if (!$conpherence || $allow_beta) {
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
                   'sprite-apps-large app-'.$name,
      ),
      '');
  }

}
