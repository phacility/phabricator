<?php

final class PhutilDefaultSyntaxHighlighterEnginePygmentsFuture
  extends FutureProxy {

  private $source;
  private $scrub;

  public function __construct(Future $proxied, $source, $scrub = false) {
    parent::__construct($proxied);
    $this->source = $source;
    $this->scrub = $scrub;
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    if (!$status->isError() && strlen($body)) {
      // Strip off fluff Pygments adds.
      $body = preg_replace(
        '@^<div class="highlight"><pre>(.*)</pre></div>\s*$@s',
        '\1',
        $body);
      if ($this->scrub) {
        $body = preg_replace('/^.*\n/', '', $body);
      }
      return phutil_safe_html($body);
    }

    throw new PhutilSyntaxHighlighterException($body, $status->getStatusCode());
  }

}
