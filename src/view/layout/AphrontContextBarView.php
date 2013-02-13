<?php

final class AphrontContextBarView extends AphrontView {

  protected $buttons = array();

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->buttons);

    require_celerity_resource('aphront-contextbar-view-css');

    return hsprintf(
      '<div class="aphront-contextbar-view">'.
        '<div class="aphront-contextbar-core">'.
          '<div class="aphront-contextbar-buttons">%s</div>'.
          '<div class="aphront-contextbar-content">%s</div>'.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>',
      $view->render(),
      $this->renderChildren());
  }

}
