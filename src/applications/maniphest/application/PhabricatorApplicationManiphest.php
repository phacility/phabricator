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

final class PhabricatorApplicationManiphest extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Tasks and Bugs';
  }

  public function getBaseURI() {
    return '/maniphest/';
  }

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function getIconURI() {
    return celerity_get_resource_uri('/rsrc/image/app/app_maniphest.png');
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new ManiphestTask(),
    );
  }

  public function getRoutes() {
    return array(
      '/T(?P<id>\d+)' => 'ManiphestTaskDetailController',
      '/maniphest/' => array(
        '' => 'ManiphestTaskListController',
        'view/(?P<view>\w+)/' => 'ManiphestTaskListController',
        'report/(?:(?P<view>\w+)/)?' => 'ManiphestReportController',
        'batch/' => 'ManiphestBatchEditController',
        'task/' => array(
          'create/' => 'ManiphestTaskEditController',
          'edit/(?P<id>\d+)/' => 'ManiphestTaskEditController',
          'descriptionchange/(?:(?P<id>\d+)/)?' =>
            'ManiphestTaskDescriptionChangeController',
          'descriptionpreview/' =>
            'ManiphestTaskDescriptionPreviewController',
        ),
        'transaction/' => array(
          'save/' => 'ManiphestTransactionSaveController',
          'preview/(?P<id>\d+)/' => 'ManiphestTransactionPreviewController',
        ),
        'export/(?P<key>[^/]+)/' => 'ManiphestExportController',
        'subpriority/' => 'ManiphestSubpriorityController',
        'custom/' => array(
          '' => 'ManiphestSavedQueryListController',
          'edit/(?:(?P<id>\d+)/)?' => 'ManiphestSavedQueryEditController',
          'delete/(?P<id>\d+)/'   => 'ManiphestSavedQueryDeleteController',
        ),
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $query = id(new ManiphestTaskQuery())
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->withPriority(ManiphestTaskPriority::PRIORITY_UNBREAK_NOW)
      ->setLimit(1)
      ->setCalculateRows(true);
    $query->execute();

    $count = $query->getRowCount();
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Unbreak Now Task(s)!', $count))
      ->setCount($count);

    $query = id(new ManiphestTaskQuery())
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->withOwners(array($user->getPHID()))
      ->setLimit(1)
      ->setCalculateRows(true);
    $query->execute();

    $count = $query->getRowCount();
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_INFO
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Assigned Task(s)', $count));

    return $status;
  }

}

