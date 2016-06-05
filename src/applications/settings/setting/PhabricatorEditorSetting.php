<?php

final class PhabricatorEditorSetting
  extends PhabricatorStringSetting {

  const SETTINGKEY = 'editor';

  public function getSettingName() {
    return pht('Editor Link');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 300;
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

  public function validateTransactionValue($value) {
    if (!strlen($value)) {
      return;
    }

    $ok = PhabricatorHelpEditorProtocolController::hasAllowedProtocol($value);
    if ($ok) {
      return;
    }

    $allowed_key = 'uri.allowed-editor-protocols';
    $allowed_protocols = PhabricatorEnv::getEnvConfig($allowed_key);

    $proto_names = array();
    foreach (array_keys($allowed_protocols) as $protocol) {
      $proto_names[] = $protocol.'://';
    }

    throw new Exception(
      pht(
        'Editor link has an invalid or missing protocol. You must '.
        'use a whitelisted editor protocol from this list: %s. To '.
        'add protocols, update "%s" in Config.',
        implode(', ', $proto_names),
        $allowed_key));
  }

}
