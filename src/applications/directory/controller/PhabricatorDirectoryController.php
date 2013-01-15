<?php

abstract class PhabricatorDirectoryController extends PhabricatorController {

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

    $applications = PhabricatorApplication::getAllInstalledApplications();

    foreach ($applications as $key => $application) {
      if (!$application->shouldAppearInLaunchView()) {
        unset($applications[$key]);
      }
    }

    $groups = PhabricatorApplication::getApplicationGroups();

    $applications = msort($applications, 'getApplicationOrder');
    $applications = mgroup($applications, 'getApplicationGroup');
    $applications = array_select_keys($applications, array_keys($groups));

    $view = array();
    foreach ($applications as $group => $application_list) {
      $status = array();
      foreach ($application_list as $key => $application) {
        $status[$key] = $application->loadStatus($user);
      }

      $views = array();
      foreach ($application_list as $key => $application) {
        $views[] = id(new PhabricatorApplicationLaunchView())
          ->setApplication($application)
          ->setApplicationStatus(idx($status, $key, array()))
          ->setUser($user);
      }

      while (count($views) % 4) {
        $views[] = id(new PhabricatorApplicationLaunchView());
      }

      $nav->addLabel($groups[$group]);
      $nav->addCustomBlock(
        phutil_render_tag(
          'div',
          array(
            'class' => 'application-tile-group',
          ),
          id(new AphrontNullView())->appendChild($views)->render()));
    }

    $nav->addClass('phabricator-side-menu-home');
    $nav->selectFilter(null);

    return $nav;
  }

}
