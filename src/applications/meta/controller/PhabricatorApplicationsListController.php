<?php

final class PhabricatorApplicationsListController
  extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

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

      $view[] = id(new PhabricatorHeaderView())
        ->setHeader($groups[$group]);

      $view[] = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-application-list',
        ),
        id(new AphrontNullView())->appendChild($views)->render());
    }

    return $this->buildApplicationPage(
      $view,
      array(
        'title' => 'Applications',
        'device' => true,
      ));
  }

}

