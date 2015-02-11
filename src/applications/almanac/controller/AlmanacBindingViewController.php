<?php

final class AlmanacBindingViewController
  extends AlmanacServiceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');

    $binding = id(new AlmanacBindingQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$binding) {
      return new Aphront404Response();
    }

    $service = $binding->getService();
    $service_uri = $service->getURI();

    $title = pht('Binding %s', $binding->getID());

    $property_list = $this->buildPropertyList($binding);
    $action_list = $this->buildActionList($binding);
    $property_list->setActionList($action_list);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($title)
      ->setPolicyObject($binding);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    if ($binding->getService()->getIsLocked()) {
      $this->addLockMessage(
        $box,
        pht(
          'This service for this binding is locked, so the binding can '.
          'not be edited.'));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName(), $service_uri);
    $crumbs->addTextCrumb($title);

    $timeline = $this->buildTransactionTimeline(
      $binding,
      new AlmanacBindingTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $this->buildAlmanacPropertiesTable($binding),
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyList(AlmanacBinding $binding) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $handles = $this->loadViewerHandles(
      array(
        $binding->getServicePHID(),
        $binding->getDevicePHID(),
        $binding->getInterface()->getNetworkPHID(),
      ));

    $properties->addProperty(
      pht('Service'),
      $handles[$binding->getServicePHID()]->renderLink());

    $properties->addProperty(
      pht('Device'),
      $handles[$binding->getDevicePHID()]->renderLink());

    $properties->addProperty(
      pht('Network'),
      $handles[$binding->getInterface()->getNetworkPHID()]->renderLink());

    $properties->addProperty(
      pht('Interface'),
      $binding->getInterface()->renderDisplayAddress());

    return $properties;
  }

  private function buildActionList(AlmanacBinding $binding) {
    $viewer = $this->getViewer();
    $id = $binding->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $binding,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Binding'))
        ->setHref($this->getApplicationURI("binding/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $actions;
  }

}
