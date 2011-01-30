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

final class DifferentialRevisionCommentView extends AphrontView {

  private $comment;
  private $handles;
  private $markupEngine;

  public function setComment($comment) {
    $this->comment = $comment;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine($markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function render() {

    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('differential-revision-comment-css');

    $comment = $this->comment;

    $action = $comment->getAction();

    $action_class = 'differential-comment-action-'.phutil_escape_html($action);

    $date = date('F jS, Y g:i:s A', $comment->getDateCreated());

    $author = $comment->getAuthorPHID();
    $author = $this->handles[$author]->renderLink();

    $verb = DifferentialAction::getActionPastTenseVerb($comment->getAction());
    $verb = phutil_escape_html($verb);

    $content = $comment->getContent();
    if (strlen(rtrim($content))) {
      $title = "{$author} {$verb} this revision:";
      $content =
        '<div class="phabricator-remarkup">'.
          $this->markupEngine->markupText($content).
        '</div>';
    } else {
      $title = null;
      $content =
        '<div class="differential-comment-nocontent">'.
          "<p>{$author} {$verb} this revision.</p>".
        '</div>';
    }

    return
      '<div class="differential-comment '.$action_class.'">'.
        '<div class="differential-comment-head">'.
          '<div class="differential-comment-date">'.$date.'</div>'.
          '<div class="differential-comment-title">'.$title.'</div>'.
        '</div>'.
        '<div class="differential-comment-body">'.
          '<div class="differential-comment-core">'.
            '<div class="differential-comment-content">'.
              $content.
            '</div>'.
          '</div>'.
        '</div>'.
      '</div>';
  }

}
