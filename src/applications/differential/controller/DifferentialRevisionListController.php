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
    $viewer_is_anonymous = !$user->isLoggedIn();

    $params = array_filter(
      array(
        'status' => $request->getStr('status'),
        'order' => $request->getStr('order'),
      ));

    $default_filter = ($viewer_is_anonymous ? 'all' : 'active');
    $filters = $this->getFilters();
    $this->filter = $this->selectFilter(
      $filters,
      $this->filter,
      $default_filter);

    // Redirect from search to canonical URL.
    $phid_arr = $request->getArr('view_user');
    if ($phid_arr) {
      $view_user = id(new PhabricatorUser())
        ->loadOneWhere('phid = %s', head($phid_arr));

      $base_uri = '/differential/filter/'.$this->filter.'/';
      if ($view_user) {
        // This is a user, so generate a pretty URI.
        $uri = $base_uri.phutil_escape_uri($view_user->getUserName()).'/';
      } else {
        // We're assuming this is a mailing list, generate an ugly URI.
        $uri = $base_uri;
        $params['phid'] = head($phid_arr);
      }

      $uri = new PhutilURI($uri);
      $uri->setQueryParams($params);

      return id(new AphrontRedirectResponse())->setURI($uri);
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
      $params['phid'] = $view_user->getPHID();
    } else {
      $phid = $request->getStr('phid');
      if (strlen($phid)) {
        $params['phid'] = $phid;
      }
    }

    // Fill in the defaults we'll actually use for calculations if any
    // parameters are missing.
    $params += array(
      'phid' => $user->getPHID(),
      'status' => 'all',
      'order' => 'modified',
    );

    $side_nav = new AphrontSideNavView();
    foreach ($filters as $filter) {
      list($filter_name, $display_name) = $filter;
      if ($filter_name) {
        $href = clone $uri;
        $href->setPath('/differential/filter/'.$filter_name.'/'.$username);
        if ($filter_name == $this->filter) {
          $class = 'aphront-side-nav-selected';
        } else {
          $class = null;
        }
        $item = phutil_render_tag(
          'a',
          array(
            'href' => (string)$href,
            'class' => $class,
          ),
          phutil_escape_html($display_name));
      } else {
        $item = phutil_render_tag(
          'span',
          array(),
          phutil_escape_html($display_name));
      }
      $side_nav->addNavItem($item);
    }

    $panels = array();
    $handles = array();
    $controls = $this->getFilterControls($this->filter);
    if ($this->getFilterRequiresUser($this->filter) && !$params['phid']) {
      // In the anonymous case, we still want to let you see some user's
      // list, but we don't have a default PHID to provide (normally, we use
      // the viewing user's). Show a warning instead.
      $warning = new AphrontErrorView();
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->setTitle('User Required');
      $warning->appendChild(
        'This filter requires that a user be specified above.');
      $panels[] = $warning;
    } else {
      $query = $this->buildQuery($this->filter, $params['phid']);

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

      $views = $this->buildViews($this->filter, $params['phid'], $revisions);

      $view_objects = array();
      foreach ($views as $view) {
        if (empty($view['special'])) {
          $view_objects[] = $view['view'];
        }
      }
      $phids = array_mergev(mpull($view_objects, 'getRequiredHandlePHIDs'));
      $phids[] = $params['phid'];
      $handles = $this->loadViewerHandles($phids);

      foreach ($views as $view) {
        if (empty($view['special'])) {
          $view['view']->setHandles($handles);
        }
        $panel = new AphrontPanelView();
        $panel->setHeader($view['title']);
        $panel->appendChild($view['view']);
        if ($pager) {
          $panel->appendChild($pager);
        }
        $panels[] = $panel;
      }
    }

    $filter_form = id(new AphrontFormView())
      ->setMethod('GET')
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
          ->setValue('Filter Revisions'));

    $filter_view = new AphrontListFilterView();
    $filter_view->appendChild($filter_form);

    if (!$viewer_is_anonymous) {
      $create_uri = new PhutilURI('/differential/diff/create/');
      $filter_view->addButton(
        phutil_render_tag(
          'a',
          array(
            'href'  => (string)$create_uri,
            'class' => 'green button',
          ),
          'Create Revision'));
    }

    $side_nav->appendChild($filter_view);

    foreach ($panels as $panel) {
      $side_nav->appendChild($panel);
    }

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Differential Home',
      ));
  }

  private function getFilters() {
    return array(
      array(null,         'User Revisions'),
      array('active',     'Active'),
      array('revisions',  'Revisions'),
      array('reviews',    'Reviews'),
      array('subscribed', 'Subscribed'),
      array('drafts',     'Draft Reviews'),
      array(null,         'All Revisions'),
      array('all',        'All'),
    );
  }

  private function selectFilter(
    array $filters,
    $requested_filter,
    $default_filter) {

    // If the user requested a filter, make sure it actually exists.
    if ($requested_filter) {
      foreach ($filters as $filter) {
        if ($filter[0] === $requested_filter) {
          return $requested_filter;
        }
      }
    }

    // If not, return the default filter.
    return $default_filter;
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
      'revisions'   => array('phid', 'status', 'order'),
      'reviews'     => array('phid', 'status', 'order'),
      'subscribed'  => array('subscriber', 'status', 'order'),
      'drafts'      => array('phid', 'status', 'order'),
      'all'         => array('status', 'order'),
    );
    if (!isset($controls[$filter])) {
      throw new Exception("Unknown filter '{$filter}'!");
    }
    return $controls[$filter];
  }

  private function buildQuery($filter, $user_phid) {
    $query = new DifferentialRevisionQuery();

    $query->needRelationships(true);

    switch ($filter) {
      case 'active':
        $query->withResponsibleUsers(array($user_phid));
        $query->withStatus(DifferentialRevisionQuery::STATUS_OPEN);
        $query->setLimit(null);
        break;
      case 'revisions':
        $query->withAuthors(array($user_phid));
        break;
      case 'reviews':
        $query->withReviewers(array($user_phid));
        break;
      case 'subscribed':
        $query->withSubscribers(array($user_phid));
        break;
      case 'drafts':
        $query->withDraftRepliesByAuthors(array($user_phid));
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
        $view_phid = $params['phid'];
        $value = array();
        if ($view_phid) {
          $value = array(
            $view_phid => $handles[$view_phid]->getFullName(),
          );
        }

        if ($control == 'subscriber') {
          $source = '/typeahead/common/allmailable/';
          $label = 'View Subscriber';
        } else {
          $source = '/typeahead/common/accounts/';
          $label  = 'View User';
        }

        return id(new AphrontFormTokenizerControl())
          ->setDatasource($source)
          ->setLabel($label)
          ->setName('view_user')
          ->setValue($value)
          ->setLimit(1);
      case 'status':
        return id(new AphrontFormToggleButtonsControl())
          ->setLabel('Status')
          ->setValue($params['status'])
          ->setBaseURI($uri, 'status')
          ->setButtons(
            array(
              'all'       => 'All',
              'open'      => 'Open',
              'closed'    => pht('Closed'),
              'abandoned' => 'Abandoned',
            ));
      case 'order':
        return id(new AphrontFormToggleButtonsControl())
          ->setLabel('Order')
          ->setValue($params['order'])
          ->setBaseURI($uri, 'order')
          ->setButtons(
            array(
              'modified'  => 'Updated',
              'created'   => 'Created',
            ));
      default:
        throw new Exception("Unknown control '{$control}'!");
    }
  }

  private function applyControlToQuery($control, $query, array $params) {
    switch ($control) {
      case 'phid':
      case 'subscriber':
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

  private function buildViews($filter, $user_phid, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $user = $this->getRequest()->getUser();

    $template = id(new DifferentialRevisionListView())
      ->setUser($user)
      ->setFields(DifferentialRevisionListView::getDefaultFields());

    $views = array();
    switch ($filter) {
      case 'active':
        list($active, $waiting) = DifferentialRevisionQuery::splitResponsible(
          $revisions,
          $user_phid);

        $view = id(clone $template)
          ->setHighlightAge(true)
          ->setRevisions($active)
          ->loadAssets();
        $views[] = array(
          'title' => 'Action Required',
          'view'  => $view,
        );

        // Flags are sort of private, so only show the flag panel if you're
        // looking at your own requests.
        if ($user_phid == $user->getPHID()) {
          $flags = id(new PhabricatorFlagQuery())
            ->withOwnerPHIDs(array($user_phid))
            ->withTypes(array(PhabricatorPHIDConstants::PHID_TYPE_DREV))
            ->needHandles(true)
            ->execute();

          if ($flags) {
            $view = id(new PhabricatorFlagListView())
              ->setFlags($flags)
              ->setUser($user);

            $views[] = array(
              'title'   => 'Flagged Revisions',
              'view'    => $view,
              'special' => true,
            );
          }
        }

        $view = id(clone $template)
          ->setRevisions($waiting)
          ->loadAssets();
        $views[] = array(
          'title' => 'Waiting On Others',
          'view'  => $view,
        );
        break;
      case 'revisions':
      case 'reviews':
      case 'subscribed':
      case 'drafts':
      case 'all':
        $titles = array(
          'revisions'   => 'Revisions by Author',
          'reviews'     => 'Revisions by Reviewer',
          'subscribed'  => 'Revisions by Subscriber',
          'all'         => 'Revisions',
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
