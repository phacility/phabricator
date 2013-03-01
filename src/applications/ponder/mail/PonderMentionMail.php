<?php

final class PonderMentionMail extends PonderMail {

  public function __construct(
    PonderQuestion $question,
    $target,
    PhabricatorUser $actor) {

    $this->setQuestion($question);
    $this->setTarget($target);
    $this->setActorHandle($actor);
    $this->setActor($actor);
  }

  protected function renderVaryPrefix() {
    return "[Mentioned]";
  }

  protected function renderBody() {
    $question = $this->getQuestion();
    $target = $this->getTarget();
    $actor = $this->getActorName();
    $name  = $question->getTitle();

    $targetkind = "somewhere";
    if ($target instanceof PonderQuestion) {
      $targetkind = "in a question";
    } else if ($target instanceof PonderAnswer) {
      $targetkind = "in an answer";
    } else if ($target instanceof PonderComment) {
      $targetkind = "in a comment";
    }

    $body = array();
    $body[] = "{$actor} mentioned you {$targetkind}.";
    $body[] = null;

    $content = $target->getContent();
    if (strlen($content)) {
      $body[] = $this->formatText($content);
      $body[] = null;
    }

    return implode("\n", $body);
  }
}
