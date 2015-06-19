<?php

final class PhabricatorYoutubeRemarkupRule extends PhutilRemarkupRule {

  private $uri;

  public function getPriority() {
    return 350.0;
  }

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
    $text_mode = $this->getEngine()->isTextMode();
    $mail_mode = $this->getEngine()->isHTMLMailMode();

    if ($text_mode || $mail_mode) {
      return $this->getEngine()->storeText('http://youtu.be/'.$v);
    }

    $youtube_src = 'https://www.youtube.com/embed/'.$v;
    $iframe = $this->newTag(
      'div',
      array(
        'class' => 'embedded-youtube-video',
      ),
      $this->newTag(
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
