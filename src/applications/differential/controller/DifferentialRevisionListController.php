<?php

final class DifferentialRevisionListController extends DifferentialController {

  private $filter;
  private $username;

  public function shouldRequireLogin() {
    return !$this->allowsAnonymousAccess();
  }

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
    $this->username = idx($data, 'username');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $params = array_filter(
      array(
        'status' => $request->getStr('status'),
        'order' => $request->getStr('order'),
      ));
    $params['participants'] = $request->getArr('participants');

    $filters = $this->getFilters();
    $this->filter = $this->selectFilter($filters,
      $this->filter, !$user->isLoggedIn());

    // Redirect from search to canonical URL.
    $phid_arr = $request->getArr('view_users');
    if ($phid_arr) {
      $view_users = id(new PhabricatorUser())
        ->loadAllWhere('phid IN (%Ls)', $phid_arr);

      if (count($view_users) == 1) {
        // This is a single user, so generate a pretty URI.
        $uri = new PhutilURI(
          '/differential/filter/'.$this->filter.'/'.
          phutil_escape_uri(reset($view_users)->getUserName()).'/');
        $uri->setQueryParams($params);

        return id(new AphrontRedirectResponse())->setURI($uri);
      }

    }

    $uri = new PhutilURI('/differential/filter/'.$this->filter.'/');
    $uri->setQueryParams($params);

    $username = '';
    if ($this->username) {
      $view_user = id(new PhabricatorUser())
        ->loadOneWhere('userName = %s', $this->username);
      if (!$view_user) {
        return new Aphront404Response();
      }
      $username = phutil_escape_uri($this->username).'/';
      $uri->setPath('/differential/filter/'.$this->filter.'/'.$username);
      $params['view_users'] = array($view_user->getPHID());
    } else {
      $phids = $request->getArr('view_users');
      if ($phids) {
        $params['view_users'] = $phids;
        $uri->setQueryParams($params);
      }
    }

    // Fill in the defaults we'll actually use for calculations if any
    // parameters are missing.
    $params += array(
      'view_users' => array($user->getPHID()),
      'status' => 'all',
      'order' => 'modified',
    );

    $side_nav = $this->buildSideNav($this->filter, false, $username);
    $side_nav->selectFilter($this->filter.'/'.$username, null);

    $panels = array();
    $handles = array();
    $controls = $this->getFilterControls($this->filter);
    if ($this->getFilterRequiresUser($this->filter) && !$params['view_users']) {
      // In the anonymous case, we still want to let you see some user's
      // list, but we don't have a default PHID to provide (normally, we use
      // the viewing user's). Show a warning instead.
      $warning = new AphrontErrorView();
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->setTitle(pht('User Required'));
      $warning->appendChild(
        pht('This filter requires that a user be specified above.'));
      $panels[] = $warning;
    } else {
      $query = $this->buildQuery($this->filter, $params);

      $pager = null;
      if ($this->getFilterAllowsPaging($this->filter)) {
        $pager = new AphrontPagerView();
        $pager->setOffset($request->getInt('page'));
        $pager->setPageSize(1000);
        $pager->setURI($uri, 'page');

        $query->setOffset($pager->getOffset());
        $query->setLimit($pager->getPageSize() + 1);
      }

      foreach ($controls as $control) {
        $this->applyControlToQuery($control, $query, $params);
      }

      $revisions = $query->execute();

      if ($pager) {
        $revisions = $pager->sliceResults($revisions);
      }

      $views = $this->buildViews(
        $this->filter,
        $params['view_users'],
        $revisions);

      $view_objects = array();
      foreach ($views as $view) {
        if (empty($view['special'])) {
          $view_objects[] = $view['view'];
        }
      }
      $phids = mpull($view_objects, 'getRequiredHandlePHIDs');
      $phids[] = $params['view_users'];
      $phids = array_mergev($phids);
      $handles = $this->loadViewerHandles($phids);

      foreach ($views as $view) {
        $view['view']->setHandles($handles);
        $panel = new AphrontPanelView();
        $panel->setHeader($view['title']);
        $panel->appendChild($view['view']);
        if ($pager) {
          $panel->appendChild($pager);
        }
        $panel->setNoBackground();
        $panels[] = $panel;
      }
    }

