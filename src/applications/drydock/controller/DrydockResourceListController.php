<?php

final class DrydockResourceListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('resource');

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI('/drydock/resource/'), 'page');

    $data = id(new DrydockResource())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $phids = mpull($data, 'getOwnerPHID');
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($data as $resource) {
      $rows[] = array(
        $resource->getID(),
        ($resource->getOwnerPHID()
          ? $handles[$resource->getOwnerPHID()]->renderLink()
          : null),
        phutil_escape_html($resource->getType()),
        DrydockResourceStatus::getNameForStatus($resource->getStatus()),
        phutil_escape_html(nonempty($resource->getName(), 'Unnamed')),
        phabricator_datetime($resource->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Owner',
        'Type',
        'Status',
        'Resource',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'pri wide',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Drydock Resources');

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/drydock/resource/allocate/',
          'class' => 'green button',
        ),
        'Allocate Resource'));

    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Resources',
      ));

  }

}
