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

final class DifferentialRevisionCommentListView extends AphrontView {

  private $comments;
  private $handles;
  private $inlines;
  private $changesets;
  private $user;
  private $target;
  private $versusDiffID;

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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setTargetDiff(DifferentialDiff $target) {
    $this->target = $target;
    return $this;
  }

  public function setVersusDiffID($diff_vs) {
    $this->versusDiffID = $diff_vs;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-revision-comment-list-css');

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine(array(
      'differential.diff' => $this->target
    ));

    $inlines = mgroup($this->inlines, 'getCommentID');

    $num = 1;
    $html = array();
    foreach ($this->comments as $comment) {
      $view = new DifferentialRevisionCommentView();
      $view->setComment($comment);
      $view->setUser($this->user);
      $view->setHandles($this->handles);
      $view->setMarkupEngine($engine);
      $view->setInlineComments(idx($inlines, $comment->getID(), array()));
      $view->setChangesets($this->changesets);
      $view->setTargetDiff($this->target);
      $view->setVersusDiffID($this->versusDiffID);
      if ($comment->getAction() == DifferentialAction::ACTION_SUMMARIZE) {
        $view->setAnchorName('summary');
      } elseif ($comment->getAction() == DifferentialAction::ACTION_TESTPLAN) {
        $view->setAnchorName('test-plan');
      } else {
        $view->setAnchorName('comment-'.$num);
        $num++;
      }

      $html[] = $view->render();
    }

    $objs = array_reverse(array_values($this->comments));
    $html = array_reverse(array_values($html));
    $user = $this->user;

    $last_comment = null;
    // Find the most recent comment by the viewer.
    foreach ($objs as $position => $comment) {
      if ($user && ($comment->getAuthorPHID() == $user->getPHID())) {
        if ($last_comment === null) {
          $last_comment = $position;
        } else if ($last_comment == $position - 1) {
          // If the viewer made several comments in a row, show them all. This
          // is a spaz rule for epriestley.
          $last_comment = $position;
        }
      }
    }

    $header = array();
    $hidden = array();
    if ($last_comment !== null) {
      foreach ($objs as $position => $comment) {
        if (!$comment->getID()) {
          // These are synthetic comments with summary/test plan information.
          $header[] = $html[$position];
          unset($html[$position]);
          continue;
        }
        if ($position <= $last_comment) {
          // Always show comments after the viewer's last comment.
          continue;
        }
        if ($position < 3) {
          // Always show the 3 most recent comments.
          continue;
        }
        $hidden[] = $position;
      }
    }

    if (count($hidden) <= 3) {
      // Don't hide if there's not much to hide.
      $hidden = array();
    }

    $header = array_reverse($header);


    $hidden = array_select_keys($html, $hidden);
    $visible = array_diff_key($html, $hidden);

    $hidden = array_reverse($hidden);
    $visible = array_reverse($visible);

    if ($hidden) {
      Javelin::initBehavior(
        'differential-show-all-comments',
        array(
          'markup' => implode("\n", $hidden),
        ));
      $hidden = javelin_render_tag(
        'div',
        array(
          'sigil' =>  "differential-all-comments-container",
        ),
        '<div class="differential-older-comments-are-hidden">'.
          number_format(count($hidden)).' older comments are hidden. '.
          javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'mustcapture' => true,
              'sigil' => 'differential-show-all-comments',
            ),
            'Show all comments.').
        '</div>');
    } else {
      $hidden = null;
    }

    return
      '<div class="differential-comment-list">'.
        implode("\n", $header).
        $hidden.
        implode("\n", $visible).
      '</div>';
  }
}
