<?php

final class ReleephReasonFieldSpecification
  extends ReleephFieldSpecification {

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
    $reason = $this->getValue();
    if (!$reason) {
      return '';
    }

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
    $engine->setConfig('viewer', $this->getUser());
    $markup = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $engine->markupText($reason));

    return id(new AphrontNoteView())
      ->setTitle('Reason')
      ->appendChild($markup)
      ->render();
  }

  private $error = true;

  public function renderEditControl(AphrontRequest $request) {
    $reason = $request->getStr('reason', $this->getValue());
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Reason')
      ->setName('reason')
      ->setError($this->error)
      ->setValue($reason);
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

}
