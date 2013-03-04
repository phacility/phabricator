<?php

final class PonderCommentMail extends PonderMail {

  public function __construct(
    PonderQuestion $question,
    PonderComment $target,
    PhabricatorUser $actor) {

    $this->setQuestion($question);
    $this->setTarget($target);
    $this->setActorHandle($actor);
    $this->setActor($actor);
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
