<?php

abstract class PhabricatorHomeController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setBaseURI('/');
    $page->setTitle(idx($data, 'title'));

    $page->setGlyph("\xE2\x9A\x92");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildNav() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/'));

    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($user)
      ->withInstalled(true)
      ->withUnlisted(false)
      ->withLaunchable(true)
      ->execute();

    foreach ($applications as $key => $application) {
      $invisible = PhabricatorApplication::TILE_INVISIBLE;
      if ($application->getDefaultTileDisplay($user) == $invisible) {
        // Remove invisible applications (e.g., admin apps for non-admins).
        unset($applications[$key]);
      }
    }

    $pinned = $user->loadPreferences()->getPinnedApplications(
      $applications,
      $user);

    // Put "Applications" at the bottom.
    $meta_app = 'PhabricatorApplicationApplications';
    $pinned = array_fuse($pinned);
    unset($pinned[$meta_app]);
    $pinned[$meta_app] = $meta_app;

    $tiles = array();
    foreach ($pinned as $pinned_application) {
      if (empty($applications[$pinned_application])) {
        continue;
      }

      $application = $applications[$pinned_application];

      $tile = id(new PhabricatorApplicationLaunchView())
        ->setApplication($application)
        ->setApplicationStatus($application->loadStatus($user))
        ->setUser($user);

      $tiles[] = $tile;
    }

    $nav->addCustomBlock(
      phutil_tag(
        'div',
        array(
          'class' => 'application-tile-group',
        ),
        $tiles));

    $nav->addFilter(
      '',
      pht('Customize Applications...'),
      '/settings/panel/home/');

    $nav->addClass('phabricator-side-menu-home');
    $nav->selectFilter(null);

    return $nav;
  }

}
