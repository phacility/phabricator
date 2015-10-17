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
      ->setHeader($title);

    if ($resource->isReleasing()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Releasing'));
    }

    $actions = $this->buildActionListView($resource);
    $properties = $this->buildPropertyListView($resource, $actions);

    $id = $resource->getID();
    $resource_uri = $this->getApplicationURI("resource/{$id}/");

    $log_query = id(new DrydockLogQuery())
      ->withResourcePHIDs(array($resource->getPHID()));

    $log_box = $this->buildLogBox(
      $log_query,
      $this->getApplicationURI("resource/{$id}/logs/query/all/"));

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
    if ($resource->isReleasing()) {
      $can_release = false;
    }

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
          ->setIconFont('fa-search')
          ->setText(pht('View All')));

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
