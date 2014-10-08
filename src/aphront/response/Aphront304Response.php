<?php

final class Aphront304Response extends AphrontResponse {

  public function getHTTPResponseCode() {
    return 304;
  }

  public function buildResponseString() {
    // IMPORTANT! According to the HTTP/1.1 spec (RFC 2616) a 304 response
    // "MUST NOT" have any content. Apache + Safari strongly agree, and
    // completely flip out and you start getting 304s for no-cache pages.
    return null;
  }

}