    $filter_form = id(new AphrontFormView())
      ->setMethod('GET')
      ->setNoShading(true)
      ->setAction('/differential/filter/'.$this->filter.'/')
      ->setUser($user);
    foreach ($controls as $control) {
      $control_view = $this->renderControl($control, $handles, $uri, $params);
      $filter_form->appendChild($control_view);
    }
    $filter_form
      ->addHiddenInput('status', $params['status'])
      ->addHiddenInput('order', $params['order'])
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Filter Revisions')));

    $filter_view = new AphrontListFilterView();
    $filter_view->appendChild($filter_form);

    $side_nav->appendChild($filter_view);

    foreach ($panels as $panel) {
      $side_nav->appendChild($panel);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $name = $side_nav
      ->getMenu()
      ->getItem($side_nav->getSelectedFilter())
      ->getName();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($name)
        ->setHref($request->getRequestURI()));
    $side_nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $side_nav,
      array(
        'title' => pht('Differential Home'),
        'dust' => true,
      ));
  }

  private function getFilterRequiresUser($filter) {
    static $requires = array(
      'active'      => true,
      'revisions'   => true,
      'reviews'     => true,
      'subscribed'  => true,
      'drafts'      => true,
      'all'         => false,
    );
    if (!isset($requires[$filter])) {
      throw new Exception("Unknown filter '{$filter}'!");
    }
    return $requires[$filter];
  }

  private function getFilterAllowsPaging($filter) {
    static $allows = array(
      'active'      => false,
      'revisions'   => true,
      'reviews'     => true,
      'subscribed'  => true,
      'drafts'      => true,
      'all'         => true,
    );
    if (!isset($allows[$filter])) {
      throw new Exception("Unknown filter '{$filter}'!");
    }
    return $allows[$filter];
  }

  private function getFilterControls($filter) {
    static $controls = array(
      'active'      => array('phid'),
      'revisions'   => array('phid', 'participants', 'status', 'order'),
      'reviews'     => array('phid', 'participants', 'status', 'order'),
      'subscribed'  => array('subscriber', 'status', 'order'),
      'drafts'      => array('phid', 'status', 'order'),
      'all'         => array('status', 'order'),
    );
    if (!isset($controls[$filter])) {
      throw new Exception("Unknown filter '{$filter}'!");
    }
    return $controls[$filter];
  }

  private function buildQuery($filter, array $params) {
    $user_phids = $params['view_users'];
    $query = new DifferentialRevisionQuery();

    $query->needRelationships(true);

    switch ($filter) {
      case 'active':
        $query->withResponsibleUsers($user_phids);
        $query->withStatus(DifferentialRevisionQuery::STATUS_OPEN);
        $query->setLimit(null);
        break;
      case 'revisions':
        $query->withAuthors($user_phids);
        $query->withReviewers($params['participants']);
        break;
      case 'reviews':
        $query->withReviewers($user_phids);
        $query->withAuthors($params['participants']);
        break;
      case 'subscribed':
        $query->withSubscribers($user_phids);
        break;
      case 'drafts':
        $query->withDraftRepliesByAuthors($user_phids);
        break;
      case 'all':
        break;
      default:
        throw new Exception("Unknown filter '{$filter}'!");
    }
    return $query;
  }

  private function renderControl(
    $control,
    array $handles,
    PhutilURI $uri,
    array $params) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    switch ($control) {
      case 'subscriber':
      case 'phid':
        $value = mpull(
          array_select_keys($handles, $params['view_users']),
          'getFullName');

        if ($control == 'subscriber') {
          $source = '/typeahead/common/allmailable/';
          $label = pht('View Subscribers');
        } else {
          $source = '/typeahead/common/accounts/';
          switch ($this->filter) {
            case 'revisions':
              $label = pht('Authors');
              break;
            case 'reviews':
              $label = pht('Reviewers');
              break;
            default:
              $label = pht('View Users');
              break;
          }
        }

        return id(new AphrontFormTokenizerControl())
          ->setDatasource($source)
          ->setLabel($label)
          ->setName('view_users')
          ->setValue($value);

      case 'participants':
        switch ($this->filter) {
          case 'revisions':
            $label = pht('Reviewers');
            break;
          case 'reviews':
            $label = pht('Authors');
            break;
        }
        $value = mpull(
          array_select_keys($handles, $params['participants']),
          'getFullName');
        return id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setLabel($label)
          ->setName('participants')
          ->setValue($value);

      case 'status':
        return id(new AphrontFormToggleButtonsControl())
          ->setLabel(pht('Status'))
          ->setValue($params['status'])
          ->setBaseURI($uri, 'status')
          ->setButtons(
            array(
              'all'       => pht('All'),
              'open'      => pht('Open'),
              'closed'    => pht('Closed'),
              'abandoned' => pht('Abandoned'),
            ));

      case 'order':
        return id(new AphrontFormToggleButtonsControl())
          ->setLabel(pht('Order'))
          ->setValue($params['order'])
          ->setBaseURI($uri, 'order')
          ->setButtons(
            array(
              'modified'  => pht('Updated'),
              'created'   => pht('Created'),
            ));

      default:
        throw new Exception("Unknown control '{$control}'!");
    }
  }

  private function applyControlToQuery($control, $query, array $params) {
    switch ($control) {
      case 'phid':
      case 'subscriber':
      case 'participants':
        // Already applied by query construction.
        break;
      case 'status':
        if ($params['status'] == 'open') {
          $query->withStatus(DifferentialRevisionQuery::STATUS_OPEN);
        } else if ($params['status'] == 'closed') {
          $query->withStatus(DifferentialRevisionQuery::STATUS_CLOSED);
        } else if ($params['status'] == 'abandoned') {
          $query->withStatus(DifferentialRevisionQuery::STATUS_ABANDONED);
        }
        break;
      case 'order':
        if ($params['order'] == 'created') {
          $query->setOrder(DifferentialRevisionQuery::ORDER_CREATED);
        }
        break;
      default:
        throw new Exception("Unknown control '{$control}'!");
    }
  }

  private function buildViews($filter, array $user_phids, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $user = $this->getRequest()->getUser();

    $template = id(new DifferentialRevisionListView())
      ->setUser($user)
      ->setFields(DifferentialRevisionListView::getDefaultFields($user));

    $views = array();
    switch ($filter) {
      case 'active':
        list($blocking, $active, $waiting) =
          DifferentialRevisionQuery::splitResponsible(
            $revisions,
            $user_phids);

        $view = id(clone $template)
          ->setHighlightAge(true)
          ->setRevisions($blocking)
          ->loadAssets();
        $views[] = array(
          'title' => pht('Blocking Others'),
          'view'  => $view,
        );

        $view = id(clone $template)
          ->setHighlightAge(true)
          ->setRevisions($active)
          ->loadAssets();
        $views[] = array(
          'title' => pht('Action Required'),
          'view'  => $view,
        );

        $view = id(clone $template)
          ->setRevisions($waiting)
          ->loadAssets();
        $views[] = array(
          'title' => pht('Waiting On Others'),
          'view'  => $view,
        );
        break;
      case 'revisions':
      case 'reviews':
      case 'subscribed':
      case 'drafts':
      case 'all':
        $titles = array(
          'revisions'   => pht('Revisions by Author'),
          'reviews'     => pht('Revisions by Reviewer'),
          'subscribed'  => pht('Revisions by Subscriber'),
          'all'         => pht('Revisions'),
        );
        $view = id(clone $template)
          ->setRevisions($revisions)
          ->loadAssets();
        $views[] = array(
          'title' => idx($titles, $filter),
          'view'  => $view,
        );
        break;
      default:
        throw new Exception("Unknown filter '{$filter}'!");
    }

    return $views;
  }

}
