<?php

final class PhabricatorAuditListController extends PhabricatorAuditController {

  private $filter;
  private $name;
  private $filterStatus;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
    $this->name   = idx($data, 'name');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $nav = $this->buildNavAndSelectFilter();

    if ($request->isFormPost()) {
      // If the list filter is POST'ed, redirect to GET so the page can be
      // bookmarked.
      $uri = $request->getRequestURI();
      $phid = head($request->getArr('set_phid'));
      $user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $phid);

      $uri = $request->getRequestURI();
      if ($user) {
        $username = phutil_escape_uri($user->getUsername());
        $uri = '/audit/view/'.$this->filter.'/'.$username.'/';
      } else if ($phid) {
        $uri = $request->getRequestURI();
        $uri = $uri->alter('phid', $phid);
      }

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $this->filterStatus = $request->getStr('status', 'all');

    $handle = $this->loadHandle();

    $nav->appendChild($this->buildListFilters($handle));


    $title = null;
    $message = null;

    if (!$handle) {
      switch ($this->filter) {
        case 'project':
          $title = pht('Choose A Project');
          $message = pht('Choose a project to view audits for.');
          break;
        case 'repository':
          $title = pht('Choose A Repository');
          $message = pht('Choose a repository to view audits for.');
          break;
        case 'package':
        case 'packagecommits':
          $title = pht('Choose a Package');
          $message = pht('Choose a package to view audits for.');
          break;
      }
    }

