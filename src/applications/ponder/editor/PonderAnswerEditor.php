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

final class PonderAnswerEditor extends PhabricatorEditor {

  private $question;
  private $answer;
  private $shouldEmail = true;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setAnswer($answer) {
    $this->answer = $answer;
    return $this;
  }

  public function saveAnswer() {
    $actor = $this->requireActor();
    if (!$this->question) {
      throw new Exception("Must set question before saving answer");
    }
    if (!$this->answer) {
      throw new Exception("Must set answer before saving it");
    }

    $question = $this->question;
    $answer = $this->answer;
    $conn = $answer->establishConnection('w');
    $trans = $conn->openTransaction();
    $trans->beginReadLocking();

      $question->reload();

      queryfx($conn,
        'UPDATE %T as t
        SET t.`answerCount` = t.`answerCount` + 1
        WHERE t.`PHID` = %s',
        $question->getTableName(),
        $question->getPHID());

      $answer->setQuestionID($question->getID());
      $answer->save();

    $trans->endReadLocking();
    $trans->saveTransaction();

    $question->attachRelated();
    PhabricatorSearchPonderIndexer::indexQuestion($question);

    // subscribe author and @mentions
    $subeditor = id(new PhabricatorSubscriptionsEditor())
      ->setObject($question)
      ->setActor($actor);

    $subeditor->subscribeExplicit(array($answer->getAuthorPHID()));

    $content = $answer->getContent();
    $at_mention_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      array($content)
    );
    $subeditor->subscribeImplicit($at_mention_phids);
    $subeditor->save();

    if ($this->shouldEmail) {
      // now load subscribers, including implicitly-added @mention victims
      $subscribers = PhabricatorSubscribersQuery
        ::loadSubscribersForPHID($question->getPHID());


      // @mention emails (but not for anyone who has explicitly unsubscribed)
      if (array_intersect($at_mention_phids, $subscribers)) {
        id(new PonderMentionMail(
          $question,
          $answer,
          $actor))
          ->setToPHIDs($at_mention_phids)
          ->send();
      }

      $other_subs =
        array_diff(
          $subscribers,
          $at_mention_phids
        );

      // 'Answered' emails for subscribers who are not @mentiond (and excluding
      // author depending on their MetaMTA settings).
      if ($other_subs) {
        id(new PonderAnsweredMail(
          $question,
          $answer,
          $actor))
          ->setToPHIDs($other_subs)
          ->send();
      }
    }
  }
}
