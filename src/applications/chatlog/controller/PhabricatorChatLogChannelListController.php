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
          $channel->getChannelName()),
          $channel->getServiceName(),
          $channel->getServiceType());
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Channel',
        'Service Name',
        'Service Type',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
      ));

    $title = pht('Channel List.');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $panel = id(new AphrontPanelView())
            ->setNoBackground(true);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Channel List'))
          ->setHref($this->getApplicationURI()));

    $panel->appendChild(
      array(
        $crumbs,
        $header,
        $table
      ));


    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Channel List',
      ));
  }
}
