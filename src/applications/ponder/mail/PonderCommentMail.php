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

final class PonderCommentMail extends PonderMail {

  public function __construct(
    PonderQuestion $question,
    PonderComment $target,
    PhabricatorUser $actor) {

    $this->setQuestion($question);
    $this->setTarget($target);
    $this->setActorHandle($actor);
  }

  protected function renderVaryPrefix() {
    return "[Commented]";
  }

  protected function renderBody() {
    $question = $this->getQuestion();
    $target = $this->getTarget();
    $actor = $this->getActorName();
    $name  = $question->getTitle();

    $body = array();
    $body[] = "{$actor} commented on a question that you are subscribed to.";
    $body[] = null;

    $content = $target->getContent();
    if (strlen($content)) {
      $body[] = $this->formatText($content);
      $body[] = null;
    }

    return implode("\n", $body);
  }
}
