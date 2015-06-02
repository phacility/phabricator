<?php

final class ReleephSummaryFieldSpecification
  extends ReleephFieldSpecification {

  const MAX_SUMMARY_LENGTH = 60;

  public function shouldAppearInPropertyView() {
    return false;
  }

  public function getFieldKey() {
    return 'summary';
  }

  public function getName() {
    return 'Summary';
  }

  public function getStorageKey() {
    return 'summary';
  }

  private $error = false;

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setLabel('Summary')
      ->setName('summary')
      ->setError($this->error)
      ->setValue($this->getValue())
      ->setCaption(pht('Leave this blank to use the original commit title'));
  }

  public function renderHelpForArcanist() {
    $text = pht(
      'A one-line title summarizing this request. '.
      'Leave blank to use the original commit title.')."\n";
    return phutil_console_wrap($text, 8);
  }

  public function validate($summary) {
    if ($summary && strlen($summary) > self::MAX_SUMMARY_LENGTH) {
      $this->error = pht('Too long!');
      throw new ReleephFieldParseException(
        $this,
        pht(
          'Please keep your summary to under %d characters.',
          self::MAX_SUMMARY_LENGTH));
    }
  }

}
