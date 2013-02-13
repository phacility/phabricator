<?php

final class AphrontMiniPanelView extends AphrontView {

  public function render() {
    return
      '<div class="aphront-mini-panel-view">'.
        $this->renderChildren().
      '</div>';
  }

}
