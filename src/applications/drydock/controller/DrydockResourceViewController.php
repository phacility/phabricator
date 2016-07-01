<?php

final class DrydockResourceViewController extends DrydockResourceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needUnconsumedCommands(true)
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $title = pht(
      'Resource %s %s',
      $resource->getID(),
      $resource->getResourceName());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($resource)
      ->setHeader($title)
      ->setHeaderIcon('fa-map');

    if ($resource->isReleasing()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Releasing'));
    }

    $curtain = $this->buildCurtain($resource);
    $properties = $this->buildPropertyListView($resource);

    $id = $resource->getID();
    $resource_uri = $this->getApplicationURI("resource/{$id}/");

    $log_query = id(new DrydockLogQuery())
      ->withResourcePHIDs(array($resource->getPHID()));

    $log_box = $this->buildLogBox(
      $log_query,
      $this->getApplicationURI("resource/{$id}/logs/query/all/"));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Resource %d', $resource->getID()));
    $crumbs->setBorder(true);

    $locks = $this->buildLocksTab($resource->getPHID());
    $commands = $this->buildCommandsTab($resource->getPHID());

    $tab_group = id(new PHUITabGroupView())
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('Properties'))
          ->setKey('properties')
          ->appendChild($properties))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('Slot Locks'))
          ->setKey('locks')
          ->appendChild($locks))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('Commands'))
          ->setKey('commands')
          ->appendChild($commands));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Properties'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addTabGroup($tab_group);

    $lease_box = $this->buildLeaseBox($resource);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $object_box,
        $lease_box,
        $log_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));

  }

  private function buildCurtain(DrydockResource $resource) {
    $viewer = $this->getViewer();

    $curtain = $this->newCurtainView($resource);

    $can_release = $resource->canRelease();
    if ($resource->isReleasing()) {
      $can_release = false;
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $resource,
      PhabricatorPolicyCapability::CAN_EDIT);

    $uri = '/resource/'.$resource->getID().'/release/';
    $uri = $this->getApplicationURI($uri);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Release Resource'))
        ->setIcon('fa-times')
        ->setWorkflow(true)
        ->setDisabled(!$can_release || !$can_edit));

    return $curtain;
  }

  private function buildPropertyListView(
    DrydockResource $resource) {
    $viewer = $this->getViewer();

    $view = new PHUIPropertyListView();
    $status = $resource->getStatus();
    $status = DrydockResourceStatus::getNameForStatus($status);

    $view->addProperty(
      pht('Status'),
      $status);

    $until = $resource->getUntil();
    if ($until) {
      $until_display = phabricator_datetime($until, $viewer);
    } else {
      $until_display = phutil_tag('em', array(), pht('Never'));
    }
    $view->addProperty(pht('Expires'), $until_display);

    $view->addProperty(
      pht('Resource Type'),
      $resource->getType());

    $view->addProperty(
      pht('Blueprint'),
      $viewer->renderHandle($resource->getBlueprintPHID()));

    $attributes = $resource->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(
        pht('Attributes'), 'fa-list-ul');
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

  private function buildLeaseBox(DrydockResource $resource) {
    $viewer = $this->getViewer();

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withResourcePHIDs(array($resource->getPHID()))
      ->withStatuses(
        array(
          DrydockLeaseStatus::STATUS_PENDING,
          DrydockLeaseStatus::STATUS_ACQUIRED,
          DrydockLeaseStatus::STATUS_ACTIVE,
        ))
      ->setLimit(100)
      ->execute();

    $id = $resource->getID();
    $leases_uri = "resource/{$id}/leases/query/all/";
    $leases_uri = $this->getApplicationURI($leases_uri);

    $lease_header = id(new PHUIHeaderView())
      ->setHeader(pht('Active Leases'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($leases_uri)
          ->setIcon('fa-search')
          ->setText(pht('View All')));

    $lease_list = id(new DrydockLeaseListView())
      ->setUser($viewer)
      ->setLeases($leases)
      ->render()
      ->setNoDataString(pht('This resource has no active leases.'));

    return id(new PHUIObjectBoxView())
      ->setHeader($lease_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($lease_list);
  }

}
