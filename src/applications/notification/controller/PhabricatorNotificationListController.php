<?php

final class PhabricatorNotificationListController
  extends PhabricatorNotificationController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/notification/'));
    $nav->addFilter('all', pht('All Notifications'));
    $nav->addFilter('unread', pht('Unread Notifications'));
    $filter = $nav->selectFilter($this->filter, 'all');

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $query = new PhabricatorNotificationQuery();
    $query->setViewer($user);
    $query->setUserPHID($user->getPHID());

    switch ($filter) {
      case 'unread':
        $query->withUnread(true);
        $header = pht('Unread Notifications');
        $no_data = pht('You have no unread notifications.');
        break;
      default:
        $header = pht('Notifications');
        $no_data = pht('You have no notifications.');
        break;
    }

    $notifications = $query->executeWithOffsetPager($pager);

    if ($notifications) {
      $builder = new PhabricatorNotificationBuilder($notifications);
      $view = $builder->buildView()->render();
    } else {
      $view = phutil_tag_div(
        'phabricator-notification no-notifications',
        $no_data);
    }

    $view = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_MEDIUM)
      ->addClass('phabricator-notification-list')
      ->appendChild($view);

    $image = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_ICONS)
        ->setSpriteIcon('preview');
    $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::SIMPLE)
        ->setHref('/notification/clear/')
        ->addSigil('workflow')
        ->setIcon($image)
        ->setText(pht('Mark All Read'));

    $notif_header = id(new PHUIHeaderView())
      ->setHeader($header)
      ->addActionLink($button);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($notif_header)
      ->appendChild($view);

    $nav->appendChild($box);
    $nav->appendChild($pager);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Notifications'),
        'device' => true,
      ));
  }

}
