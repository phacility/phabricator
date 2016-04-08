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
      ->needProperties(true)
      ->executeOne();
    if (!$service) {
      return new Aphront404Response();
    }

    $title = pht('Service %s', $service->getName());

    $curtain = $this->buildCurtain($service);
    $details = $this->buildPropertySection($service);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($service->getName())
      ->setPolicyObject($service)
      ->setHeaderIcon('fa-plug');

    $issue = null;
    if ($service->isClusterService()) {
      $issue = $this->addClusterMessage(
        pht('This is a cluster service.'),
        pht(
          'This service is a cluster service. You do not have permission to '.
          'edit cluster services, so you can not edit this service.'));
    }

    $bindings = $this->buildBindingList($service);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($service->getName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $service,
      new AlmanacServiceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $issue,
          $details,
          $bindings,
          $this->buildAlmanacPropertiesTable($service),
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildPropertySection(
    AlmanacService $service) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(
      pht('Service Type'),
      $service->getServiceImplementation()->getServiceTypeShortName());

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

  private function buildCurtain(AlmanacService $service) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $service,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $service->getID();
    $edit_uri = $this->getApplicationURI("service/edit/{$id}/");

    $curtain = $this->newCurtainView($service);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Service'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
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
      ->setBindings($bindings)
      ->setHideServiceColumn(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('SERVICE BINDINGS'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI("binding/edit/?serviceID={$id}"))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit)
          ->setText(pht('Add Binding'))
          ->setIcon('fa-plus'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
