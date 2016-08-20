<?php

final class PhabricatorGuideQuickStartController
  extends PhabricatorGuideController {

  public function shouldAllowPublic() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    require_celerity_resource('guides-app-css');
    $viewer = $request->getViewer();

    $title = pht('Quick Start Guide');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('quickstart/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Quick Start'));

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

    $title = pht('Configure Applications');
    $apps_check = true;
    $href = PhabricatorEnv::getURI('/applications/');
    if ($apps_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've uninstalled any unneeded applications for now.");
    } else {
      $icon = 'fa-globe';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('Use all our applications, or uninstall the ones you don\'t want.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Invite Collaborators');
    $people_check = true;
    $href = PhabricatorEnv::getURI('/people/invite/');
    if ($people_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        'You will not be alone on this journey.');
    } else {
      $icon = 'fa-group';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('Invite the rest of your team to get started on Phabricator.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Create a Repository');
    $repository_check = true;
    $href = PhabricatorEnv::getURI('/diffusion/');
    if ($repository_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've created at least one repository.");
    } else {
      $icon = 'fa-code';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('If you are here for code review, let\'s set up your first '.
        'repository.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Create a Project');
    $project_check = true;
    $href = PhabricatorEnv::getURI('/project/');
    if ($project_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've created at least one project.");
    } else {
      $icon = 'fa-briefcase';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('Project tags define everything. Create them for teams, tags, '.
          'or actual projects.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Build a Dashboard');
    $dashboard_check = true;
    $href = PhabricatorEnv::getURI('/dashboard/');
    if ($dashboard_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        "You've created at least one dashboard.");
    } else {
      $icon = 'fa-dashboard';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('Customize the default homepage layout and items.');
    }

    $item = id(new PhabricatorGuideItemView())
      ->setTitle($title)
      ->setHref($href)
      ->setIcon($icon)
      ->setIconBackground($icon_bg)
      ->setSkipHref($skip)
      ->setDescription($description);
    $guide_items->addItem($item);


    $title = pht('Personalize your Install');
    $ui_check = true;
    $href = PhabricatorEnv::getURI('/config/group/ui/');
    if ($dashboard_check) {
      $icon = 'fa-check';
      $icon_bg = 'bg-green';
      $skip = null;
      $description = pht(
        'It looks amazing, good work. Home Sweet Home.');
    } else {
      $icon = 'fa-home';
      $icon_bg = 'bg-sky';
      $skip = '#';
      $description =
        pht('Change the name and add your company logo, just to give it a '.
          'little extra polish.');
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
