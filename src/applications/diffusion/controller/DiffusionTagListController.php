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

final class DiffusionTagListController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    $repository = $drequest->getRepository();

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    if ($drequest->getRawCommit()) {
      $is_commit = true;

      $query = DiffusionCommitTagsQuery::newFromDiffusionRequest($drequest);
      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
      $tags = $query->loadTags();
    } else {
      $is_commit = false;

      $query = DiffusionTagListQuery::newFromDiffusionRequest($drequest);
      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
      $tags = $query->loadTags();
    }

    $tags = $pager->sliceResults($tags);

    $content = null;
    if (!$tags) {
      $content = new AphrontErrorView();
      $content->setTitle('No Tags');
      if ($is_commit) {
        $content->appendChild('This commit has no tags.');
      } else {
        $content->appendChild('This repository has no tags.');
      }
      $content->setSeverity(AphrontErrorView::SEVERITY_NODATA);
    } else {
      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $drequest->getRepository()->getID(),
          mpull($tags, 'getCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionTagListView())
        ->setTags($tags)
        ->setUser($user)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $phids = $view->getRequiredHandlePHIDs();
      $handles = $this->loadViewerHandles($phids);
      $view->setHandles($handles);

      $panel = id(new AphrontPanelView())
        ->setHeader('Tags')
        ->appendChild($view)
        ->appendChild($pager);

      $content = $panel;
    }

    return $this->buildStandardPageResponse(
      array(
        $this->buildCrumbs(
          array(
            'tags'    => true,
            'commit'  => $drequest->getRawCommit(),
          )),
        $content,
      ),
      array(
        'title' => array(
          'Tags',
          $repository->getCallsign().' Repository',
        ),
      ));
  }

}
