<?php

final class DrydockResourceViewController extends DrydockController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $resource = id(new DrydockResource())->load($this->id);
    if (!$resource) {
      return new Aphront404Response();
    }

    $title = 'Resource '.$resource->getID().' '.$resource->getName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($resource);
    $properties = $this->buildPropertyListView($resource, $actions);

    $resource_uri = 'resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    $leases = id(new DrydockLeaseQuery())
      ->withResourceIDs(array($resource->getID()))
      ->needResources(true)
      ->execute();

    $lease_list = $this->buildLeaseListView($leases);
    $lease_list->setNoDataString(pht('This resource has no leases.'));

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI($resource_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->withResourceIDs(array($resource->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = $this->buildLogTableView($logs);
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Resource %d', $resource->getID())));

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
        'device'  => true,
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
        ->setIcon('delete')
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
