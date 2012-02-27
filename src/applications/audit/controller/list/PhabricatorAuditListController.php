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

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/audit/view/'));
    $nav->addLabel('Active');
    $nav->addFilter('active',  'Need Attention');
    $nav->addLabel('Audits');
    $nav->addFilter('all',  'All');

    $this->filter = $nav->selectFilter($this->filter, 'active');

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');

    $query = new PhabricatorAuditQuery();
    $query->setOffset($pager->getOffset());
    $query->setLimit($pager->getPageSize() + 1);

    switch ($this->filter) {
      case 'all':
        break;
      case 'active':
        $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);
        $query->withAuditorPHIDs($phids);
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

    $nav->appendChild($panel);
    $nav->appendChild($pager);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Audits',
      ));
  }

}
