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

final class PonderQuestionEditor extends PhabricatorEditor {

  private $question;
  private $shouldEmail = true;

  public function setQuestion(PonderQuestion $question) {
    $this->question = $question;
    return $this;
  }

  public function setShouldEmail($se) {
    $this->shouldEmail = $se;
    return $this;
  }

  public function save() {
    $actor = $this->requireActor();
    if (!$this->question) {
      throw new Exception("Must set question before saving it");
    }

    $question = $this->question;
    $question->save();

    // search index
    $question->attachRelated();
    PhabricatorSearchPonderIndexer::indexQuestion($question);

    // subscribe author and @mentions
    $subeditor = id(new PhabricatorSubscriptionsEditor())
      ->setObject($question)
      ->setActor($actor);

    $subeditor->subscribeExplicit(array($question->getAuthorPHID()));

    $content = $question->getContent();
    $at_mention_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      array($content)
    );
    $subeditor->subscribeImplicit($at_mention_phids);
    $subeditor->save();

    if ($this->shouldEmail && $at_mention_phids) {
      id(new PonderMentionMail(
         $question,
         $question,
         $actor))
        ->setToPHIDs($at_mention_phids)
        ->send();
    }
  }
}
