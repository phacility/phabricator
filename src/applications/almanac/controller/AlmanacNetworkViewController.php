<?php

final class AlmanacNetworkViewController
  extends AlmanacNetworkController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $network = id(new AlmanacNetworkQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$network) {
      return new Aphront404Response();
    }

    $title = pht('Network %s', $network->getName());

    $curtain = $this->buildCurtain($network);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($network->getName())
      ->setHeaderIcon('fa-globe')
      ->setPolicyObject($network);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($network->getName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $network,
      new AlmanacNetworkTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }


  private function buildCurtain(AlmanacNetwork $network) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $network,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $network->getID();
    $edit_uri = $this->getApplicationURI("network/edit/{$id}/");

    $curtain = $this->newCurtainView($network);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Network'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

}
