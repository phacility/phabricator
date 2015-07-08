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

    $messages = $service->getServiceType()->getStatusMessages($service);
    if ($messages) {
      $box->setFormErrors($messages);
    }

    if ($service->getIsLocked()) {
      $this->addLockMessage(
        $box,
        pht('This service is locked, and can not be edited.'));
    }

    $bindings = $this->buildBindingList($service);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName());

    $timeline = $this->buildTransactionTimeline(
      $service,
      new AlmanacServiceTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $bindings,
        $this->buildAlmanacPropertiesTable($service),
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyList(AlmanacService $service) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($service);

    $properties->addProperty(
      pht('Service Type'),
      $service->getServiceType()->getServiceTypeShortName());

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

  private function buildBindingList(AlmanacService $service) {
    $viewer = $this->getViewer();
    $id = $service->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $service,
      PhabricatorPolicyCapability::CAN_EDIT);

    $bindings = id(new AlmanacBindingQuery())
      ->setViewer($viewer)
      ->withServicePHIDs(array($service->getPHID()))
      ->execute();

    $table = id(new AlmanacBindingTableView())
      ->setNoDataString(
        pht('This service has not been bound to any device interfaces yet.'))
      ->setUser($viewer)
      ->setBindings($bindings);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Service Bindings'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI("binding/edit/?serviceID={$id}"))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit)
          ->setText(pht('Add Binding'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-plus')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);
  }

}
