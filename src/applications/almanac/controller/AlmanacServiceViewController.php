<?php

final class AlmanacServiceViewController
  extends AlmanacServiceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $name = $request->getURIData('name');

    $service = id(new AlmanacServiceQuery())
      ->setViewer($viewer)
      ->withNames(array($name))
      ->executeOne();
    if (!$service) {
      return new Aphront404Response();
    }

    $title = pht('Service %s', $service->getName());

    $property_list = $this->buildPropertyList($service);
    $action_list = $this->buildActionList($service);
    $property_list->setActionList($action_list);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($service->getName())
      ->setPolicyObject($service);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName());

    $xactions = id(new AlmanacServiceTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($service->getPHID()))
      ->execute();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($service->getPHID())
      ->setTransactions($xactions)
      ->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $xaction_view,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyList(AlmanacService $service) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    return $properties;
  }

  private function buildActionList(AlmanacService $service) {
    $viewer = $this->getViewer();
    $id = $service->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $service,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Service'))
        ->setHref($this->getApplicationURI("service/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $actions;
  }

}
