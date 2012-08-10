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

final class PonderAnswerSummaryView extends AphrontView {
  private $user;
  private $answer;
  private $handles;

  public function setAnswer($answer) {
    $this->answer = $answer;
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

  private static function abbreviate($w) {
    return phutil_utf8_shorten($w, 60);
  }

  public function render() {
    require_celerity_resource('ponder-feed-view-css');

    $user = $this->user;
    $answer = $this->answer;
    $question = $answer->getQuestion();
    $author_phid = $question->getAuthorPHID();
    $handles = $this->handles;

    $votecount =
      '<div class="ponder-summary-votes">'.
        phutil_escape_html($answer->getVoteCount()).
        '<div class="ponder-question-label">'.
          'votes'.
        '</div>'.
      '</div>';

    $title =
      '<h2 class="ponder-question-title">'.
        phutil_render_tag(
          'a',
          array(
           "href" => id(new PhutilURI('/Q' . $question->getID()))
             ->setFragment('A' . $answer->getID())
          ),
          phutil_escape_html('A' . $answer->getID() . ' ' .
            self::abbreviate($answer->getContent())
          )
        ).
      '</h2>';

    $rhs =
      '<div class="ponder-metadata">'.
        $title.
        '<span class="ponder-small-metadata">'.
          phutil_escape_html(
            'answer to "'. self::abbreviate($question->getTitle()). '" on ' .
            phabricator_datetime($answer->getDateCreated(), $user)
          ).
        '</span>'.
      '</div>';

    $summary =
      '<div class="ponder-answer-summary">'.
        $votecount.
        $rhs.
      '</div>';

    return $summary;
  }
}
