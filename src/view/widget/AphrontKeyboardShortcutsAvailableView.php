<?php

final class AphrontKeyboardShortcutsAvailableView extends AphrontView {

  public function render() {
    return
      '<div class="keyboard-shortcuts-available">'.
        'Press <strong>?</strong> to show keyboard shortcuts.'.
      '</div>';
  }

}
