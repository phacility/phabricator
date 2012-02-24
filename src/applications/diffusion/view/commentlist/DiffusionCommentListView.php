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

final class DiffusionCommentListView extends AphrontView {

  private $user;
  private $comments;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setComments(array $comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->comments as $comment) {
      $phids[$comment->getActorPHID()] = true;
    }
    return array_keys($phids);
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }


  public function render() {

    $num = 1;

    $comments = array();
    foreach ($this->comments as $comment) {
      $view = id(new DiffusionCommentView())
        ->setComment($comment)
        ->setCommentNumber($num)
        ->setHandles($this->handles)
        ->setUser($this->user);

      $comments[] = $view->render();
      ++$num;
    }

    return
      '<div class="diffusion-comment-list">'.
        $this->renderSingleView($comments).
      '</div>';
  }

}
