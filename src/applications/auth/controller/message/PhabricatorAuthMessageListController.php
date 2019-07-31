<?php

final class PhabricatorAuthMessageListController
  extends PhabricatorAuthProviderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $can_manage = $this->hasApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $types = PhabricatorAuthMessageType::getAllMessageTypes();

    $messages = id(new PhabricatorAuthMessageQuery())
      ->setViewer($viewer)
      ->execute();
    $messages = mpull($messages, null, 'getMessageKey');

    $list = new PHUIObjectItemListView();
    foreach ($types as $type) {
      $message = idx($messages, $type->getMessageTypeKey());

      if ($message) {
        $href = $message->getURI();
        $name = $message->getMessageTypeDisplayName();
      } else {
        $href = urisprintf(
          '/auth/message/%s/',
          $type->getMessageTypeKey());
        $name = $type->getDisplayName();
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($name)
        ->setHref($href)
        ->addAttribute($type->getShortDescription());

      if ($message) {
        $item->addIcon('fa-circle', pht('Customized'));
      } else {
        $item->addIcon('fa-circle-o grey', pht('Default'));
      }

      $list->addItem($item);
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Messages'))
      ->setBorder(true);

    $list->setFlush(true);
    $list = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Auth Messages'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    $title = pht('Auth Messages');
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-commenting-o');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $list,
        ));

    $nav = $this->newNavigation()
      ->setCrumbs($crumbs)
      ->appendChild($view);

    $nav->selectFilter('message');

    return $this->newPage()
      ->setTitle($title)
      ->appendChild($nav);
  }

}
