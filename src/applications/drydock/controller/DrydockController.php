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
      $rows[] = array(
        $log->getResourceID(),
        $log->getLeaseID(),
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

}