    if (!$message) {
      $nav->appendChild($this->buildViews($handle));
    } else {
      $panel = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->setTitle($title)
        ->appendChild($message);
      $nav->appendChild($panel);
    }

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => pht('Audits'),
      ));
  }

  private function buildNavAndSelectFilter() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/audit/view/'));
    $nav->addLabel(pht('Active'));
    $nav->addFilter('active', pht('Need Attention'));

    $nav->addLabel(pht('Audits'));
    $nav->addFilter('audits', pht('All'));
    $nav->addFilter('user', pht('By User'));
    $nav->addFilter('project', pht('By Project'));
    $nav->addFilter('package', pht('By Package'));
    $nav->addFilter('repository', pht('By Repository'));

    $nav->addLabel(pht('Commits'));
    $nav->addFilter('commits', pht('All'));
    $nav->addFilter('author', pht('By Author'));
    $nav->addFilter('packagecommits', pht('By Package'));

    $this->filter = $nav->selectFilter($this->filter, 'active');

    return $nav;
  }

  private function buildListFilters(PhabricatorObjectHandle $handle = null) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $form = new AphrontFormView();
    $form->setUser($user);

    $show_status  = false;
    $show_user    = false;
    $show_project = false;
    $show_package = false;
    $show_repository = false;

    switch ($this->filter) {
      case 'audits':
      case 'commits':
        $show_status = true;
        break;
      case 'active':
        $show_user = true;
        break;
      case 'author':
      case 'user':
        $show_user = true;
        $show_status = true;
        break;
      case 'project':
        $show_project = true;
        $show_status = true;
        break;
      case 'repository':
        $show_repository = true;
        $show_status = true;
        break;
      case 'package':
      case 'packagecommits':
        $show_package = true;
        $show_status = true;
        break;
    }

    if ($show_user || $show_project || $show_package || $show_repository) {
      if ($show_user) {
        $uri = '/typeahead/common/users/';
        $label = pht('User');
      } else if ($show_project) {
        $uri = '/typeahead/common/projects/';
        $label = pht('Project');
      } else if ($show_package) {
        $uri = '/typeahead/common/packages/';
        $label = pht('Package');
      } else if ($show_repository) {
        $uri = '/typeahead/common/repositories/';
        $label = pht('Repository');
      }

      $tok_value = null;
      if ($handle) {
        $tok_value = array(
          $handle->getPHID() => $handle->getFullName(),
        );
      }

      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('set_phid')
          ->setLabel($label)
          ->setLimit(1)
          ->setDatasource($uri)
          ->setValue($tok_value));
    }

    if ($show_status) {
      $form->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setBaseURI($request->getRequestURI(), 'status')
          ->setValue($this->filterStatus)
          ->setButtons(
            array(
              'all'   => pht('All'),
              'open'  => pht('Open'),
              'concern' => pht('Concern Raised'),
            )));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Filter Audits')));

    $view = new AphrontListFilterView();
    $view->appendChild($form);
    return $view;
  }

  private function loadHandle() {
    $request = $this->getRequest();

    $default = null;
    switch ($this->filter) {
      case 'user':
      case 'active':
      case 'author':
        $default = $request->getUser()->getPHID();
        if ($this->name) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'username = %s',
            $this->name);
          if ($user) {
            $default = $user->getPHID();
          }
        }
        break;
    }

    $phid = $request->getStr('phid', $default);
    if (!$phid) {
      return null;
    }

    $phids = array($phid);
    $handles = $this->loadViewerHandles($phids);
    $handle = $handles[$phid];

    $this->validateHandle($handle);
    return $handle;
  }

  private function validateHandle(PhabricatorObjectHandle $handle) {
    switch ($this->filter) {
      case 'active':
      case 'user':
      case 'author':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_USER) {
          throw new Exception("PHID must be a user PHID!");
        }
        break;
      case 'package':
      case 'packagecommits':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_OPKG) {
          throw new Exception("PHID must be a package PHID!");
        }
        break;
      case 'project':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_PROJ) {
          throw new Exception("PHID must be a project PHID!");
        }
        break;
      case 'repository':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_REPO) {
          throw new Exception("PHID must be a repository PHID!");
        }
        break;
      case 'audits':
      case 'commits':
        break;
      default:
        throw new Exception("Unknown filter '{$this->filter}'!");
    }
  }

  private function buildViews(PhabricatorObjectHandle $handle = null) {
    $views = array();
    switch ($this->filter) {
      case 'active':
        $views[] = $this->buildCommitView($handle);
        $views[] = $this->buildAuditView($handle);
        break;
      case 'audits':
      case 'user':
      case 'package':
      case 'project':
      case 'repository':
        $views[] = $this->buildAuditView($handle);
        break;
      case 'commits':
      case 'packagecommits':
      case 'author':
        $views[] = $this->buildCommitView($handle);
        break;
    }
    return $views;
  }

  private function buildAuditView(PhabricatorObjectHandle $handle = null) {
    $request = $this->getRequest();

    $query = new PhabricatorAuditQuery();

    $use_pager = ($this->filter != 'active');

    if ($use_pager) {
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'offset');
      $pager->setOffset($request->getInt('offset'));

      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
    }

    $awaiting = null;

    $phids = null;
    $repository_phids = null;
    switch ($this->filter) {
      case 'user':
      case 'active':
        $obj = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $handle->getPHID());
        if (!$obj) {
          throw new Exception("Invalid user!");
        }
        $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($obj);
        $awaiting = $obj;
        break;
      case 'project':
      case 'package':
        $phids = array($handle->getPHID());
        break;
      case 'repository':
        $repository_phids = array($handle->getPHID());
        break;
      case 'audits';
        break;
      default:
        throw new Exception("Unknown filter!");
    }

    if ($phids) {
      $query->withAuditorPHIDs($phids);
    }

    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    if ($awaiting) {
      $query->withAwaitingUser($awaiting);
    }

    switch ($this->filter) {
      case 'active':
        $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
        break;
      default:
        switch ($this->filterStatus) {
          case 'open':
            $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
            break;
          case 'concern':
            $query->withStatus(PhabricatorAuditQuery::STATUS_CONCERN);
            break;
        }
        break;
    }

    if ($handle) {
      $handle_name = $handle->getFullName();
    } else {
      $handle_name = null;
    }

    switch ($this->filter) {
      case 'active':
        $header = pht('Required Audits');
        $nodata = pht('No commits require your audit.');
        break;
      case 'user':
        $header = pht("Audits for %s", $handle_name);
        $nodata = pht("No matching audits by %s.", $handle_name);
        break;
      case 'audits':
        $header = pht('Audits');
        $nodata = pht('No matching audits.');
        break;
      case 'project':
        $header = pht("Audits in Project %s", $handle_name);
        $nodata = pht("No matching audits in project %s.", $handle_name);
        break;
      case 'package':
        $header = pht("Audits for Package %s", $handle_name);
        $nodata = pht("No matching audits in package %s.", $handle_name);
        break;
      case 'repository':
        $header = pht("Audits in Repository %s", $handle_name);
        $nodata = pht("No matching audits in repository %s.", $handle_name);
        break;
    }

    $query->needCommitData(true);

    $audits = $query->execute();
    if ($use_pager) {
      $audits = $pager->sliceResults($audits);
    }

    $view = new PhabricatorAuditListView();
    $view->setAudits($audits);
    $view->setCommits($query->getCommits());
    $view->setUser($request->getUser());
    $view->setNoDataString($nodata);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($view);
    $panel->setNoBackground();

    if ($use_pager) {
      $panel->appendChild($pager);
    }

    return $panel;
  }

  private function buildCommitView(PhabricatorObjectHandle $handle = null) {
    $request = $this->getRequest();

    $query = new PhabricatorAuditCommitQuery();
    $query->needCommitData(true);
    $query->needAudits(true);

    $use_pager = ($this->filter != 'active');

    if ($use_pager) {
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'offset');
      $pager->setOffset($request->getInt('offset'));

      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
    }

    switch ($this->filter) {
      case 'active':
      case 'author':
        $query->withAuthorPHIDs(array($handle->getPHID()));
        break;
      case 'packagecommits':
        $query->withPackagePHIDs(array($handle->getPHID()));
        break;
    }

    switch ($this->filter) {
      case 'active':
        $query->withStatus(PhabricatorAuditCommitQuery::STATUS_CONCERN);
        break;
      default:
        switch ($this->filterStatus) {
          case 'open':
            $query->withStatus(PhabricatorAuditCommitQuery::STATUS_OPEN);
            break;
          case 'concern':
            $query->withStatus(PhabricatorAuditCommitQuery::STATUS_CONCERN);
            break;
        }
        break;
    }

    if ($handle) {
      $handle_name = $handle->getName();
    } else {
      $handle_name = null;
    }

    switch ($this->filter) {
      case 'active':
        $header = pht('Problem Commits');
        $nodata = pht('None of your commits have open concerns.');
        break;
      case 'author':
        $header = pht("Commits by %s", $handle_name);
        $nodata = pht("No matching commits by %s.", $handle_name);
        break;
      case 'commits':
        $header = pht("Commits");
        $nodata = pht("No matching commits.");
        break;
      case 'packagecommits':
        $header = pht("Commits in Package %s", $handle_name);
        $nodata = pht("No matching commits in package %s.", $handle_name);
        break;
    }

    $commits = $query->execute();

    if ($use_pager) {
      $commits = $pager->sliceResults($commits);
    }

    $view = new PhabricatorAuditCommitListView();
    $view->setUser($request->getUser());
    $view->setCommits($commits);
    $view->setNoDataString($nodata);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($view);
    $panel->setNoBackground();

    if ($use_pager) {
      $panel->appendChild($pager);
    }

    return $panel;
  }

}
