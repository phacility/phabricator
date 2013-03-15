<?php

final class ReleephRiskFieldSpecification
  extends ReleephFieldSpecification {

  static $defaultRisks = array(
    'NONE'  => 'Completely safe to pick this request.',
    'SOME'  => 'There is some risk this could break things, but not much.',
    'HIGH'  => 'This is pretty risky, but is also very important.',
  );

  public function getName() {
    return 'Riskiness';
  }

  public function getStorageKey() {
    return 'risk';
  }

  public function renderLabelForHeaderView() {
    return 'Riskiness';
  }

  private $error = true;

  public function renderEditControl(AphrontRequest $request) {
    $value = $request->getStr('risk', $this->getValue());
    $buttons = id(new AphrontFormRadioButtonControl())
      ->setLabel('Riskiness')
      ->setName('risk')
      ->setError($this->error)
      ->setValue($value);
    foreach (self::$defaultRisks as $value => $description) {
      $buttons->addButton($value, $value, $description);
    }
    return $buttons;
  }

  public function validate($risk) {
    if (!$risk) {
      $this->error = 'Required';
      throw new ReleephFieldParseException(
        $this,
        "No risk was given, which probably means we've changed the set ".
        "of valid risks since you made this request.  Please pick one.");
    }
    if (!idx(self::$defaultRisks, $risk)) {
      throw new ReleephFieldParseException(
        $this,
        "Unknown risk '{$risk}'.");
    }
  }

  public function renderHelpForArcanist() {
    $help = '';
    foreach (self::$defaultRisks as $name => $description) {
      $help .= "      **{$name}**\n";
      $help .= phutil_console_wrap($description."\n", 8);
    }
    return $help;
  }

}
