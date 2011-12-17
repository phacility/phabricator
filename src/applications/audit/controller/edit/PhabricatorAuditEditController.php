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

class PhabricatorAuditEditController extends PhabricatorAuditController {

  private $request;
  private $user;
  private $commitPHID;
  private $packagePHID;

  public function processRequest() {
    $this->request = $this->getRequest();
    $this->user = $this->request->getUser();
    $this->commitPHID = $this->request->getStr('c-phid');
    $this->packagePHID = $this->request->getStr('p-phid');

    $relationship = id(new PhabricatorOwnersPackageCommitRelationship())
      ->loadOneWhere(
        'commitPHID = %s AND packagePHID=%s',
        $this->commitPHID,
        $this->packagePHID);
    if (!$relationship) {
      return new Aphront404Response();
    }

    $package = id(new PhabricatorOwnersPackage())->loadOneWhere(
      "phid = %s",
      $this->packagePHID);

    $owners = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID = %d',
      $package->getID());
    $owners_phids = mpull($owners, 'getUserPHID');
    if (!$this->user->getIsAdmin() &&
        !in_array($this->user->getPHID(), $owners_phids)) {
      return $this->buildStandardPageResponse(
        id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
          ->setTitle("Only admin or owner of the package can audit the ".
            "commit."),
        array(
          'title' => 'Audit a Commit',
        ));
    }

    if ($this->request->isFormPost()) {
      return $this->saveAuditComments();
    }

    $package_link = phutil_render_tag(
      'a',
      array(
        'href' => '/owners/package/'.$package->getID().'/',
      ),
      phutil_escape_html($package->getName()));

    $phids = array(
      $this->commitPHID,
    );
    $loader = new PhabricatorObjectHandleData($phids);
    $handles = $loader->loadHandles();
    $objects = $loader->loadObjects();

    $commit_handle = $handles[$this->commitPHID];
    $commit_object = $objects[$this->commitPHID];
    $commit_data = $commit_object->getCommitData();
    $commit_epoch = $commit_handle->getTimeStamp();
    $commit_datetime = phabricator_datetime($commit_epoch, $this->user);
    $commit_link = $this->renderHandleLink($commit_handle);

    $revision_author_phid = null;
    $revision_reviewedby_phid = null;
    $revision_link = null;
    $revision_id = $commit_data->getCommitDetail('differential.revisionID');
    if ($revision_id) {
      $revision = id(new DifferentialRevision())->load($revision_id);
      if ($revision) {
        $revision->loadRelationships();
        $revision_author_phid = $revision->getAuthorPHID();
        $revision_reviewedby_phid = $revision->loadReviewedBy();
        $revision_link = phutil_render_tag(
          'a',
          array(
            'href' => '/D'.$revision->getID()
          ),
          phutil_escape_html($revision->getTitle()));
      }
    }

    $commit_author_phid = $commit_data->getCommitDetail('authorPHID');
    $commit_reviewedby_phid = $commit_data->getCommitDetail('reviewerPHID');
    $conn_r = id(new PhabricatorAuditComment())->establishConnection('r');
    $latest_comment = queryfx_one(
      $conn_r,
      'SELECT * FROM %T
        WHERE targetPHID = %s and actorPHID in (%Ls)
        ORDER BY ID DESC LIMIT 1',
      id(new PhabricatorAuditComment())->getTableName(),
      $this->commitPHID,
      $owners_phids);
    $auditor_phid = $latest_comment['actorPHID'];

    $user_phids = array_unique(array_filter(array(
      $revision_author_phid,
      $revision_reviewedby_phid,
      $commit_author_phid,
      $commit_reviewedby_phid,
      $auditor_phid,
    )));
    $user_loader = new PhabricatorObjectHandleData($user_phids);
    $user_handles = $user_loader->loadHandles();
    if ($commit_author_phid && isset($handles[$commit_author_phid])) {
      $commit_author_link = $handles[$commit_author_phid]->renderLink();
    } else {
      $commit_author_link = phutil_escape_html($commit_data->getAuthorName());
    }

    $reasons = $relationship->getAuditReasons();
    $reasons = array_map('phutil_escape_html', $reasons);
    $reasons = implode($reasons, '<br>');

    $latest_comment_content = id(new AphrontFormTextAreaControl())
      ->setLabel('Audit comments')
      ->setName('latest_comments')
      ->setReadOnly(true)
      ->setValue($latest_comment['content']);
    $latest_comment_epoch = $latest_comment['dateModified'];
    $latest_comment_datetime =
      phabricator_datetime($latest_comment_epoch, $this->user);

    $select = id(new AphrontFormSelectControl())
      ->setLabel('Audit it')
      ->setName('action')
      ->setValue(PhabricatorAuditActionConstants::ACCEPT)
      ->setOptions(PhabricatorAuditActionConstants::getActionNameMap());

