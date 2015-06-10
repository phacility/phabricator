<?php

final class ReleephReasonFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'reason';
  }

  public function getName() {
    return pht('Reason');
  }

  public function getStorageKey() {
    return 'reason';
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function getIconForPropertyView() {
    return PHUIPropertyListView::ICON_SUMMARY;
  }

  public function renderPropertyViewValue(array $handles) {
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $this->getMarkupEngineOutput());
  }

  private $error = true;

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextAreaControl())
      ->setLabel(pht('Reason'))
      ->setName('reason')
      ->setError($this->error)
      ->setValue($this->getValue());
  }

  public function validate($reason) {
    if (!$reason) {
      $this->error = pht('Required');
      throw new ReleephFieldParseException(
        $this,
        pht('You must give a reason for your request.'));
    }
  }

  public function renderHelpForArcanist() {
    $text = pht(
      'Fully explain why you are requesting this code be included '.
      'in the next release.')."\n";
    return phutil_console_wrap($text, 8);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return pht('Request Reason');
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
