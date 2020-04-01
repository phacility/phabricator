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
    list($err, $stdout, $stderr) = $result;

    if (!$err && strlen($stdout)) {
      // Strip off fluff Pygments adds.
      $stdout = preg_replace(
        '@^<div class="highlight"><pre>(.*)</pre></div>\s*$@s',
        '\1',
        $stdout);
      if ($this->scrub) {
        $stdout = preg_replace('/^.*\n/', '', $stdout);
      }
      return phutil_safe_html($stdout);
    }

    throw new PhutilSyntaxHighlighterException($stderr, $err);
  }

}
