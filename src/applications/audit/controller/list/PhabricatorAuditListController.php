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

final class PhabricatorAuditListController extends PhabricatorAuditController {

  private $filter;
  private $filterStatus;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();

    if ($request->isFormPost()) {
      // If the list filter is POST'ed, redirect to GET so the page can be
      // bookmarked.
      $uri = $request->getRequestURI();
      $phid = head($request->getArr('phid'));
      if ($phid) {
        $uri = $uri->alter('phid', $phid);
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $nav = $this->buildNavAndSelectFilter();
    $this->filterStatus = $request->getStr('status', 'all');
    $handle = $this->loadHandle();

    $nav->appendChild($this->buildListFilters($handle));


    $title = null;
    $message = null;

    if (!$handle) {
      switch ($this->filter) {
        case 'project':
          $title = 'Choose A Project';
          $message = 'Choose a project to view audits for.';
          break;
        case 'package':
          $title = 'Choose a Package';
          $message = 'Choose a package to view audits for.';
          break;
      }
    }

    if (!$message) {
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'offset');

      $query = new PhabricatorAuditQuery();
      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);

      $phids = null;
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
          break;
        case 'project':
        case 'package':
          $phids = array($handle->getPHID());
          break;
        case 'all';
          break;
        default:
          throw new Exception("Unknown filter!");
      }

      if ($phids) {
        $query->withAuditorPHIDs($phids);
      }

      switch ($this->filter) {
        case 'all':
        case 'user':
        case 'project':
        case 'package':
          switch ($this->filterStatus) {
            case 'open':
              $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
              break;
          }
          break;
        case 'active':
          $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
          break;
      }

      $audits = $query->execute();
      $audits = $pager->sliceResults($audits);

      $view = new PhabricatorAuditListView();
      $view->setAudits($audits);

      $phids = $view->getRequiredHandlePHIDs();
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
      $view->setHandles($handles);

      $panel = new AphrontPanelView();
      $panel->appendChild($view);
      $panel->setHeader('Audits');

    } else {
      $panel = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->setTitle($title)
        ->appendChild($message);
      $pager = null;
    }

    $nav->appendChild($panel);
    $nav->appendChild($pager);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Audits',
      ));
  }

  private function buildNavAndSelectFilter() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/audit/view/'));
    $nav->addLabel('Active');
    $nav->addFilter('active',  'Need Attention');
    $nav->addLabel('Audits');
    $nav->addFilter('all',  'All');
    $nav->addFilter('user',  'By User');
    $nav->addFilter('project',  'By Project');
    $nav->addFilter('package',  'By Package');

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

    switch ($this->filter) {
      case 'all':
        $show_status = true;
        break;
      case 'active':
        $show_user = true;
        break;
      case 'user':
        $show_user = true;
        $show_status = true;
        break;
      case 'project':
        $show_project = true;
        $show_status = true;
        break;
      case 'package':
        $show_package = true;
        $show_status = true;
        break;
    }

    if ($show_user || $show_project || $show_package) {
      if ($show_user) {
        $uri = '/typeahead/common/user/';
        $label = 'User';
      } else if ($show_project) {
        $uri = '/typeahead/common/projects/';
        $label = 'Project';
      } else if ($show_package) {
        $uri = '/typeahead/common/packages/';
        $label = 'Package';
      }

      $tok_value = null;
      if ($handle) {
        $tok_value = array(
          $handle->getPHID() => $handle->getFullName(),
        );
      }

      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('phid')
          ->setLabel($label)
          ->setLimit(1)
          ->setDatasource($uri)
          ->setValue($tok_value));
    }

    if ($show_status) {
      $form->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setName('status')
          ->setLabel('Status')
          ->setBaseURI($request->getRequestURI(), 'status')
          ->setValue($this->filterStatus)
          ->setButtons(
            array(
              'all'   => 'All',
              'open'  => 'Open',
            )));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue('Filter Audits'));

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
        $default = $request->getUser()->getPHID();
        break;
    }

    $phid = $request->getStr('phid', $default);
    if (!$phid) {
      return null;
    }

    $phids = array($phid);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $handle = $handles[$phid];

    $this->validateHandle($handle);
    return $handle;
  }

  private function validateHandle(PhabricatorObjectHandle $handle) {
    switch ($this->filter) {
      case 'active':
      case 'user':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_USER) {
          throw new Exception("PHID must be a user PHID!");
        }
        break;
      case 'package':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_OPKG) {
          throw new Exception("PHID must be a package PHID!");
        }
        break;
      case 'project':
        if ($handle->getType() !== PhabricatorPHIDConstants::PHID_TYPE_PROJ) {
          throw new Exception("PHID must be a project PHID!");
        }
        break;
      case 'all':
        break;
      default:
        throw new Exception("Unknown filter '{$this->filter}'!");
    }
  }

}
