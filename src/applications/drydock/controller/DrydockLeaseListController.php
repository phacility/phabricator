<?php

final class DrydockLeaseListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('lease');

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI('/drydock/lease/'), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $data = id(new DrydockLease())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $resource_ids = mpull($data, 'getResourceID');
    $resources = array();
    if ($resource_ids) {
      $resources = id(new DrydockResource())->loadAllWhere(
        'id IN (%Ld)',
        $resource_ids);
    }

    $rows = array();
    foreach ($data as $lease) {
      $resource = idx($resources, $lease->getResourceID());

      $lease_uri = '/lease/'.$lease->getID().'/';
      $lease_uri = $this->getApplicationURI($lease_uri);

      $resource_uri = '/resource/'.$lease->getResourceID().'/';
      $resource_uri = $this->getApplicationURI($resource_uri);

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $lease_uri,
          ),
          $lease->getID()),
        phutil_render_tag(
          'a',
          array(
            'href' => $resource_uri,
          ),
          $lease->getResourceID()),
        DrydockLeaseStatus::getNameForStatus($lease->getStatus()),
        phutil_escape_html($lease->getResourceType()),
        ($resource
          ? phutil_escape_html($resource->getName())
          : null),
        phabricator_datetime($lease->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Resource ID',
        'Status',
        'Resource Type',
        'Resource',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'wide pri',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Drydock Leases');

    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);
    return $this->buildStandardPageResponse(
      $nav,
      array(
        'device'  => true,
        'title'   => 'Leases',
      ));

  }

}
