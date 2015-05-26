<?php

final class PhabricatorChatLogChannelListController
  extends PhabricatorChatLogController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $channels = id(new PhabricatorChatLogChannelQuery())
      ->setViewer($user)
      ->execute();

    $list = new PHUIObjectItemListView();
    foreach ($channels as $channel) {
        $item = id(new PHUIObjectItemView())
          ->setHeader($channel->getChannelName())
          ->setHref('/chatlog/channel/'.$channel->getID().'/')
          ->addAttribute($channel->getServiceName())
          ->addAttribute($channel->getServiceType());
        $list->addItem($item);
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Channel List'), $this->getApplicationURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $list,
      ),
      array(
        'title' => pht('Channel List'),
      ));
  }
}
