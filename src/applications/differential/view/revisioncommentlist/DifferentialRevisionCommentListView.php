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

final class DifferentialRevisionCommentListView extends AphrontView {

  private $comments;
  private $handles;
  private $inlines;
  private $changesets;

  public function setComments(array $comments) {
    $this->comments = $comments;
    return $this;
  }

  public function setInlineComments(array $inline_comments) {
    $this->inlines = $inline_comments;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setChangesets(array $changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-revision-comment-list-css');

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    $inlines = mgroup($this->inlines, 'getCommentID');


    $comments = array();
    foreach ($this->comments as $comment) {
      $view = new DifferentialRevisionCommentView();
      $view->setComment($comment);
      $view->setHandles($this->handles);
      $view->setMarkupEngine($engine);
      $view->setInlineComments(idx($inlines, $comment->getID(), array()));
      $view->setChangesets($this->changesets);

      $comments[] = $view->render();
    }

    return
      '<div>'.
        implode("\n", $comments).
      '</div>';
  }
}
