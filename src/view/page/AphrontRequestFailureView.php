<?php

final class AphrontRequestFailureView extends AphrontView {

  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }


  final public function render() {
    require_celerity_resource('aphront-request-failure-view-css');

    $head = phutil_tag_div(
      'aphront-request-failure-head',
      phutil_tag('h1', array(), $this->header));

    $body = phutil_tag_div(
      'aphront-request-failure-body',
      $this->renderChildren());

    return phutil_tag_div('aphront-request-failure-view', array($head, $body));
  }

}
