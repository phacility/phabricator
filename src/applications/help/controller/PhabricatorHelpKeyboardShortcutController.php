<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

    $rows = array();
    foreach ($keys as $shortcut) {
      $keystrokes = array();
      foreach ($shortcut['keys'] as $stroke) {
        $keystrokes[] = '<kbd>'.phutil_escape_html($stroke).'</kbd>';
      }
      $keystrokes = implode(' or ', $keystrokes);
      $rows[] =
        '<tr>'.
          '<th>'.$keystrokes.'</th>'.
          '<td>'.phutil_escape_html($shortcut['description']).'</td>'.
        '</tr>';
    }

    $table =
      '<table class="keyboard-shortcut-help">'.
        implode('', $rows).
      '</table>';

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Keyboard Shortcuts')
      ->appendChild($table)
      ->addCancelButton('#', 'Close');

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

}
