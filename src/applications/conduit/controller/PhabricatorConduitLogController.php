<?php

final class PhabricatorConduitLogController
  extends PhabricatorConduitController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $conn_table = new PhabricatorConduitConnectionLog();
    $call_table = new PhabricatorConduitMethodCallLog();

    $conn_r = $call_table->establishConnection('r');

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);
    $pager->setPageSize(500);

    $query = id(new PhabricatorConduitLogQuery())
      ->setViewer($viewer);

    $methods = $request->getStrList('methods');
    if ($methods) {
      $query->withMethods($methods);
    }

    $calls = $query->executeWithCursorPager($pager);

    $conn_ids = array_filter(mpull($calls, 'getConnectionID'));
    $conns = array();
    if ($conn_ids) {
      $conns = $conn_table->loadAllWhere(
        'id IN (%Ld)',
        $conn_ids);
    }

    $table = $this->renderCallTable($calls, $conns);
    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Call Logs'))
      ->appendChild($table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Call Logs'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $pager,
      ),
      array(
        'title' => pht('Conduit Logs'),
      ));
  }

  private function renderCallTable(array $calls, array $conns) {
    assert_instances_of($calls, 'PhabricatorConduitMethodCallLog');
    assert_instances_of($conns, 'PhabricatorConduitConnectionLog');

    $viewer = $this->getRequest()->getUser();

    $methods = id(new PhabricatorConduitMethodQuery())
      ->setViewer($viewer)
      ->execute();
    $methods = mpull($methods, null, 'getAPIMethodName');

    $rows = array();
    foreach ($calls as $call) {
      $conn = idx($conns, $call->getConnectionID());
      if ($conn) {
        $name = $conn->getUserName();
        $client = ' (via '.$conn->getClient().')';
      } else {
        $name = null;
        $client = null;
      }

      $method = idx($methods, $call->getMethod());
      if ($method) {
        switch ($method->getMethodStatus()) {
          case ConduitAPIMethod::METHOD_STATUS_STABLE:
            $status = null;
            break;
          case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
            $status = pht('Unstable');
            break;
          case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
            $status = pht('Deprecated');
            break;
        }
      } else {
        $status = pht('Unknown');
      }

      $rows[] = array(
        $call->getConnectionID(),
        $name,
        array($call->getMethod(), $client),
        $status,
        $call->getError(),
        number_format($call->getDuration()).' us',
        phabricator_datetime($call->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows));

    $table->setHeaders(
      array(
        pht('Connection'),
        pht('User'),
        pht('Method'),
        pht('Status'),
        pht('Error'),
        pht('Duration'),
        pht('Date'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
        '',
        'n',
        'right',
      ));
    return $table;
  }

}
