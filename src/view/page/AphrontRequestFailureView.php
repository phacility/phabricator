<?php

final class AphrontRequestFailureView extends AphrontView {

  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }


  final public function render() {
    require_celerity_resource('aphront-request-failure-view-css');

    return
      '<div class="aphront-request-failure-view">'.
        '<div class="aphront-request-failure-head">'.
          phutil_tag('h1', array(), $this->header).
        '</div>'.
        '<div class="aphront-request-failure-body">'.
          $this->renderChildren().
        '</div>'.
      '</div>';
  }

}
