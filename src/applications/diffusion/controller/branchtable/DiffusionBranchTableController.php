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

final class DiffusionBranchTableController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    $repository = $drequest->getRepository();

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    // TODO: Add support for branches that contain commit
    $query = DiffusionBranchQuery::newFromDiffusionRequest($drequest);
    $query->setOffset($pager->getOffset());
    $query->setLimit($pager->getPageSize() + 1);
    $branches = $query->loadBranches();

    $branches = $pager->sliceResults($branches);

    $content = null;
    if (!$branches) {
      $content = new AphrontErrorView();
      $content->setTitle('No Branches');
      $content->appendChild('This repository has no branches.');
      $content->setSeverity(AphrontErrorView::SEVERITY_NODATA);
    } else {
      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $drequest->getRepository()->getID(),
          mpull($branches, 'getHeadCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionBranchTableView())
        ->setBranches($branches)
        ->setUser($user)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $panel = id(new AphrontPanelView())
        ->setHeader('Branches')
        ->appendChild($view)
        ->appendChild($pager);

      $content = $panel;
    }

    return $this->buildStandardPageResponse(
      array(
        $this->buildCrumbs(
          array(
            'branches'    => true,
          )),
        $content,
      ),
      array(
        'title' => array(
          'Branches',
          $repository->getCallsign().' Repository',
        ),
      ));
  }

}
