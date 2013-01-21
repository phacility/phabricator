<?php

final class PhabricatorApplicationsListController
  extends PhabricatorApplicationsController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $applications = PhabricatorApplication::getAllInstalledApplications();

    $list = $this->buildInstalledApplicationsList($applications);

    $title = pht('Installed Applications');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $nav->appendChild(
      array(
        $header,
        $list
      ));

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
      )
    );
  }


  private function buildInstalledApplicationsList(array $applications) {

     $list = new PhabricatorObjectItemListView();
     foreach ($applications as $applications) {
        $item = id(new PhabricatorObjectItemView())
          ->setHeader($applications->getName())
          ->addAttribute(
            phutil_escape_html($applications->getShortDescription()));
        $list->addItem($item);
      }

    return $list;
   }

}
