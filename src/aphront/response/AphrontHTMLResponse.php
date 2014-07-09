<?php

abstract class AphrontHTMLResponse extends AphrontResponse {

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'text/html; charset=UTF-8'),
    );
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
