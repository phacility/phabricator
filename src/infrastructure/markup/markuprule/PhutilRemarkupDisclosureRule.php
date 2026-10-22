<?php

final class PhutilRemarkupDisclosureRule extends PhutilRemarkupRule {

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    // Tags to match in text and what the tag should look like after
    // HTML sanitization. Altering the left margin creates indentation.
    $replacements = array(
      '<details>' => hsprintf('<details style="margin-left: 2em">'),
      '</details>' => hsprintf('</details>'),
      '<summary>' => hsprintf('<summary style="margin-left: -2em">'),
      '</summary>' => hsprintf('</summary>'),
    );

    // Sanitize text and replace each sanitized tag with it's
    // corresponding replacement text.
    foreach ($replacements as $match => $replacement) {
      $text = PhutilSafeHTML::applyFunction(
        'preg_replace',
        hsprintf('@\s?%s\s?@s', $match),
        $replacement,
        $text);
    }

    return $text;
  }

}
