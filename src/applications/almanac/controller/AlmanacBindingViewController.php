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

    $properties = $this->buildPropertyList($binding);
    $details = $this->buildPropertySection($binding);
    $curtain = $this->buildCurtain($binding);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($title)
      ->setPolicyObject($binding)
      ->setHeaderIcon('fa-object-group');

    if ($binding->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    }

    $issue = null;
    if ($binding->getService()->isClusterService()) {
      $issue = $this->addClusterMessage(
        pht('The service for this binding is a cluster service.'),
        pht(
          'The service for this binding is a cluster service. You do not '.
          'have permission to manage cluster services, so this binding can '.
          'not be edited.'));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName(), $service_uri);
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $binding,
      new AlmanacBindingTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $issue,
          $this->buildAlmanacPropertiesTable($binding),
          $timeline,
        ))
      ->addPropertySection(pht('Details'), $details);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildPropertySection(AlmanacBinding $binding) {
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

  private function buildPropertyList(AlmanacBinding $binding) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($binding);
    $properties->invokeWillRenderEvent();

    return $properties;
  }

  private function buildCurtain(AlmanacBinding $binding) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $binding,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $binding->getID();
    $edit_uri = $this->getApplicationURI("binding/edit/{$id}/");
    $disable_uri = $this->getApplicationURI("binding/disable/{$id}/");

    $curtain = $this->newCurtainView($binding);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Binding'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    if ($binding->getIsDisabled()) {
      $disable_icon = 'fa-check';
      $disable_text = pht('Enable Binding');
    } else {
      $disable_icon = 'fa-ban';
      $disable_text = pht('Disable Binding');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($disable_icon)
        ->setName($disable_text)
        ->setHref($disable_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

}
