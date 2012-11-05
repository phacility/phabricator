<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleProxyImage
  extends PhutilRemarkupRule {

  public function apply($text) {

    $filetypes = '\.(?:jpe?g|png|gif)';

    $text = preg_replace_callback(
      '@[<](\w{3,}://.+?'.$filetypes.')[>]@',
      array($this, 'markupProxyImage'),
      $text);

    $text = preg_replace_callback(
      '@(?<=^|\s)(\w{3,}://\S+'.$filetypes.')(?=\s|$)@',
      array($this, 'markupProxyImage'),
      $text);

    return $text;
  }

  public function markupProxyImage($matches) {

    $uri = PhabricatorFileProxyImage::getProxyImageURI($matches[1]);

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href' => $uri,
          'target' => '_blank',
        ),
        phutil_render_tag(
          'img',
          array(
            'src' => $uri,
            'class' => 'remarkup-proxy-image',
          ))));
  }

}
