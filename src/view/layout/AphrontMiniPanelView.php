<?php

final class AphrontMiniPanelView extends AphrontView {

  public function render() {
    return hsprintf(
      '<div class="aphront-mini-panel-view">%s</div>',
      $this->renderChildren());
  }

}
