<?php

final class PhabricatorHelpEditorProtocolController
  extends PhabricatorHelpController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    return $this->newDialog()
      ->setMethod('GET')
      ->setSubmitURI('/settings/panel/display/')
      ->setTitle(pht('Unsupported Editor Protocol'))
      ->appendParagraph(
        pht(
          'Your configured editor URI uses an unsupported protocol. Change '.
          'your settings to use a supported protocol, or ask your '.
          'administrator to add support for the chosen protocol by '.
          'configuring: %s',
          phutil_tag('tt', array(), 'uri.allowed-editor-protocols')))
      ->addSubmitButton(pht('Change Settings'))
      ->addCancelButton('/');
  }

  public static function hasAllowedProtocol($uri) {
    $uri = new PhutilURI($uri);
    $editor_protocol = $uri->getProtocol();
    if (!$editor_protocol) {
      // The URI must have a protocol.
      return false;
    }

    $allowed_key = 'uri.allowed-editor-protocols';
    $allowed_protocols = PhabricatorEnv::getEnvConfig($allowed_key);
    if (empty($allowed_protocols[$editor_protocol])) {
      // The protocol must be on the allowed protocol whitelist.
      return false;
    }

    return true;
  }


}
