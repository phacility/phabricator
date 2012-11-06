<?php

final class DrydockLeaseViewController extends DrydockController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('lease');

    $lease = id(new DrydockLease())->load($this->id);
    if (!$lease) {
      return new Aphront404Response();
    }

    $title = 'Lease '.$lease->getID();

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($lease);
    $properties = $this->buildPropertyListView($lease);

    $pager = new AphrontPagerView();
    $pager->setURI(
      new PhutilURI($this->getApplicationURI('lease/'.$lease->getID().'/')),
      'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->withLeaseIDs(array($lease->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = $this->buildLogTableView($logs);
    $log_table->appendChild($pager);

    $nav->appendChild(
      array(
        $header,
        $actions,
        $properties,
        $log_table,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'device'  => true,
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockLease $lease) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($lease);

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
      phutil_escape_html($lease->getResourceType()));

    $view->addProperty(
      pht('Resource'),
      phutil_escape_html($lease->getResourceID()));

    return $view;
  }

}
