<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DifferentialRevisionListController extends DifferentialController {

  private $filter;

  public function shouldRequireLogin() {
    return !$this->allowsAnonymousAccess();
  }

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $viewer_is_anonymous = !$user->isLoggedIn();

    if ($request->isFormPost()) {
      $phid_arr = $request->getArr('view_user');
      $view_target = head($phid_arr);
      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('phid', $view_target));
    }

    $params = array_filter(
      array(
        'phid' => $request->getStr('phid'),
        'status' => $request->getStr('status'),
        'order' => $request->getStr('order'),
      ));

    $default_filter = ($viewer_is_anonymous ? 'all' : 'active');
    $filters = $this->getFilters();
    $this->filter = $this->selectFilter(
      $filters,
      $this->filter,
      $default_filter);

    $uri = new PhutilURI('/differential/filter/'.$this->filter.'/');
    $uri->setQueryParams($params);

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
        $href->setPath('/differential/filter/'.$filter_name.'/');
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

      $view_objects = ipull($views, 'view');
      $phids = array_mergev(mpull($view_objects, 'getRequiredHandlePHIDs'));
      $phids[] = $params['phid'];
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      foreach ($views as $view) {
        $view['view']->setHandles($handles);
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
        'tab' => 'revisions',
      ));
  }

  private function getFilters() {
    return array(
      array(null,         'User Revisions'),
      array('active',     'Active'),
      array('revisions',  'Revisions'),
      array('reviews',    'Reviews'),
      array('subscribed', 'Subscribed'),
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
      'subscribed'  => array('phid', 'status', 'order'),
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
    switch ($control) {
      case 'phid':
        $view_phid = $params['phid'];
        $value = array();
        if ($view_phid) {
          $value = array(
            $view_phid => $handles[$view_phid]->getFullName(),
          );
        }
        return id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setLabel('View User')
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
              'committed' => 'Committed',
            ));
      case 'order':
        return id(new AphrontFormToggleButtonsControl())
          ->setLabel('Order')
          ->setValue($params['order'])
          ->setBaseURI($uri, 'order')
          ->setButtons(
            array(
              'modified'  => 'Modified',
              'created'   => 'Created',
            ));
      default:
        throw new Exception("Unknown control '{$control}'!");
    }
  }

  private function applyControlToQuery($control, $query, array $params) {
    switch ($control) {
      case 'phid':
        // Already applied by query construction.
        break;
      case 'status':
        if ($params['status'] == 'open') {
          $query->withStatus(DifferentialRevisionQuery::STATUS_OPEN);
        } elseif ($params['status'] == 'committed') {
          $query->withStatus(DifferentialRevisionQuery::STATUS_COMMITTED);
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
          ->setRevisions($active)
          ->setNoDataString("You have no active revisions requiring action.");
        $views[] = array(
          'title' => 'Action Required',
          'view'  => $view,
        );

        $view = id(clone $template)
          ->setRevisions($waiting)
          ->setNoDataString("You have no active revisions waiting on others.");
        $views[] = array(
          'title' => 'Waiting On Others',
          'view'  => $view,
        );
        break;
      case 'revisions':
      case 'reviews':
      case 'subscribed':
      case 'all':
        $titles = array(
          'revisions'   => 'Revisions by Author',
          'reviews'     => 'Revisions by Reviewer',
          'subscribed'  => 'Revisions by Subscriber',
          'all'         => 'Revisions',
        );
        $view = id(clone $template)
          ->setRevisions($revisions);
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
