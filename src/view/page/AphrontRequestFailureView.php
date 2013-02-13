<?php

final class AphrontRequestFailureView extends AphrontView {

  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }


  final public function render() {
    require_celerity_resource('aphront-request-failure-view-css');

    return hsprintf(
      '<div class="aphront-request-failure-view">'.
        '<div class="aphront-request-failure-head">'.
          '<h1>%s</h1>'.
        '</div>'.
        '<div class="aphront-request-failure-body">%s</div>'.
      '</div>',
      $this->header,
      $this->renderChildren());
  }

}
