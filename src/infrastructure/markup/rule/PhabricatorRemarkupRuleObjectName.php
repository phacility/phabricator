<?php

/**
 * @group markup
 */
abstract class PhabricatorRemarkupRuleObjectName
  extends PhutilRemarkupRule {

  abstract protected function getObjectNamePrefix();

  public function apply($text) {
    $prefix = $this->getObjectNamePrefix();
    return preg_replace_callback(
      "@\b({$prefix})([1-9]\d*)(?:#([-\w\d]+))?\b@",
      array($this, 'markupObjectNameLink'),
      $text);
  }

  public function markupObjectNameLink($matches) {
    list(, $prefix, $id) = $matches;

    if (isset($matches[3])) {
      $href = $matches[3];
      $text = $matches[3];
      if (preg_match('@^(?:comment-)?(\d{1,7})$@', $href, $matches)) {
        // Maximum length is 7 because 12345678 could be a file hash.
        $href = "comment-{$matches[1]}";
        $text = $matches[1];
      }
      $href = "/{$prefix}{$id}#{$href}";
      $text = "{$prefix}{$id}#{$text}";
    } else {
      $href = "/{$prefix}{$id}";
      $text = "{$prefix}{$id}";
    }

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href' => $href,
        ),
        $text));
  }

}
