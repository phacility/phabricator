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

final class PhabricatorFeedStoryCommit extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('commitPHID');
  }

  public function getRequiredHandlePHIDs() {
    return array(
      $this->getValue('committerPHID'),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author = null;
    if ($data->getValue('authorPHID')) {
      $author = $this->linkTo($data->getValue('authorPHID'));
    } else {
      $author = phutil_escape_html($data->getValue('authorName'));
    }

    $committer = null;
    if ($data->getValue('committerPHID')) {
      $committer = $this->linkTo($data->getValue('committerPHID'));
    } else if ($data->getValue('committerName')) {
      $committer = phutil_escape_html($data->getValue('committerName'));
    }

    $commit = $this->linkTo($data->getValue('commitPHID'));

    if (!$committer) {
      $committer = $author;
      $author = null;
    }

    if ($author) {
      $title = "{$committer} committed {$commit} (authored by {$author})";
    } else {
      $title = "{$committer} committed {$commit}";
    }

    $view = new PhabricatorFeedStoryView();

    $view->setTitle($title);
    $view->setEpoch($data->getEpoch());

    if ($data->getValue('authorPHID')) {
      $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
    }

    $content = $this->renderSummary($data->getValue('summary'));
    $view->appendChild($content);

    return $view;
  }

}
