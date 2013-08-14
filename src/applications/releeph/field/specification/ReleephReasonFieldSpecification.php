<?php

final class ReleephReasonFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'reason';
  }

  public function getName() {
    return 'Reason';
  }

  public function getStorageKey() {
    return 'reason';
  }

  public function renderLabelForHeaderView() {
    return null;
  }

  public function renderValueForHeaderView() {
    $markup = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $this->getMarkupEngineOutput());

    return id(new AphrontNoteView())
      ->setTitle('Reason')
      ->appendChild($markup)
      ->render();
  }

  private $error = true;

  public function renderEditControl() {
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Reason')
      ->setName('reason')
      ->setError($this->error)
      ->setValue($this->getValue());
  }

  public function validate($reason) {
    if (!$reason) {
      $this->error = 'Required';
      throw new ReleephFieldParseException(
        $this,
        "You must give a reason for your request.");
    }
  }

  public function renderHelpForArcanist() {
    $text =
      "Fully explain why you are requesting this code be included ".
      "in the next release.\n";
    return phutil_console_wrap($text, 8);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Request Reason';
  }

  public function renderValueForCommitMessage() {
    return $this->getValue();
  }

  public function shouldMarkup() {
    return true;
  }

  public function getMarkupText($field) {
    $reason = $this->getValue();
    if ($reason) {
      return $reason;
    } else {
      return '';
    }
  }

}
