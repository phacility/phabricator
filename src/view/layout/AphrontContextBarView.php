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

    return phutil_tag_div(
      'aphront-contextbar-view',
      array(
        phutil_tag_div('aphront-contextbar-core', array(
          phutil_tag_div('aphront-contextbar-buttons', $view->render()),
          phutil_tag_div('aphront-contextbar-content', $this->renderChildren()),
        )),
        phutil_tag('div', array('style' => 'clear: both;')),
      ));
  }

}
