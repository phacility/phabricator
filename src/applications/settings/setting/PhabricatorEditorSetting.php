<?php

final class PhabricatorEditorSetting
  extends PhabricatorStringSetting {

  const SETTINGKEY = 'editor';

  public function getSettingName() {
    return pht('Editor Link');
  }

  protected function getControlInstructions() {
    return pht(
      "Many text editors can be configured as URI handlers for special ".
      "protocols like `editor://`. If you have such an editor, Phabricator ".
      "can generate links that you can click to open files locally.".
      "\n\n".
      "These special variables are supported:".
      "\n\n".
      "| Value | Replaced With |\n".
      "|-------|---------------|\n".
      "| `%%f`  | Filename |\n".
      "| `%%l`  | Line Number |\n".
      "| `%%r`  | Repository Callsign |\n".
      "| `%%%%`  | Literal `%%` |\n".
      "\n\n".
      "For complete instructions on editor configuration, ".
      "see **[[ %s | %s ]]**.",
      PhabricatorEnv::getDoclink('User Guide: Configuring an External Editor'),
      pht('User Guide: Configuring an External Editor'));
  }

}
