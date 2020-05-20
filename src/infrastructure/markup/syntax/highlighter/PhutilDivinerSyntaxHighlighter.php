<?php

/**
 * Simple syntax highlighter for the ".diviner" format, which is just Remarkup
 * with a specific ruleset. This should also work alright for Remarkup.
 */
final class PhutilDivinerSyntaxHighlighter extends Phobject {

  private $config = array();
  private $replaceClass;

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getHighlightFuture($source) {
    $source = phutil_escape_html($source);

    // This highlighter isn't perfect but tries to do an okay job at getting
    // some of the basics at least. There's lots of room for improvement.

    $blocks = explode("\n\n", $source);
    foreach ($blocks as $key => $block) {
      if (preg_match('/^[^ ](?! )/m', $block)) {
        $blocks[$key] = $this->highlightBlock($block);
      }
    }
    $source = implode("\n\n", $blocks);

    $source = phutil_safe_html($source);
    return new ImmediateFuture($source);
  }

  private function highlightBlock($source) {
    // Highlight "@{class:...}" links to other documentation pages.
    $source = $this->highlightPattern('/@{([\w@]+?):([^}]+?)}/', $source, 'nc');

    // Highlight "@title", "@group", etc.
    $source = $this->highlightPattern('/^@(\w+)/m', $source, 'k');

    // Highlight bold, italic and monospace.
    $source = $this->highlightPattern('@\\*\\*(.+?)\\*\\*@s', $source, 's');
    $source = $this->highlightPattern('@(?<!:)//(.+?)//@s', $source, 's');
    $source = $this->highlightPattern(
      '@##([\s\S]+?)##|\B`(.+?)`\B@',
      $source,
      's');

    // Highlight stuff that looks like headers.
    $source = $this->highlightPattern('/^=(.*)$/m', $source, 'nv');

    return $source;
  }

  private function highlightPattern($regexp, $source, $class) {
    $this->replaceClass = $class;
    $source = preg_replace_callback(
      $regexp,
      array($this, 'replacePattern'),
      $source);

    return $source;
  }

  public function replacePattern($matches) {

    // NOTE: The goal here is to make sure a <span> never crosses a newline.

    $content = $matches[0];
    $content = explode("\n", $content);
    foreach ($content as $key => $line) {
      $content[$key] =
        '<span class="'.$this->replaceClass.'">'.
          $line.
        '</span>';
    }
    return implode("\n", $content);
  }

}
