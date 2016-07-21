<?php

final class PhabricatorPackagesPublisherViewController
  extends PhabricatorPackagesPublisherController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $publisher_key = $request->getURIData('publisherKey');

    $publisher = id(new PhabricatorPackagesPublisherQuery())
      ->setViewer($viewer)
      ->withPublisherKeys(array($publisher_key))
      ->executeOne();
    if (!$publisher) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(
        pht('Publishers'),
        $this->getApplicationURI('publisher/'))
      ->addTextCrumb($publisher->getName())
      ->setBorder(true);

    $header = $this->buildHeaderView($publisher);
    $curtain = $this->buildCurtain($publisher);

    $timeline = $this->buildTransactionTimeline(
      $publisher,
      new PhabricatorPackagesPublisherTransactionQuery());

    $publisher_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $publisher->getPHID(),
        ))
      ->appendChild($publisher_view);
  }


  private function buildHeaderView(PhabricatorPackagesPublisher $publisher) {
    $viewer = $this->getViewer();
    $name = $publisher->getName();

    return id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($name)
      ->setPolicyObject($publisher)
      ->setHeaderIcon('fa-paw');
  }

  private function buildCurtain(PhabricatorPackagesPublisher $publisher) {
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($publisher);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $publisher,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $publisher->getID();
    $edit_uri = $this->getApplicationURI("publisher/edit/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Publisher'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setHref($edit_uri));

    return $curtain;
  }

}
