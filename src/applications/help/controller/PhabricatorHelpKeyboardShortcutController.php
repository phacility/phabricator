<?php

final class PhabricatorHelpKeyboardShortcutController
  extends PhabricatorHelpController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

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
      'keys' => array('Esc'),
      'description' => pht('Close any dialog, including this one.'),
      'group' => 'global',
    );

    $groups = array(
      'default' => array(
        'name' => pht('Page Shortcuts'),
        'icon' => 'fa-keyboard-o',
      ),
      'diff-nav' => array(
        'name' => pht('Diff Navigation'),
        'icon' => 'fa-arrows',
      ),
      'diff-vis' => array(
        'name' => pht('Hiding Content'),
        'icon' => 'fa-eye-slash',
      ),
      'inline' => array(
        'name' => pht('Editing Inline Comments'),
        'icon' => 'fa-pencil',
      ),
      'xactions' => array(
        'name' => pht('Comments'),
        'icon' => 'fa-comments-o',
      ),
      'global' => array(
        'name' => pht('Global Shortcuts'),
        'icon' => 'fa-globe',
      ),
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

    $row_maps = array();
    foreach ($keys as $shortcut) {
      $keystrokes = array();
      foreach ($shortcut['keys'] as $stroke) {
        $stroke = idx($stroke_map, $stroke, $stroke);
        $keystrokes[] = phutil_tag(
          'span',
          array(
            'class' => 'keyboard-shortcut-key',
          ),
          $stroke);
      }
      $keystrokes = phutil_implode_html(' or ', $keystrokes);

      $group_key = idx($shortcut, 'group');
      if (!isset($groups[$group_key])) {
        $group_key = 'default';
      }

      $row = phutil_tag(
        'tr',
        array(),
        array(
          phutil_tag('th', array(), $keystrokes),
          phutil_tag('td', array(), $shortcut['description']),
        ));

      $row_maps[$group_key][] = $row;
    }

    $tab_group = id(new PHUITabGroupView())
      ->setVertical(true);

    foreach ($groups as $key => $group) {
      $rows = idx($row_maps, $key);
      if (!$rows) {
        continue;
      }

      $icon = id(new PHUIIconView())
        ->setIcon($group['icon']);

      $tab = id(new PHUITabView())
        ->setKey($key)
        ->setName($group['name'])
        ->setIcon($icon);

      $table = phutil_tag(
        'table',
        array('class' => 'keyboard-shortcut-help'),
        $rows);

      $tab->appendChild($table);

      $tab_group->addTab($tab);
    }

    return $this->newDialog()
      ->setTitle(pht('Keyboard Shortcuts'))
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setFlush(true)
      ->appendChild($tab_group)
      ->addCancelButton('#', pht('Close'));

  }

}
