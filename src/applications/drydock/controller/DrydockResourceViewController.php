<?php

final class DrydockResourceViewController extends DrydockResourceController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $title = pht('Resource %s %s', $resource->getID(), $resource->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($resource);
    $properties = $this->buildPropertyListView($resource, $actions);

    $resource_uri = 'resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withResourceIDs(array($resource->getID()))
      ->execute();

    $lease_list = id(new DrydockLeaseListView())
      ->setUser($viewer)
      ->setLeases($leases)
      ->render();
    $lease_list->setNoDataString(pht('This resource has no leases.'));

    $pager = new AphrontPagerView();
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

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $lease_list,
        $log_table,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockResource $resource) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($resource);

    $can_close = ($resource->getStatus() == DrydockResourceStatus::STATUS_OPEN);
    $uri = '/resource/'.$resource->getID().'/close/';
    $uri = $this->getApplicationURI($uri);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Close Resource'))
        ->setIcon('fa-times')
        ->setWorkflow(true)
        ->setDisabled(!$can_close));

    return $view;
  }

  private function buildPropertyListView(
    DrydockResource $resource,
    PhabricatorActionListView $actions) {

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    $status = $resource->getStatus();
    $status = DrydockResourceStatus::getNameForStatus($status);

    $view->addProperty(
      pht('Status'),
      $status);

    $view->addProperty(
      pht('Resource Type'),
      $resource->getType());

    // TODO: Load handle.
    $view->addProperty(
      pht('Blueprint'),
      $resource->getBlueprintPHID());

    $attributes = $resource->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(pht('Attributes'));
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

}
