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
      ->needProperties(true)
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

    if ($binding->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    if ($binding->getService()->isClusterService()) {
      $this->addClusterMessage(
        $box,
        pht('The service for this binding is a cluster service.'),
        pht(
          'The service for this binding is a cluster service. You do not '.
          'have permission to manage cluster services, so this binding can '.
          'not be edited.'));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName(), $service_uri);
    $crumbs->addTextCrumb($title);

    $timeline = $this->buildTransactionTimeline(
      $binding,
      new AlmanacBindingTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $box,
          $this->buildAlmanacPropertiesTable($binding),
          $timeline,
      ));
  }

  private function buildPropertyList(AlmanacBinding $binding) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(
      pht('Service'),
      $viewer->renderHandle($binding->getServicePHID()));

    $properties->addProperty(
      pht('Device'),
      $viewer->renderHandle($binding->getDevicePHID()));

    $properties->addProperty(
      pht('Network'),
      $viewer->renderHandle($binding->getInterface()->getNetworkPHID()));

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

    if ($binding->getIsDisabled()) {
      $disable_icon = 'fa-check';
      $disable_text = pht('Enable Binding');
    } else {
      $disable_icon = 'fa-ban';
      $disable_text = pht('Disable Binding');
    }

    $disable_href = $this->getApplicationURI("binding/disable/{$id}/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon($disable_icon)
        ->setName($disable_text)
        ->setHref($disable_href)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $actions;
  }

}
