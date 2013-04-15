<?php

final class PhabricatorChatLogChannelListController
  extends PhabricatorChatLogController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $channels = id(new PhabricatorChatLogChannelQuery())
                ->setViewer($user)
                ->execute();

    $list = new PhabricatorObjectItemListView();
    foreach ($channels as $channel) {
        $item = id(new PhabricatorObjectItemView())
          ->setHeader($channel->getChannelName())
          ->setHref('/chatlog/channel/'.$channel->getID().'/')
          ->addAttribute($channel->getServiceName())
          ->addAttribute($channel->getServiceType());
        $list->addItem($item);
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Channel List'))
          ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $list,
      ),
      array(
        'title' => pht('Channel List'),
        'device' => true,
        'dust' => true,
      ));
  }
}
