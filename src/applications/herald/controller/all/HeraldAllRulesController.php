<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class HeraldAllRulesController extends HeraldController {

  private $view;
  private $viewPHID;

  public function shouldRequireAdmin() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $this->viewPHID = nonempty($request->getStr('phid'), null);

    if ($request->isFormPost()) {
      $phid_arr = $request->getArr('view_user');
      $view_target = head($phid_arr);
      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('phid', $view_target));
    }

    $map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($map[$this->view])) {
      reset($map);
      $this->view = key($map);
    }

    $offset = $request->getInt('offset', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize(50);
    $pager->setOffset($offset);
    $pager->setURI($request->getRequestURI(), 'offset');

    list($rules, $handles) = $this->queryRules($pager);

    if (!$this->viewPHID) {
      $view_users = array();
    } else {
      $view_users = array(
        $this->viewPHID => $handles[$this->viewPHID]->getFullName(),
      );
    }

    $filter_form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setLabel('View User')
          ->setName('view_user')
          ->setValue($view_users)
          ->setLimit(1));
    $filter_view = new AphrontListFilterView();
    $filter_view->appendChild($filter_form);

    $list_view = id(new HeraldRuleListView())
      ->setRules($rules)
      ->setHandles($handles)
      ->setMap($map)
      ->setAllowCreation(false)
      ->setView($this->view);
    $panel = $list_view->render();
    $panel->appendChild($pager);

    $sidenav = new AphrontSideNavView();
    $sidenav->appendChild($filter_view);
    $sidenav->appendChild($panel);

    $query = '';
    if ($this->viewPHID) {
      $query = '?phid='.$this->viewPHID;
    }

    foreach ($map as $key => $value) {
      $sidenav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/herald/all/view/'.$key.'/'.$query,
            'class' => ($key == $this->view)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($value)));
    }

    return $this->buildStandardPageResponse(
      $sidenav,
      array(
        'title' => 'Herald',
        'tab' => 'all',
      ));
  }

  private function queryRules(AphrontPagerView $pager) {
    $rule = new HeraldRule();
    $conn_r = $rule->establishConnection('r');

    $where_clause = qsprintf(
      $conn_r,
      'WHERE contentType = %s',
      $this->view);

    if ($this->viewPHID) {
      $where_clause .= qsprintf(
        $conn_r,
        ' AND authorPHID = %s',
        $this->viewPHID);
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T
        %Q
        ORDER BY id DESC
        LIMIT %d, %d',
      $rule->getTableName(),
      $where_clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);
    $rules = $rule->loadAllFromArray($data);

    $need_phids = mpull($rules, 'getAuthorPHID');
    if ($this->viewPHID) {
      $need_phids[] = $this->viewPHID;
    }

    $handles = id(new PhabricatorObjectHandleData($need_phids))
      ->loadHandles();

    return array($rules, $handles);
  }
}

