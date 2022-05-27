<?php

final class PhabricatorEditorSetting
  extends PhabricatorStringSetting {

  const SETTINGKEY = 'editor';

  public function getSettingName() {
    return pht('Editor Link');
  }

  public function getSettingPanelKey() {
    return PhabricatorExternalEditorSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 300;
  }

  protected function getControlInstructions() {
    return pht(
      "Many text editors can be configured as URI handlers for special ".
      "protocols like `editor://`. If you have installed and configured ".
      "such an editor, some applications can generate links that you can ".
      "click to open files locally.".
      "\n\n".
      "Provide a URI pattern for building external editor URIs in your ".
      "environment. For example, if you use TextMate on macOS, the pattern ".
      "for your machine may look something like this:".
      "\n\n".
      "```name=\"Example: TextMate on macOS\"\n".
      "%s\n".
      "```\n".
      "\n\n".
      "For complete instructions on editor configuration, ".
      "see **[[ %s | %s ]]**.".
      "\n\n".
      "See the tables below for a list of supported variables and protocols.",
      'txmt://open/?url=file:///Users/alincoln/editor_links/%n/%f&line=%l',
      PhabricatorEnv::getDoclink('User Guide: Configuring an External Editor'),
      pht('User Guide: Configuring an External Editor'));
  }

  public function validateTransactionValue($value) {
    if (!phutil_nonempty_string($value)) {
      return;
    }

    id(new PhabricatorEditorURIEngine())
      ->setPattern($value)
      ->validatePattern();
  }

}
