<?php

final class PhabricatorApplicationsListController
  extends PhabricatorApplicationsController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $applications = PhabricatorApplication::getAllApplications();

    $list = $this->buildInstalledApplicationsList($applications);
    $title = pht('Installed Applications');
    $nav->appendChild($list);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Applications'))
          ->setHref($this->getApplicationURI()));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }


  private function buildInstalledApplicationsList(array $applications) {
    $list = new PhabricatorObjectItemListView();

    $applications = msort($applications, 'getName');

    foreach ($applications as $application) {
        $item = id(new PhabricatorObjectItemView())
          ->setHeader($application->getName())
          ->setHref('/applications/view/'.get_class($application).'/')
          ->addAttribute($application->getShortDescription());

        if (!$application->isInstalled()) {
          $item->addIcon('delete', pht('Uninstalled'));
        }

        if ($application->isBeta()) {
          $item->addIcon('lint-warning', pht('Beta'));
        }

        $list->addItem($item);

      }
    return $list;
   }

}
