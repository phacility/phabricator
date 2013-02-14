<?php

/**
 * @group conduit
 */
final class PhabricatorConduitListController
  extends PhabricatorConduitController {

  public function processRequest() {
    $method_groups = $this->getMethodFilters();
    $rows = array();
    foreach ($method_groups as $group => $methods) {
      foreach ($methods as $info) {
        switch ($info['status']) {
          case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
            $status = 'Deprecated';
            break;
          case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
            $status = 'Unstable';
            break;
          default:
            $status = null;
            break;
        }

        $rows[] = array(
          $group,
          phutil_tag(
            'a',
            array(
              'href' => '/conduit/method/'.$info['full_name'],
            ),
            $info['full_name']),
          $info['description'],
          $status,
        );
        $group = null;
      }
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(array(
      'Group',
      'Name',
      'Description',
      'Status',
    ));
    $table->setColumnClasses(array(
      'pri',
      'pri',
      'wide',
      null,
    ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit Methods');
    $panel->appendChild($table);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    $utils = new AphrontPanelView();
    $utils->setHeader('Utilities');
    $utils->appendChild(hsprintf(
      '<ul>'.
      '<li><a href="/conduit/log/">Log</a> - Conduit Method Calls</li>'.
      '<li><a href="/conduit/token/">Token</a> - Certificate Install</li>'.
      '</ul>'));
    $utils->setWidth(AphrontPanelView::WIDTH_FULL);

    $this->setShowSideNav(false);

    return $this->buildStandardPageResponse(
      array(
        $panel,
        $utils,
      ),
      array(
        'title' => 'Conduit Console',
      ));
  }

}
