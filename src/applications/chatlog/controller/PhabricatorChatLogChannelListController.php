<?php

final class PhabricatorChatLogChannelListController
  extends PhabricatorChatLogController {

  public function processRequest() {

    $table = new PhabricatorChatLogEvent();

    $channels = queryfx_all(
      $table->establishConnection('r'),
      'SELECT DISTINCT channel FROM %T',
      $table->getTableName());

    $rows = array();
    foreach ($channels as $channel) {
      $name = $channel['channel'];
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => '/chatlog/channel/'.phutil_escape_uri($name).'/',
          ),
          $name));
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Channel',
      ));
    $table->setColumnClasses(
      array(
        'pri wide',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Channel List',
      ));
  }
}
