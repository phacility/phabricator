<?php

final class PhabricatorYoutubeRemarkupRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 350.0;
  }

  public function apply($text) {
    try {
      $uri = new PhutilURI($text);
    } catch (Exception $ex) {
      return $text;
    }

    $domain = $uri->getDomain();
    if (!preg_match('/(^|\.)youtube\.com\z/', $domain)) {
      return $text;
    }

    $v_params = array();

    $params = $uri->getQueryParamsAsPairList();
    foreach ($params as $pair) {
      list($k, $v) = $pair;
      if ($k === 'v') {
        $v_params[] = $v;
      }
    }

    if (count($v_params) !== 1) {
      return $text;
    }

    $v_param = head($v_params);

    $text_mode = $this->getEngine()->isTextMode();
    $mail_mode = $this->getEngine()->isHTMLMailMode();

    if ($text_mode || $mail_mode) {
      return $text;
    }

    $youtube_src = 'https://www.youtube.com/embed/'.$v_param;

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

  public function didMarkupText() {
    CelerityAPI::getStaticResourceResponse()
      ->addContentSecurityPolicyURI('frame-src', 'https://www.youtube.com/');
  }

}
