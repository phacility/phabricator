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

    $properties = $this->buildPropertyList($network);
    $actions = $this->buildActionList($network);

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
      ->setMainColumn(array(
          $timeline,
        ))
      ->setPropertyList($properties)
      ->setActionList($actions);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildPropertyList(AlmanacNetwork $network) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($network);

    $properties->invokeWillRenderEvent();

    return $properties;
  }

  private function buildActionList(AlmanacNetwork $network) {
    $viewer = $this->getViewer();
    $id = $network->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $network,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Network'))
        ->setHref($this->getApplicationURI("network/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $actions;
  }

}
