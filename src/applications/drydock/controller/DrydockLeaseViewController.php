<?php

final class DrydockLeaseViewController extends DrydockController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $lease = id(new DrydockLease())->load($this->id);
    if (!$lease) {
      return new Aphront404Response();
    }

    $lease_uri = $this->getApplicationURI('lease/'.$lease->getID().'/');

    $title = pht('Lease %d', $lease->getID());

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($lease);
    $properties = $this->buildPropertyListView($lease);

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI($lease_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->withLeaseIDs(array($lease->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = $this->buildLogTableView($logs);
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($lease_uri));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $log_table,
      ),
      array(
        'device'  => true,
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockLease $lease) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($lease);

    $id = $lease->getID();

    $can_release = ($lease->getStatus() == DrydockLeaseStatus::STATUS_ACTIVE);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Release Lease'))
        ->setIcon('delete')
        ->setHref($this->getApplicationURI("/lease/{$id}/release/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_release));

    return $view;
  }

  private function buildPropertyListView(DrydockLease $lease) {
    $view = new PhabricatorPropertyListView();

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
