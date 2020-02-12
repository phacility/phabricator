<?php

/**
 * Simple syntax highlighter for console output. We just try to highlight the
 * commands so it's easier to follow transcripts.
 */
final class PhutilConsoleSyntaxHighlighter extends Phobject {

  private $config = array();

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getHighlightFuture($source) {
    $in_command = false;
    $lines = explode("\n", $source);
    foreach ($lines as $key => $line) {
      $matches = null;

      // Parse commands like this:
      //
      //   some/path/ $ ./bin/example # Do things
      //
      // ...into path, command, and comment components.

      $pattern =
        '@'.
        ($in_command ? '()(.*?)' : '^(\S+[\\\\/] )?([$] .*?)').
        '(#.*|\\\\)?$@';

      if (preg_match($pattern, $line, $matches)) {
        $lines[$key] = hsprintf(
          '%s<span class="gp">%s</span>%s',
          $matches[1],
          $matches[2],
          (!empty($matches[3])
            ? hsprintf('<span class="k">%s</span>', $matches[3])
            : ''));
        $in_command = (idx($matches, 3) == '\\');
      } else {
        $lines[$key] = hsprintf('<span class="go">%s</span>', $line);
      }
    }
    $lines = phutil_implode_html("\n", $lines);

    return new ImmediateFuture($lines);
  }

}
