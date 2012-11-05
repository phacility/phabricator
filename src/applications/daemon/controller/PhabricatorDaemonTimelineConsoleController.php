<?php

final class PhabricatorDaemonTimelineConsoleController
  extends PhabricatorDaemonController {

  public function processRequest() {

    $timeline_table = new PhabricatorTimelineEvent('NULL');

    $events = queryfx_all(
      $timeline_table->establishConnection('r'),
      'SELECT id, type FROM %T ORDER BY id DESC LIMIT 100',
      $timeline_table->getTableName());

    $rows = array();
    foreach ($events as $event) {
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/daemon/timeline/'.$event['id'].'/',
          ),
          $event['id']),
        phutil_escape_html($event['type']),
      );
    }

    $event_table = new AphrontTableView($rows);
    $event_table->setHeaders(
      array(
        'ID',
        'Type',
      ));
    $event_table->setColumnClasses(
      array(
        null,
        'wide',
      ));

    $event_panel = new AphrontPanelView();
    $event_panel->setHeader('Timeline Events');
    $event_panel->appendChild($event_table);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('timeline');
    $nav->appendChild($event_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Timeline',
      ));
  }

}
