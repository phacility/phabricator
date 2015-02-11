<?php

final class DrydockLeaseViewController extends DrydockLeaseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$lease) {
      return new Aphront404Response();
    }

    $lease_uri = $this->getApplicationURI('lease/'.$lease->getID().'/');

    $title = pht('Lease %d', $lease->getID());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($lease);
    $properties = $this->buildPropertyListView($lease, $actions);

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI($lease_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->setViewer($viewer)
      ->withLeaseIDs(array($lease->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = id(new DrydockLogListView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->render();
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $lease_uri);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $log_table,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockLease $lease) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($lease);

    $id = $lease->getID();

    $can_release = ($lease->getStatus() == DrydockLeaseStatus::STATUS_ACTIVE);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Release Lease'))
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI("/lease/{$id}/release/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_release));

    return $view;
  }

  private function buildPropertyListView(
    DrydockLease $lease,
    PhabricatorActionListView $actions) {

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    switch ($lease->getStatus()) {
      case DrydockLeaseStatus::STATUS_ACTIVE:
        $status = pht('Active');
        break;
      case DrydockLeaseStatus::STATUS_RELEASED:
        $status = pht('Released');
        break;
      case DrydockLeaseStatus::STATUS_EXPIRED:
        $status = pht('Expired');
        break;
      case DrydockLeaseStatus::STATUS_PENDING:
        $status = pht('Pending');
        break;
      case DrydockLeaseStatus::STATUS_BROKEN:
        $status = pht('Broken');
        break;
      default:
        $status = pht('Unknown');
        break;
    }

    $view->addProperty(
      pht('Status'),
      $status);

    $view->addProperty(
      pht('Resource Type'),
      $lease->getResourceType());

    $view->addProperty(
      pht('Resource'),
      $lease->getResourceID());

    $attributes = $lease->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(pht('Attributes'));
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

}
