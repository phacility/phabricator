<?php

final class PhabricatorPackagesVersionViewController
  extends PhabricatorPackagesVersionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $publisher_key = $request->getURIData('publisherKey');
    $package_key = $request->getURIData('packageKey');
    $full_key = $publisher_key.'/'.$package_key;
    $version_key = $request->getURIData('versionKey');

    $version = id(new PhabricatorPackagesVersionQuery())
      ->setViewer($viewer)
      ->withFullKeys(array($full_key))
      ->withNames(array($version_key))
      ->executeOne();
    if (!$version) {
      return new Aphront404Response();
    }

    $package = $version->getPackage();
    $publisher = $package->getPublisher();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($publisher->getName(), $publisher->getURI())
      ->addTextCrumb($package->getName(), $package->getURI())
      ->addTextCrumb($version->getName())
      ->setBorder(true);

    $header = $this->buildHeaderView($version);
    $curtain = $this->buildCurtain($version);

    $timeline = $this->buildTransactionTimeline(
      $version,
      new PhabricatorPackagesVersionTransactionQuery());
    $timeline->setShouldTerminate(true);

    $version_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $version->getPHID(),
        ))
      ->appendChild($version_view);
  }


  private function buildHeaderView(PhabricatorPackagesVersion $version) {
    $viewer = $this->getViewer();
    $name = $version->getName();

    return id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($name)
      ->setPolicyObject($version)
      ->setHeaderIcon('fa-tag');
  }

  private function buildCurtain(PhabricatorPackagesVersion $version) {
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($version);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $version,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $version->getID();
    $edit_uri = $this->getApplicationURI("version/edit/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Version'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setHref($edit_uri));

    return $curtain;
  }

}
