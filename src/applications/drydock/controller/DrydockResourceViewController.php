<?php

final class DrydockResourceViewController extends DrydockResourceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $title = pht('Resource %s %s', $resource->getID(), $resource->getName());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($resource)
      ->setHeader($title);

    $actions = $this->buildActionListView($resource);
    $properties = $this->buildPropertyListView($resource, $actions);

    $resource_uri = 'resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    $pager = new PHUIPagerView();
    $pager->setURI(new PhutilURI($resource_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->setViewer($viewer)
      ->withResourceIDs(array($resource->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = id(new DrydockLogListView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->render();
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Resource %d', $resource->getID()));

    $locks = $this->buildLocksTab($resource->getPHID());
    $commands = $this->buildCommandsTab($resource->getPHID());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties, pht('Properties'))
      ->addPropertyList($locks, pht('Slot Locks'))
      ->addPropertyList($commands, pht('Commands'));

    $lease_box = $this->buildLeaseBox($resource);

    $log_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Resource Logs'))
      ->setTable($log_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $lease_box,
        $log_box,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockResource $resource) {
    $viewer = $this->getViewer();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($resource);

    $can_release = $resource->canRelease();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $resource,
      PhabricatorPolicyCapability::CAN_EDIT);

    $uri = '/resource/'.$resource->getID().'/release/';
    $uri = $this->getApplicationURI($uri);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Release Resource'))
        ->setIcon('fa-times')
        ->setWorkflow(true)
        ->setDisabled(!$can_release || !$can_edit));

    return $view;
  }

  private function buildPropertyListView(
    DrydockResource $resource,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setActionList($actions);

    $status = $resource->getStatus();
    $status = DrydockResourceStatus::getNameForStatus($status);

    $view->addProperty(
      pht('Status'),
      $status);

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
          ->setIconFont('fa-search')
          ->setText(pht('View All Leases')));

    $lease_list = id(new DrydockLeaseListView())
      ->setUser($viewer)
      ->setLeases($leases)
      ->render()
      ->setNoDataString(pht('This resource has no active leases.'));

    return id(new PHUIObjectBoxView())
      ->setHeader($lease_header)
      ->setObjectList($lease_list);
  }

}
