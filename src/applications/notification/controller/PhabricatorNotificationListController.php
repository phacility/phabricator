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
    $nav->addFilter('all', 'All Notifications');
    $nav->addFilter('unread', 'Unread Notifications');
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
      $view = hsprintf(
        '<div class="phabricator-notification no-notifications">%s</div>',
        $no_data);
    }

    $view = hsprintf(
      '<div class="phabricator-notification-list">%s</div>',
      $view);

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->addButton(
      javelin_tag(
        'a',
        array(
          'href'  => '/notification/clear/',
          'class' => 'button',
          'sigil' => 'workflow',
        ),
        'Mark All Read'));
    $panel->appendChild($view);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Notifications',
      ));
  }

}
