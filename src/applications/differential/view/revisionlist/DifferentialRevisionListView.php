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

/**
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions;
  private $handles;
  private $user;
  private $noDataString;

  public function setRevisions(array $revisions) {
    $this->revisions = $revisions;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->revisions as $revision) {
      $phids[] = $revision->getAuthorPHID();
      $reviewers = $revision->getReviewers();
      if ($reviewers) {
        $phids[] = head($reviewers);
      }
    }
    return $phids;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function render() {

    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    $rows = array();
    foreach ($this->revisions as $revision) {
      $status = $revision->getStatus();
      $status = DifferentialRevisionStatus::getNameForRevisionStatus($status);

      $reviewer_phids = $revision->getReviewers();
      if ($reviewer_phids) {
        $first = reset($reviewer_phids);
        if (count($reviewer_phids) > 1) {
          $suffix = ' (+'.(count($reviewer_phids) - 1).')';
        } else {
          $suffix = null;
        }
        $reviewers = $this->handles[$first]->renderLink().$suffix;
      } else {
        $reviewers = '<em>None</em>';
      }

      $link = phutil_render_tag(
        'a',
        array(
          'href' => '/D'.$revision->getID(),
        ),
        phutil_escape_html($revision->getTitle()));

      $rows[] = array(
        'D'.$revision->getID(),
        $link,
        phutil_escape_html($status),
        number_format($revision->getLineCount()),
        $this->handles[$revision->getAuthorPHID()]->renderLink(),
        $reviewers,
        phabricator_datetime($revision->getDateModified(), $user),
        phabricator_date($revision->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Revision',
        'Status',
        'Lines',
        'Author',
        'Reviewers',
        'Updated',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide pri',
        null,
        'n',
        null,
        null,
        'right',
        'right',
      ));

    if ($this->noDataString) {
      $table->setNoDataString($this->noDataString);
    }

    return $table->render();
  }

}
