<?php

final class PhabricatorHelpKeyboardShortcutController
  extends PhabricatorHelpController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $keys = $request->getStr('keys');
    try {
      $keys = phutil_json_decode($keys);
    } catch (PhutilJSONParserException $ex) {
      return new Aphront400Response();
    }

    // There have been at least two users asking for a keyboard shortcut to
    // close the dialog, so be explicit that escape works since it isn't
    // terribly discoverable.
    $keys[] = array(
      'keys'        => array('esc'),
      'description' => pht('Close any dialog, including this one.'),
    );

    $stroke_map = array(
      'left' => "\xE2\x86\x90",
      'right' => "\xE2\x86\x92",
      'up' => "\xE2\x86\x91",
      'down' => "\xE2\x86\x93",
      'return' => "\xE2\x8F\x8E",
      'tab' => "\xE2\x87\xA5",
      'delete' => "\xE2\x8C\xAB",
    );

    $rows = array();
    foreach ($keys as $shortcut) {
      $keystrokes = array();
      foreach ($shortcut['keys'] as $stroke) {
        $stroke = idx($stroke_map, $stroke, $stroke);
        $keystrokes[] = phutil_tag('kbd', array(), $stroke);
      }
      $keystrokes = phutil_implode_html(' or ', $keystrokes);
      $rows[] = phutil_tag(
        'tr',
        array(),
        array(
          phutil_tag('th', array(), $keystrokes),
          phutil_tag('td', array(), $shortcut['description']),
        ));
    }

    $table = phutil_tag(
      'table',
      array('class' => 'keyboard-shortcut-help'),
      $rows);

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Keyboard Shortcuts'))
      ->appendChild($table)
      ->addCancelButton('#', pht('Close'));

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

}
