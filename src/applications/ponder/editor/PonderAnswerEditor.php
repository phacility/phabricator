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


final class PonderAnswerEditor {

  private $question;
  private $answer;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setAnswer($answer) {
    $this->answer = $answer;
    return $this;
  }

  public function saveAnswer() {
    if (!$this->question) {
      throw new Exception("Must set question before saving vote");
    }
    if (!$this->answer) {
      throw new Exception("Must set answer before saving vote");
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
  }
}
