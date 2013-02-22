<?php

final class PhabricatorChatLogChannelListController
  extends PhabricatorChatLogController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $channels = id(new PhabricatorChatLogChannelQuery())
                ->setViewer($user)
                ->execute();

    $rows = array();
    foreach ($channels as $channel) {
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' =>
                 '/chatlog/channel/'.$channel->getID().'/',
          ),
          $channel->getChannelName()));
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
