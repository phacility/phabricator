<?php

final class PhabricatorPeopleListController
  extends PhabricatorPeopleController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $is_admin = $viewer->getIsAdmin();

    $user = new PhabricatorUser();

    $count = queryfx_one(
      $user->establishConnection('r'),
      'SELECT COUNT(*) N FROM %T',
      $user->getTableName());
    $count = idx($count, 'N', 0);

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page', 0));
    $pager->setCount($count);
    $pager->setURI($request->getRequestURI(), 'page');

    $users = id(new PhabricatorPeopleQuery())
      ->needPrimaryEmail(true)
      ->executeWithOffsetPager($pager);

    $list = new PhabricatorObjectItemListView();

    foreach ($users as $user) {
      $primary_email = $user->loadPrimaryEmail();
      if ($primary_email && $primary_email->getIsVerified()) {
        $email = pht('Verified');
      } else {
        $email = pht('Unverified');
      }

      $user_handle = new PhabricatorObjectHandle();
      $user_handle->setImageURI($user->loadProfileImageURI());

      $item = new PhabricatorObjectItemView();
      $item->setHeader($user->getFullName())
        ->setHref('/people/edit/'.$user->getID().'/')
        ->addAttribute(hsprintf('%s %s',
            phabricator_date($user->getDateCreated(), $viewer),
            phabricator_time($user->getDateCreated(), $viewer)))
        ->addAttribute($email);

      if ($user->getIsDisabled()) {
        $item->addIcon('disable', pht('Disabled'));
      }

      if ($user->getIsAdmin()) {
        $item->addIcon('highlight', pht('Admin'));
      }

      if ($user->getIsSystemAgent()) {
        $item->addIcon('computer', pht('System Agent'));
      }

      $list->addItem($item);
    }

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('People (%d)', number_format($count)));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('User Directory'))
          ->setHref('/people/'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('people');
    $nav->appendChild($header);
    $nav->appendChild($list);
    $nav->appendChild($pager);
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'  => pht('People'),
        'device' => true,
        'dust'   => true,
      ));
  }
}
