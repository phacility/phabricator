<?php

final class PhabricatorHelpKeyboardShortcutController
  extends PhabricatorHelpController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $keys = $request->getStr('keys');
    $keys = json_decode($keys, true);
    if (!is_array($keys)) {
      return new Aphront400Response();
    }

    // There have been at least two users asking for a keyboard shortcut to
    // close the dialog, so be explicit that escape works since it isn't
    // terribly discoverable.
    $keys[] = array(
      'keys'        => array('esc'),
      'description' => 'Close any dialog, including this one.',
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
      ->setTitle('Keyboard Shortcuts')
      ->appendChild($table)
      ->addCancelButton('#', 'Close');

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

}
