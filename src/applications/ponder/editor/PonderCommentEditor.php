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


final class PonderCommentEditor {

  private $question;
  private $comment;

  public function setComment(PonderComment $comment) {
    $this->comment = $comment;
    return $this;
  }

  public function setQuestion(PonderQuestion $question) {
    $this->question = $question;
    return $this;
  }

  public function save() {
    if (!$this->comment) {
      throw new Exception("Must set comment before saving it");
    }
    if (!$this->question) {
      throw new Exception("Must set question before saving comment");
    }

    $comment  = $this->comment;
    $question = $this->question;

    $comment->save();

    $question->attachRelated();
    PhabricatorSearchPonderIndexer::indexQuestion($question);
  }
}
