<?php

final class DifferentialCCWelcomeMail extends DifferentialReviewRequestMail {

  protected function renderVaryPrefix() {
    return '[Added to CC]';
  }

  protected function renderBody() {

    $actor = $this->getActorName();
    $name  = $this->getRevision()->getTitle();
    $body = array();

    $body[] = "{$actor} added you to the CC list for the revision \"{$name}\".";
    $body[] = null;

    $body[] = $this->renderReviewRequestBody();

    return implode("\n", $body);
  }
}
