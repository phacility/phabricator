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

final class PonderQuestionSummaryView extends AphrontView {
  private $user;
  private $question;
  private $handles;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-feed-view-css');

    $user = $this->user;
    $question = $this->question;
    $author_phid = $question->getAuthorPHID();
    $handles = $this->handles;

    $authorlink = $handles[$author_phid]
      ->renderLink();

    $votecount =
      '<div class="ponder-summary-votes">'.
        phutil_escape_html($question->getVoteCount()).
        '<div class="ponder-question-label">'.
          'votes'.
        '</div>'.
      '</div>';

    $answerclass = "ponder-summary-answers";
    if ($question->getAnswercount() == 0) {
      $answerclass .= " ponder-not-answered";
    }
    $answercount =
      '<div class="ponder-summary-answers">'.
        phutil_escape_html($question->getAnswerCount()).
        '<div class="ponder-question-label">'.
          'answers'.
        '</div>'.
      '</div>';


    $title =
      '<h2 class="ponder-question-title">'.
        phutil_render_tag(
          'a',
          array(
            "href" => '/Q' . $question->getID(),
          ),
          phutil_escape_html(
            'Q' . $question->getID() .
            ' ' . $question->getTitle()
          )
        ) .
      '</h2>';

    $rhs =
      '<div class="ponder-metadata">'.
        $title.
       '<span class="ponder-small-metadata">'.
        'asked on '.
        phabricator_datetime($question->getDateCreated(), $user).
        ' by ' . $authorlink.
       '</span>'.
      '</div>';

    $summary =
      '<div class="ponder-question-summary">'.
        $votecount.
        $answercount.
        $rhs.
      '</div>';


    return $summary;
  }
}
