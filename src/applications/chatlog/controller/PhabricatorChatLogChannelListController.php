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
        pht('Channel'),
        pht('Service Name'),
        pht('Service Type'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
      ));

    $title = pht('Channel List');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $panel = id(new AphrontPanelView())
            ->appendChild($table)
            ->setNoBackground(true);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Channel List'))
          ->setHref($this->getApplicationURI()));

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $header,
        $panel,
      ),
      array(
        'title' => pht('Channel List'),
      ));
  }
}
