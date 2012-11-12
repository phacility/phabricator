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
        $email = 'Verified';
      } else {
        $email = 'Unverified';
      }

      $status = array();
      if ($user->getIsDisabled()) {
        $status[] = 'Disabled';
      }
      if ($user->getIsAdmin()) {
        $status[] = 'Admin';
      }
      if ($user->getIsSystemAgent()) {
        $status[] = 'System Agent';
      }
      $status = implode(', ', $status);

      $rows[] = array(
        phabricator_date($user->getDateCreated(), $viewer),
        phabricator_time($user->getDateCreated(), $viewer),
        phutil_render_tag(
          'a',
          array(
            'href' => '/p/'.$user->getUsername().'/',
          ),
          phutil_escape_html($user->getUserName())),
        phutil_escape_html($user->getRealName()),
        $status,
        $email,
        phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/people/edit/'.$user->getID().'/',
          ),
          'Administrate User'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Join Date',
        'Time',
        'Username',
        'Real Name',
        'Roles',
        'Email',
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
    $panel->setHeader('People ('.number_format($count).')');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    if ($is_admin) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => '/people/edit/',
            'class' => 'button green',
          ),
          'Create New Account'));
      if (PhabricatorEnv::getEnvConfig('ldap.auth-enabled')) {
        $panel->addButton(
          phutil_render_tag(
            'a',
            array(
              'href' => '/people/ldap/',
              'class' => 'button green'
            ),
            'Import from LDAP'));
      }
    }

    $nav = $this->buildSideNavView();
    $nav->selectFilter('people');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'People',
      ));
  }
}
