<?php

final class PhabricatorApplicationDetailViewController
  extends PhabricatorApplicationsController{

  private $application;

  public function willProcessRequest(array $data) {
    $this->application = $data['application'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $selected = null;
    $applications = PhabricatorApplication::getAllInstalledApplications();

    foreach ($applications as $application) {
      if (get_class($application) == $this->application) {
        $selected = $application;
        break;
      }
    }

    if (!$selected) {
      return new Aphront404Response();
    }

    $title = $selected->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Applications'))
        ->setHref($this->getApplicationURI()));

    $properties = $this->buildPropertyView($selected);
    $actions = $this->buildActionView($user);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        id(new PhabricatorHeaderView())->setHeader($title),
        $actions,
        $properties,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildPropertyView(PhabricatorApplication $selected) {
    $properties = new PhabricatorPropertyListView();

    $properties->addProperty(
      pht('Status'), pht('Installed'));

    $properties->addProperty(
      pht('Description'), $selected->getShortDescription());

    return $properties;
  }

  private function buildActionView(PhabricatorUser $user) {

    return id(new PhabricatorActionListView())
      ->setUser($user)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Uninstall'))
          ->setIcon('delete')
     );
  }

}
