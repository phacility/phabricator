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

final class PhabricatorApplicationAudit extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Audit Code';
  }

  public function getBaseURI() {
    return '/audit/';
  }

  public function getAutospriteName() {
    return 'audit';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Audit_User_Guide.html');
  }

  public function getRoutes() {
    return array(
      '/audit/' => array(
        '' => 'PhabricatorAuditListController',
        'view/(?P<filter>[^/]+)/(?:(?P<name>[^/]+)/)?'
          => 'PhabricatorAuditListController',
        'addcomment/' => 'PhabricatorAuditAddCommentController',
        'preview/(?P<id>[1-9]\d*)/' => 'PhabricatorAuditPreviewController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getApplicationOrder() {
    return 0.130;
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $audits = id(new PhabricatorAuditQuery())
      ->withAuditorPHIDs($phids)
      ->withStatus(PhabricatorAuditQuery::STATUS_OPEN)
      ->withAwaitingUser($user)
      ->execute();

    $count = count($audits);
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_INFO
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Commit(s) Awaiting Audit', $count))
      ->setCount($count);


    $commits = id(new PhabricatorAuditCommitQuery())
      ->withAuthorPHIDs($phids)
      ->withStatus(PhabricatorAuditQuery::STATUS_OPEN)
      ->execute();

    $count = count($commits);
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Problem Commit(s)', $count))
      ->setCount($count);

    return $status;
  }

}

