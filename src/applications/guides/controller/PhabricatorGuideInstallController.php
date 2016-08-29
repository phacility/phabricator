<?php

final class PhabricatorGuideInstallController
  extends PhabricatorGuideController {

  public function shouldAllowPublic() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $title = pht('Installation Guide');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('install/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Installation'));

    $content = $this->getGuideContent($viewer);

    $view = id(new PHUICMSView())
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->setHeader($header)
      ->setContent($content);

    return $this->newPage()
      ->setTitle($title)
      ->addClass('phui-cms-body')
      ->appendChild($view);

  }

  private function getGuideContent($viewer) {
    $guide_items = new PhabricatorGuideListView();

    $title = pht('Resolve Setup Issues');
    $issues_resolved = !PhabricatorSetupCheck::getOpenSetupIssueKeys();
    $href = PhabricatorEnv::getURI('/config/issue/');
    if ($issues_resolved) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've resolved (or ignored) all outstanding setup issues.");
    } else {
      $icon = 'fa-warning';
      $icon_bg = 'bg-red';
      $skip = '#';
      $description =
        pht('You have some unresolved setup issues to take care of.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();

    $title = pht('Login and Registration');
    $href = PhabricatorEnv::getURI('/auth/');
    $have_auth = (bool)$configs;
    if ($have_auth) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've configured at least one authentication provider.");
    } else {
      $icon = 'fa-key';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description = pht(
        'Authentication providers allow users to register accounts and '.
        'log in to Phabricator.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Configure Phabricator');
    $href = PhabricatorEnv::getURI('/config/');

    // Just load any config value at all; if one exists the install has figured
    // out how to configure things.
    $have_config = (bool)id(new PhabricatorConfigEntry())->loadAllWhere(
      '1 = 1 LIMIT 1');

    if ($have_config) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've configured at least one setting from the web interface.");
    } else {
      $icon = 'fa-sliders';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description = pht(
        'Learn how to configure mail and other options in Phabricator.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('User Account Settings');
    $href = PhabricatorEnv::getURI('/settings/');
    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUsers(array($viewer))
      ->executeOne();

    $have_settings = ($preferences && $preferences->getPreferences());
    if ($have_settings) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've adjusted at least one setting on your account.");
    } else {
      $icon = 'fa-wrench';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description = pht(
        'Configure account settings for all users, or just yourself');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Notification Server');
    $href = PhabricatorEnv::getURI('/config/notifications/');
    // TODO: Wire up a notifications check
    $have_notifications = false;
    if ($have_notifications) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've set up a real-time notification server.");
    } else {
      $icon = 'fa-bell';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description = pht(
        'Phabricator can deliver notifications in real-time with WebSockets.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);

    $guide_items->addItem($item);

    return $guide_items;
  }
}