    $comment = id(new AphrontFormTextAreaControl())
      ->setLabel('Audit comments')
      ->setName('comments')
      ->setCaption("Explain the audit.");

    $submit = id(new AphrontFormSubmitControl())
      ->setValue('Save')
      ->addCancelButton('/owners/related/view/audit/?phid='.$this->packagePHID);

    $form = id(new AphrontFormView())
      ->setUser($this->user)
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Package')
        ->setValue($package_link))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Commit')
        ->setValue($commit_link))
      ->appendChild(new AphrontFormDividerControl())
      ->appendChild(id(new AphrontFormStaticControl())
        ->setLabel('Commit Summary')
        ->setValue(phutil_escape_html($commit_data->getSummary())))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Commit Author')
        ->setValue($commit_author_link))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Commit Reviewed By')
        ->setValue(
          $this->renderHandleLink(
            idx($user_handles, $commit_reviewedby_phid))))
      ->appendChild(id(new AphrontFormStaticControl())
        ->setLabel('Commit Time')
        ->setValue($commit_datetime))
      ->appendChild(new AphrontFormDividerControl())
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Revision')
        ->setValue($revision_link))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Revision Author')
        ->setValue(
          $this->renderHandleLink(idx($user_handles, $revision_author_phid))))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Revision Reviewed By')
        ->setValue(
          $this->renderHandleLink(
            idx($user_handles, $revision_reviewedby_phid))))
      ->appendChild(new AphrontFormDividerControl())
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Audit Reasons')
        ->setValue($reasons))
      ->appendChild(id(new AphrontFormMarkupControl())
        ->setLabel('Latest Auditor')
        ->setValue($this->renderHandleLink(idx($user_handles, $auditor_phid))))
      ->appendChild(id(new AphrontFormStaticControl())
        ->setLabel('Latest Audit Status')
        ->setValue(idx(PhabricatorAuditStatusConstants::getStatusNameMap(),
          $relationship->getAuditStatus())))
      ->appendChild(id(new AphrontFormStaticControl())
        ->setLabel('Latest Audit Time')
        ->setValue($latest_comment_datetime))
      ->appendChild($latest_comment_content)
      ->appendChild(new AphrontFormDividerControl())
      ->appendChild($select)
      ->appendChild($comment)
      ->appendChild($submit);

    $panel = id(new AphrontPanelView())
      ->setHeader('Audit a Commit')
      ->setWidth(AphrontPanelView::WIDTH_WIDE)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Audit a Commit',
      ));
  }

  private function saveAuditComments() {
    $action = $this->request->getStr('action');
    $status_map = PhabricatorAuditActionConstants::getStatusNameMap();
    $status = idx($status_map, $action, null);
    if ($status === null) {
      return $this->buildStandardPageResponse(
        id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
          ->setTitle("Action {$action} is invalid."),
        array(
          'title' => 'Audit a Commit',
        ));
    }

    id(new PhabricatorAuditComment())
      ->setActorPHID($this->user->getPHID())
      ->setTargetPHID($this->commitPHID)
      ->setAction($action)
      ->setContent($this->request->getStr('comments'))
      ->save();

    // Update the audit status for all the relationships <commit, package>
    // where the package is owned by the user. When a user owns several
    // packages and a commit touches all of them,It should be good enough for
    // the user to approve it once to get all the relationships automatically
    // updated.
    $owned_packages = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'userPHID = %s',
      $this->user->getPHID());
    $owned_package_ids = mpull($owned_packages, 'getPackageID');

    $conn_r = id(new PhabricatorOwnersPackage())->establishConnection('r');
    $owned_package_phids = queryfx_all(
      $conn_r,
      'SELECT `phid` FROM %T WHERE id IN (%Ld)',
      id(new PhabricatorOwnersPackage())->getTableName(),
      $owned_package_ids);
    $owned_package_phids = ipull($owned_package_phids, 'phid');

    $relationships = id(new PhabricatorOwnersPackageCommitRelationship())
      ->loadAllWhere(
        'commitPHID = %s AND packagePHID IN (%Ls)',
        $this->commitPHID,
        $owned_package_phids);

    foreach ($relationships as $relationship) {
      $relationship->setAuditStatus($status);
      $relationship->save();
    }

    return id(new AphrontRedirectResponse())
      ->setURI(sprintf('/audit/edit/?c-phid=%s&p-phid=%s',
        $this->commitPHID,
        $this->packagePHID));
  }

  private function renderHandleLink($handle) {
    if (!$handle) {
      return null;
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => $handle->getURI(),
      ),
      phutil_escape_html($handle->getName()));
  }
}
