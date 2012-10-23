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

final class PhabricatorFeedStoryAudit extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getStoryData()->getValue('commitPHID');
  }

  public function renderView() {
    $author_phid = $this->getAuthorPHID();
    $commit_phid = $this->getPrimaryObjectPHID();

    $view = new PhabricatorFeedStoryView();

    $action = $this->getValue('action');
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $view->setTitle(
      $this->linkTo($author_phid).
      " {$verb} commit ".
      $this->linkTo($commit_phid).
      ".");

    $view->setEpoch($this->getEpoch());

    $comments = $this->getValue('content');
    if ($comments) {
      $full_size = true;
    } else {
      $full_size = false;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($author_phid)->getImageURI());
      $content = $this->renderSummary($this->getValue('content'));
      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
