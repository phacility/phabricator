<?php

final class PhutilRemarkupHyperlinkRule extends PhutilRemarkupRule {

  const KEY_HYPERLINKS = 'hyperlinks';

  public function getPriority() {
    return 400.0;
  }

  public function apply($text) {
    // Hyperlinks with explicit "<>" around them get linked exactly, without
    // the "<>". Angle brackets are basically special and mean "this is a URL
    // with weird characters". This is assumed to be reasonable because they
    // don't appear in normal text or normal URLs.
    $text = preg_replace_callback(
      '@<(\w{3,}://[^\s'.PhutilRemarkupBlockStorage::MAGIC_BYTE.']+?)>@',
      array($this, 'markupHyperlinkAngle'),
      $text);

    // We match "{uri}", but do not link it by default.
    $text = preg_replace_callback(
      '@{(\w{3,}://[^\s'.PhutilRemarkupBlockStorage::MAGIC_BYTE.']+?)}@',
      array($this, 'markupHyperlinkCurly'),
      $text);

    // Anything else we match "ungreedily", which means we'll look for
    // stuff that's probably puncutation or otherwise not part of the URL and
    // not link it. This lets someone write "QuicK! Go to
    // http://www.example.com/!". We also apply some paren balancing rules.

    // NOTE: We're explicitly avoiding capturing stored blocks, so text like
    // `http://www.example.com/[[x | y]]` doesn't get aggressively captured.
    $text = preg_replace_callback(
      '@(\w{3,}://[^\s'.PhutilRemarkupBlockStorage::MAGIC_BYTE.']+)@',
      array($this, 'markupHyperlinkUngreedy'),
      $text);

    return $text;
  }

  public function markupHyperlinkAngle(array $matches) {
    return $this->markupHyperlink('<', $matches);
  }

  public function markupHyperlinkCurly(array $matches) {
    return $this->markupHyperlink('{', $matches);
  }

  protected function markupHyperlink($mode, array $matches) {
    $raw_uri = $matches[1];

    try {
      $uri = new PhutilURI($raw_uri);
    } catch (Exception $ex) {
      return $matches[0];
    }

    $engine = $this->getEngine();

    $token = $engine->storeText($raw_uri);

    $list_key = self::KEY_HYPERLINKS;
    $link_list = $engine->getTextMetadata($list_key, array());

    $link_list[] = array(
      'token' => $token,
      'uri' => $raw_uri,
      'mode' => $mode,
    );

    $engine->setTextMetadata($list_key, $link_list);

    return $token;
  }

  protected function renderHyperlink($link, $is_embed) {
    // If the URI is "{uri}" and no handler picked it up, we just render it
    // as plain text.
    if ($is_embed) {
      return $this->renderRawLink($link, $is_embed);
    }

    $engine = $this->getEngine();

    $same_window = $engine->getConfig('uri.same-window', false);
    if ($same_window) {
      $target = null;
    } else {
      $target = '_blank';
    }

    return phutil_tag(
      'a',
      array(
        'href' => $link,
        'class' => 'remarkup-link',
        'target' => $target,
        'rel' => 'noreferrer',
      ),
      $link);
  }

  private function renderRawLink($link, $is_embed) {
    if ($is_embed) {
      return '{'.$link.'}';
    } else {
      return $link;
    }
  }

  protected function markupHyperlinkUngreedy($matches) {
    $match = $matches[1];
    $tail = null;
    $trailing = null;
    if (preg_match('/[;,.:!?]+$/', $match, $trailing)) {
      $tail = $trailing[0];
      $match = substr($match, 0, -strlen($tail));
    }

    // If there's a closing paren at the end but no balancing open paren in
    // the URL, don't link the close paren. This is an attempt to gracefully
    // handle the two common paren cases, Wikipedia links and English language
    // parentheticals, e.g.:
    //
    //  http://en.wikipedia.org/wiki/Noun_(disambiguation)
    //  (see also http://www.example.com)
    //
    // We could apply a craftier heuristic here which tries to actually balance
    // the parens, but this is probably sufficient.
    if (preg_match('/\\)$/', $match) && !preg_match('/\\(/', $match)) {
      $tail = ')'.$tail;
      $match = substr($match, 0, -1);
    }

    try {
      $uri = new PhutilURI($match);
    } catch (Exception $ex) {
      return $matches[0];
    }

    $link = $this->markupHyperlink(null, array(null, $match));

    return hsprintf('%s%s', $link, $tail);
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $protocols = $engine->getConfig('uri.allowed-protocols', array());
    $is_toc = $engine->getState('toc');
    $is_text = $engine->isTextMode();
    $is_mail = $engine->isHTMLMailMode();

    $list_key = self::KEY_HYPERLINKS;
    $raw_list = $engine->getTextMetadata($list_key, array());

    $links = array();
    foreach ($raw_list as $key => $link) {
      $token = $link['token'];
      $raw_uri = $link['uri'];
      $mode = $link['mode'];

      $is_embed = ($mode === '{');
      $is_literal = ($mode === '<');

      // If we're rendering in a "Table of Contents" or a plain text mode,
      // we're going to render the raw URI without modifications.
      if ($is_toc || $is_text) {
        $result = $this->renderRawLink($raw_uri, $is_embed);
        $engine->overwriteStoredText($token, $result);
        continue;
      }

      // If this URI doesn't use a whitelisted protocol, don't link it. This
      // is primarily intended to prevent "javascript://" silliness.
      $uri = new PhutilURI($raw_uri);
      $protocol = $uri->getProtocol();
      $valid_protocol = idx($protocols, $protocol);
      if (!$valid_protocol) {
        $result = $this->renderRawLink($raw_uri, $is_embed);
        $engine->overwriteStoredText($token, $result);
        continue;
      }

      // If the URI is written as "<uri>", we'll render it literally even if
      // some handler would otherwise deal with it.
      // If we're rendering for HTML mail, we also render literally.
      if ($is_literal || $is_mail) {
        $result = $this->renderHyperlink($raw_uri, $is_embed);
        $engine->overwriteStoredText($token, $result);
        continue;
      }

      // Otherwise, this link is a valid resource which extensions are allowed
      // to handle.
      $links[$key] = $link;
    }

    if (!$links) {
      return;
    }

    foreach ($links as $key => $link) {
      $links[$key] = new PhutilRemarkupHyperlinkRef($link);
    }

    $extensions = PhutilRemarkupHyperlinkEngineExtension::getAllLinkEngines();
    foreach ($extensions as $extension) {
      $extension = id(clone $extension)
        ->setEngine($engine)
        ->processHyperlinks($links);

      foreach ($links as $key => $link) {
        $result = $link->getResult();
        if ($result !== null) {
          $engine->overwriteStoredText($link->getToken(), $result);
          unset($links[$key]);
        }
      }

      if (!$links) {
        break;
      }
    }

    // Render any remaining links in a normal way.
    foreach ($links as $link) {
      $result = $this->renderHyperlink($link->getURI(), $link->isEmbed());
      $engine->overwriteStoredText($link->getToken(), $result);
    }
  }

}
