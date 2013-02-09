<?php

/**
 * @group conduit
 */
final class PhabricatorConduitLogController
  extends PhabricatorConduitController {

  public function processRequest() {
    $request = $this->getRequest();

    $conn_table = new PhabricatorConduitConnectionLog();
    $call_table = new PhabricatorConduitMethodCallLog();

    $conn_r = $call_table->establishConnection('r');

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $calls = $call_table->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $calls = $pager->sliceResults($calls);
    $pager->setURI(new PhutilURI('/conduit/log/'), 'page');
    $pager->setEnableKeyboardShortcuts(true);

    $min = $pager->getOffset() + 1;
    $max = ($min + count($calls) - 1);

    $conn_ids = array_filter(mpull($calls, 'getConnectionID'));
    $conns = array();
    if ($conn_ids) {
      $conns = $conn_table->loadAllWhere(
        'id IN (%Ld)',
        $conn_ids);
    }

    $table = $this->renderCallTable($calls, $conns);
    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit Method Calls ('.$min.'-'.$max.')');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $this->setShowSideNav(false);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Conduit Logs',
      ));
  }

  private function renderCallTable(array $calls, array $conns) {
    assert_instances_of($calls, 'PhabricatorConduitMethodCallLog');
    assert_instances_of($conns, 'PhabricatorConduitConnectionLog');

    $user = $this->getRequest()->getUser();

    $rows = array();
    foreach ($calls as $call) {
      $conn = idx($conns, $call->getConnectionID());
      if (!$conn) {
        // If there's no connection, use an empty object.
        $conn = new PhabricatorConduitConnectionLog();
      }
      $rows[] = array(
        $call->getConnectionID(),
        $conn->getUserName(),
        $call->getMethod(),
        $call->getError(),
        number_format($call->getDuration()).' us',
        phabricator_datetime($call->getDateCreated(), $user),
      );
    }
    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Connection',
        'User',
        'Method',
        'Error',
        'Duration',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
        'n',
        'right',
      ));
    return $table;
  }

}
