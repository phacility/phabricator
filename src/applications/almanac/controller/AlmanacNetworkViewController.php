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

    $property_list = $this->buildPropertyList($network);
    $action_list = $this->buildActionList($network);
    $property_list->setActionList($action_list);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($network->getName())
      ->setPolicyObject($network);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($network->getName());

    $timeline = $this->buildTransactionTimeline(
      $network,
      new AlmanacNetworkTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyList(AlmanacNetwork $network) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

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
