<?php

final class PhabricatorPackagesPackageViewController
  extends PhabricatorPackagesPackageController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $publisher_key = $request->getURIData('publisherKey');
    $package_key = $request->getURIData('packageKey');
    $full_key = $publisher_key.'/'.$package_key;

    $package = id(new PhabricatorPackagesPackageQuery())
      ->setViewer($viewer)
      ->withFullKeys(array($full_key))
      ->executeOne();
    if (!$package) {
      return new Aphront404Response();
    }

    $publisher = $package->getPublisher();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($publisher->getName(), $publisher->getURI())
      ->addTextCrumb($package->getName())
      ->setBorder(true);

    $header = $this->buildHeaderView($package);
    $curtain = $this->buildCurtain($package);

    $timeline = $this->buildTransactionTimeline(
      $package,
      new PhabricatorPackagesPackageTransactionQuery());

    $package_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $package->getPHID(),
        ))
      ->appendChild($package_view);
  }


  private function buildHeaderView(PhabricatorPackagesPackage $package) {
    $viewer = $this->getViewer();
    $name = $package->getName();

    return id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($name)
      ->setPolicyObject($package)
      ->setHeaderIcon('fa-gift');
  }

  private function buildCurtain(PhabricatorPackagesPackage $package) {
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($package);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $package,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $package->getID();
    $edit_uri = $this->getApplicationURI("package/edit/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Package'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setHref($edit_uri));

    return $curtain;
  }

}
