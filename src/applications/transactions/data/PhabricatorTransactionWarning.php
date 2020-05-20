<?php

final class PhabricatorTransactionWarning
  extends Phobject {

  private $titleText;
  private $continueActionText;
  private $cancelActionText;
  private $warningParagraphs = array();

  public function setTitleText($title_text) {
    $this->titleText = $title_text;
    return $this;
  }

  public function getTitleText() {
    return $this->titleText;
  }

  public function setContinueActionText($continue_action_text) {
    $this->continueActionText = $continue_action_text;
    return $this;
  }

  public function getContinueActionText() {
    return $this->continueActionText;
  }

  public function setCancelActionText($cancel_action_text) {
    $this->cancelActionText = $cancel_action_text;
    return $this;
  }

  public function getCancelActionText() {
    return $this->cancelActionText;
  }

  public function setWarningParagraphs(array $warning_paragraphs) {
    $this->warningParagraphs = $warning_paragraphs;
    return $this;
  }

  public function getWarningParagraphs() {
    return $this->warningParagraphs;
  }

}
