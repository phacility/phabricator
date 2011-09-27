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

class PhabricatorRepositoryDefaultCommitMessageDetailParser
  extends PhabricatorRepositoryCommitMessageDetailParser {

  public function parseCommitDetails() {
    $commit = $this->getCommit();
    $data = $this->getCommitData();

    $details = nonempty($data->getCommitDetails(), array());
    $message = $data->getCommitMessage();
    $author_name = $data->getAuthorName();

    $match = null;

    if (preg_match(
      '/^\s*Differential Revision:\s*(\S+)\s*$/mi',
      $message,
      $match)) {

      $id = (int)$match[1];
      if ($id) {
        $details['differential.revisionID'] = (int)$match[1];
        $revision = id(new DifferentialRevision())->load($id);
        if ($revision) {
          $details['differential.revisionPHID'] = $revision->getPHID();
        }
      }
    }

    if (preg_match(
      '/^\s*Reviewed By:\s*(\S+)\s*$/mi',
      $message,
      $match)) {
      $details['reviewerName'] = $match[1];

      $reviewer_phid = $this->resolveUserPHID($details['reviewerName']);
      if ($reviewer_phid) {
        $details['reviewerPHID'] = $reviewer_phid;
      } else {
        unset($details['reviewerPHID']);
      }
    } else {
      unset($details['reviewerName']);
      unset($details['reviewerPHID']);
    }

    $author_phid = $this->resolveUserPHID($author_name);
    if ($author_phid) {
      $details['authorPHID'] = $author_phid;
    } else {
      unset($details['authorPHID']);
    }

    $data->setCommitDetails($details);
  }

}
