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
          '<h1>'.phutil_escape_html($this->header).'</h1>'.
        '</div>'.
        '<div class="aphront-request-failure-body">'.
          $this->renderChildren().
        '</div>'.
      '</div>';
  }

}
