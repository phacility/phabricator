<?php

final class AphrontKeyboardShortcutsAvailableView extends AphrontView {

  public function render() {
    return phutil_tag(
      'div',
      array(
        'class' => 'keyboard-shortcuts-available',
      ),
      pht(
        'Press %s to show keyboard shortcuts.',
        phutil_tag('strong', array(), '?')));
  }

}
