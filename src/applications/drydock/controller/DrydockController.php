<?php

abstract class DrydockController extends PhabricatorController {

  abstract function buildSideNavView();

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function buildLogTableView(array $logs) {
    assert_instances_of($logs, 'DrydockLog');

    $user = $this->getRequest()->getUser();

    $rows = array();
    foreach ($logs as $log) {
      $resource_uri = '/resource/'.$log->getResourceID().'/';
      $resource_uri = $this->getApplicationURI($resource_uri);

      $lease_uri = '/lease/'.$log->getLeaseID().'/';
      $lease_uri = $this->getApplicationURI($lease_uri);

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $resource_uri,
          ),
          $log->getResourceID()),
        phutil_tag(
          'a',
          array(
            'href' => $lease_uri,
          ),
          $log->getLeaseID()),
        $log->getMessage(),
        phabricator_date($log->getEpoch(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setDeviceReadyTable(true);
    $table->setHeaders(
      array(
        'Resource',
        'Lease',
        'Message',
        'Date',
      ));
    $table->setShortHeaders(
      array(
        'R',
        'L',
        'Message',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    return $table;
  }

  protected function buildLeaseListView(array $leases) {
    assert_instances_of($leases, 'DrydockLease');

    $viewer = $this->getRequest()->getUser();
    $view = new PHUIObjectItemListView();

    foreach ($leases as $lease) {
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($lease->getLeaseName())
        ->setHref($this->getApplicationURI('/lease/'.$lease->getID().'/'));

      if ($lease->hasAttachedResource()) {
        $resource = $lease->getResource();

        $resource_href = '/resource/'.$resource->getID().'/';
        $resource_href = $this->getApplicationURI($resource_href);

        $resource_name = $resource->getName();

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $resource_href,
            ),
            $resource_name));
      }

      $status = DrydockLeaseStatus::getNameForStatus($lease->getStatus());
      $item->addAttribute($status);
      $item->setEpoch($lease->getDateCreated());

      if ($lease->isActive()) {
        $item->setBarColor('green');
      } else {
        $item->setBarColor('red');
      }

      $view->addItem($item);
    }

    return $view;
  }

  protected function buildResourceListView(array $resources) {
    assert_instances_of($resources, 'DrydockResource');

    $user = $this->getRequest()->getUser();
    $view = new PHUIObjectItemListView();

    foreach ($resources as $resource) {
      $name = pht('Resource %d', $resource->getID()).': '.$resource->getName();

      $item = id(new PHUIObjectItemView())
        ->setHref($this->getApplicationURI('/resource/'.$resource->getID().'/'))
        ->setHeader($name);

      $status = DrydockResourceStatus::getNameForStatus($resource->getStatus());
      $item->addAttribute($status);

      switch ($resource->getStatus()) {
        case DrydockResourceStatus::STATUS_PENDING:
          $item->setBarColor('yellow');
          break;
        case DrydockResourceStatus::STATUS_OPEN:
          $item->setBarColor('green');
          break;
        case DrydockResourceStatus::STATUS_DESTROYED:
          $item->setBarColor('black');
          break;
        default:
          $item->setBarColor('red');
          break;
      }

      $view->addItem($item);
    }

    return $view;
  }


}
