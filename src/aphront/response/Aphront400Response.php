<?php

final class Aphront400Response extends AphrontResponse {

  public function getHTTPResponseCode() {
    return 400;
  }

  public function buildResponseString() {
    return '400 Bad Request';
  }

}
