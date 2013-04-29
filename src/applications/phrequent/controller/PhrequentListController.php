<?php

final class PhrequentListController extends PhrequentController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', "current");
  }

  private function getArrToStrList($key) {
    $arr = $this->getRequest()->getArr($key);
    $arr = implode(',', $arr);
    return nonempty($arr, null);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      // Redirect to GET so URIs can be copy/pasted.

      $order = $request->getStr('o');
      $order = nonempty($order, null);

      $ended = $request->getStr('e');
      $ended = nonempty($ended, null);

      $uri = $request->getRequestURI()
        ->alter('users', $this->getArrToStrList('set_users'))
        ->alter('o',     $order)
        ->alter('e',     $ended);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $nav = $this->buildNav($this->view);

    $has_user_filter = array(
      "current" => true,
      "recent" => true,
    );

    $user_phids = $request->getStrList('users', array());
    if (isset($has_user_filter[$this->view])) {
      $user_phids = array($user->getPHID());
    }

    switch ($this->view) {
      case "current":
      case "allcurrent":
        $order_key_default = "s";
        $ended_key_default = "n";
        break;
      case "recent":
      case "allrecent":
        $order_key_default = "s";
        $ended_key_default = "y";
        break;
      default:
        $order_key_default = "s";
        $ended_key_default = "a";
        break;
    }

    switch ($request->getStr('o', $order_key_default)) {
      case 's':
        $order = PhrequentUserTimeQuery::ORDER_STARTED;
        break;
      case 'e':
        $order = PhrequentUserTimeQuery::ORDER_ENDED;
        break;
      case 'd':
        $order = PhrequentUserTimeQuery::ORDER_DURATION;
        break;
      default:
        throw new Exception("Unknown order!");
    }

    switch ($request->getStr('e', $ended_key_default)) {
      case 'a':
        $ended = PhrequentUserTimeQuery::ENDED_ALL;
        break;
      case 'y':
        $ended = PhrequentUserTimeQuery::ENDED_YES;
        break;
      case 'n':
        $ended = PhrequentUserTimeQuery::ENDED_NO;
        break;
      default:
        throw new Exception("Unknown ended!");
    }

    $filter = new AphrontListFilterView();
    $filter->appendChild(
      $this->buildForm($user_phids, $order_key_default, $ended_key_default));

    $query = new PhrequentUserTimeQuery();
    $query->setOrder($order);
    $query->setEnded($ended);
    $query->setUsers($user_phids);

    $pager = new AphrontPagerView();
    $pager->setPageSize(500);
    $pager->setOffset($request->getInt('offset'));
    $pager->setURI($request->getRequestURI(), 'offset');

    $logs = $query->executeWithOffsetPager($pager);

    $title = pht('Time Tracked');

    $table = $this->buildTableView($logs);
    $table->appendChild($pager);

    $nav->appendChild(
      array(
        $filter,
        $table,
        $pager,
      ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI('/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));

  }

  protected function buildForm(array $user_phids, $order_key_default,
                               $ended_key_default) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setNoShading(true)
      ->setAction($this->getApplicationURI("/view/custom/"));

    $user_handles = id(new PhabricatorObjectHandleData($user_phids))
      ->setViewer($user)
      ->loadHandles();
    $tokens = array();
    foreach ($user_phids as $phid) {
      $tokens[$phid] = $user_handles[$phid]->getFullName();
    }
    $form->appendChild(
      id(new AphrontFormTokenizerControl())
        ->setDatasource('/typeahead/common/searchowner/')
        ->setName('set_users')
        ->setLabel(pht('Users'))
        ->setValue($tokens));

    $form->appendChild(
      id(new AphrontFormToggleButtonsControl())
        ->setName('o')
        ->setLabel(pht('Sort Order'))
        ->setBaseURI($request->getRequestURI(), 'o')
        ->setValue($request->getStr('o', $order_key_default))
        ->setButtons(
          array(
            's'   => pht('Started'),
            'e'   => pht('Ended'),
            'd'   => pht('Duration'),
          )));

    $form->appendChild(
      id(new AphrontFormToggleButtonsControl())
        ->setName('e')
        ->setLabel(pht('Ended'))
        ->setBaseURI($request->getRequestURI(), 'e')
        ->setValue($request->getStr('e', $ended_key_default))
        ->setButtons(
          array(
            'y'   => pht('Yes'),
            'n'   => pht('No'),
            'a'   => pht('All'),
          )));

    $form->appendChild(
      id(new AphrontFormSubmitControl())->setValue(pht('Filter Objects')));

    return $form;
  }

  protected function buildTableView(array $usertimes) {
    assert_instances_of($usertimes, 'PhrequentUserTime');

    $user = $this->getRequest()->getUser();

    $phids = array();
    foreach ($usertimes as $usertime) {
      $phids[] = $usertime->getUserPHID();
      $phids[] = $usertime->getObjectPHID();
    }
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($usertimes as $usertime) {

      if ($usertime->getDateEnded() !== null) {
        $time_spent = $usertime->getDateEnded() - $usertime->getDateStarted();
        $time_ended = phabricator_datetime($usertime->getDateEnded(), $user);
      } else {
        $time_spent = time() - $usertime->getDateStarted();
        $time_ended = phutil_tag(
          'em',
          array(),
          pht('Ongoing'));
      }

      $usertime_user = $handles[$usertime->getUserPHID()];
      $usertime_object = null;
      $object = null;
      if ($usertime->getObjectPHID() !== null) {
        $usertime_object = $handles[$usertime->getObjectPHID()];
        $object = phutil_tag(
          'a',
          array(
            'href' => $usertime_object->getURI()
          ),
          $usertime_object->getFullName());
      } else {
        $object = phutil_tag(
          'em',
          array(),
          pht('None'));
      }

      $rows[] = array(
        $object,
        phutil_tag(
          'a',
          array(
            'href' => $usertime_user->getURI()
          ),
          $usertime_user->getFullName()),
        phabricator_datetime($usertime->getDateStarted(), $user),
        $time_ended,
        $time_spent == 0 ? 'none' :
          phabricator_format_relative_time_detailed($time_spent),
        $usertime->getNote()
      );
    }

    $table = new AphrontTableView($rows);
    $table->setDeviceReadyTable(true);
    $table->setHeaders(
      array(
        'Object',
        'User',
        'Started',
        'Ended',
        'Duration',
        'Note'
      ));
    $table->setShortHeaders(
      array(
        'O',
        'U',
        'S',
        'E',
        'D',
        'Note',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        '',
        'wide'
      ));

    return $table;
  }

}
