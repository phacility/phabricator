<?php

abstract class DrydockController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Drydock');
    $page->setBaseURI('/drydock/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x82");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  final protected function buildSideNav($selected) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/drydock/'));
    $nav->addFilter('resource', 'Resources');
    $nav->addFilter('lease',    'Leases');
    $nav->addSpacer();
    $nav->addFilter('log',      'Logs');

    $nav->selectFilter($selected, 'resource');

    return $nav;
  }

  protected function buildLogTableView(array $logs) {
    assert_instances_of($logs, 'DrydockLog');

    $user = $this->getRequest()->getUser();

    // TODO: It's probably a stretch to claim this works on mobile.

    $rows = array();
    foreach ($logs as $log) {
      $resource_uri = '/resource/'.$log->getResourceID().'/';
      $resource_uri = $this->getApplicationURI($resource_uri);

      $lease_uri = '/lease/'.$log->getLeaseID().'/';
      $lease_uri = $this->getApplicationURI($lease_uri);

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $resource_uri,
          ),
          phutil_escape_html($log->getResourceID())),
        phutil_render_tag(
          'a',
          array(
            'href' => $lease_uri,
          ),
          phutil_escape_html($log->getLeaseID())),
        phutil_escape_html($log->getMessage()),
        phabricator_datetime($log->getEpoch(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Resource',
        'Lease',
        'Message',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Logs');
    $panel->appendChild($table);

    return $panel;
  }

  protected function buildLeaseListView(array $leases) {
    assert_instances_of($leases, 'DrydockLease');

    $user = $this->getRequest()->getUser();
    $view = new PhabricatorObjectItemListView();

    foreach ($leases as $lease) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($lease->getLeaseName())
        ->setHref($this->getApplicationURI('/lease/'.$lease->getID().'/'));

      if ($lease->hasAttachedResource()) {
        $resource = $lease->getResource();

        $resource_href = '/resource/'.$resource->getID().'/';
        $resource_href = $this->getApplicationURI($resource_href);

        $resource_name = $resource->getName();

        $item->addAttribute(
          phutil_render_tag(
            'a',
            array(
              'href' => $resource_href,
            ),
            phutil_escape_html($resource_name)));
      }

      $status = DrydockLeaseStatus::getNameForStatus($lease->getStatus());
      $item->addAttribute(phutil_escape_html($status));

      $date_created = phabricator_date($lease->getDateCreated(), $user);
      $item->addAttribute(pht('Created on %s', $date_created));

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
    $view = new PhabricatorObjectItemListView();

    foreach ($resources as $resource) {
      $name = pht('Resource %d', $resource->getID()).': '.$resource->getName();

      $item = id(new PhabricatorObjectItemView())
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
