<?php

final class PhabricatorApplicationTransactionTemplatedCommentQuery
  extends PhabricatorApplicationTransactionCommentQuery {

  private $template;

  public function setTemplate(
    PhabricatorApplicationTransactionComment $template) {
    $this->template = $template;
    return $this;
  }

  protected function getTemplate() {
    return $this->template;
  }

}
