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

    $rows = array();
    foreach ($users as $user) {
      $primary_email = $user->loadPrimaryEmail();
      if ($primary_email && $primary_email->getIsVerified()) {
        $email = pht('Verified');
      } else {
        $email = pht('Unverified');
      }

      $status = array();
      if ($user->getIsDisabled()) {
        $status[] = pht('Disabled');
      }
      if ($user->getIsAdmin()) {
        $status[] = pht('Admin');
      }
      if ($user->getIsSystemAgent()) {
        $status[] = pht('System Agent');
      }
      $status = implode(', ', $status);

      $rows[] = array(
        phabricator_date($user->getDateCreated(), $viewer),
        phabricator_time($user->getDateCreated(), $viewer),
        phutil_tag(
          'a',
          array(
            'href' => '/p/'.$user->getUsername().'/',
          ),
          $user->getUserName()),
        $user->getRealName(),
        $status,
        $email,
        phutil_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/people/edit/'.$user->getID().'/',
          ),
          pht('Administrate User')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Join Date'),
        pht('Time'),
        pht('Username'),
        pht('Real Name'),
        pht('Roles'),
        pht('Email'),
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        'right',
        'pri',
        'wide',
        null,
        null,
        'action',
      ));
    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
        true,
        $is_admin,
        $is_admin,
        $is_admin,
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('People (%d)', number_format($count)));
    $panel->setNoBackground();
    $panel->appendChild($table);
    $panel->appendChild($pager);

    if ($is_admin) {
      if (PhabricatorEnv::getEnvConfig('ldap.auth-enabled')) {
        $panel->addButton(
          phutil_tag(
            'a',
            array(
              'href' => '/people/ldap/',
              'class' => 'button green'
            ),
            pht('Import from LDAP')));
      }
    }
    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('User Directory'))
          ->setHref('/people/'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('people');
    $nav->appendChild($panel);
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('People'),
        'device' => true,
      ));
  }
}
