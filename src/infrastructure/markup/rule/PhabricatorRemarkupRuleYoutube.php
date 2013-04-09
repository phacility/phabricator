<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleYoutube
  extends PhutilRemarkupRule {

  public function apply($text) {
    $this->uri = new PhutilURI($text);

    if ($this->uri->getDomain() &&
        preg_match('/(^|\.)youtube\.com$/', $this->uri->getDomain()) &&
        idx($this->uri->getQueryParams(), 'v')) {
      return $this->markupYoutubeLink();
    }

    return $text;
  }

  public function markupYoutubeLink() {
    $v = idx($this->uri->getQueryParams(), 'v');

    if ($this->getEngine()->isTextMode()) {
      return $this->getEngine()->storeText('http://youtu.be/'.$v);
    }

    $youtube_src = 'https://www.youtube.com/embed/'.$v;
    $iframe = hsprintf(
      '<div class="embedded-youtube-video">%s</div>',
      phutil_tag(
        'iframe',
        array(
          'width'       => '650',
          'height'      => '400',
          'style'       => 'margin: 1em auto; border: 0px;',
          'src'         => $youtube_src,
          'frameborder' => 0,
        ),
        ''));
    return $this->getEngine()->storeText($iframe);
  }

}
