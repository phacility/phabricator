<?php

/**
 * Render an HTML tag in a way that treats user content as unsafe by default.
 *
 * Tag rendering has some special logic which implements security features:
 *
 *   - When rendering `<a>` tags, if the `rel` attribute is not specified, it
 *     is interpreted as `rel="noreferrer"`.
 *   - When rendering `<a>` tags, the `href` attribute may not begin with
 *     `javascript:`.
 *
 * These special cases can not be disabled.
 *
 * IMPORTANT: The `$tag` attribute and the keys of the `$attributes` array are
 * trusted blindly, and not escaped. You should not pass user data in these
 * parameters.
 *
 * @param string The name of the tag, like `a` or `div`.
 * @param map<string, string> A map of tag attributes.
 * @param wild Content to put in the tag.
 * @return PhutilSafeHTML Tag object.
 */
function phutil_tag($tag, array $attributes = array(), $content = null) {
  // If the `href` attribute is present, make sure it is not a "javascript:"
  // URI. We never permit these.
  if (!empty($attributes['href'])) {
    // This might be a URI object, so cast it to a string.
    $href = (string)$attributes['href'];

    if (isset($href[0])) {
      // Block 'javascript:' hrefs at the tag level: no well-designed
      // application should ever use them, and they are a potent attack vector.

      // This function is deep in the core and performance sensitive, so we're
      // doing a cheap version of this test first to avoid calling preg_match()
      // on URIs which begin with '/' or `#`. These cover essentially all URIs
      // in Phabricator.
      if (($href[0] !== '/') && ($href[0] !== '#')) {
        // Chrome 33 and IE 11 both interpret "javascript\n:" as a Javascript
        // URI, and all browsers interpret "  javascript:" as a Javascript URI,
        // so be aggressive about looking for "javascript:" in the initial
        // section of the string.

        $normalized_href = preg_replace('([^a-z0-9/:]+)i', '', $href);
        if (preg_match('/^javascript:/i', $normalized_href)) {
          throw new Exception(
            pht(
              "Attempting to render a tag with an '%s' attribute that begins ".
              "with '%s'. This is either a serious security concern or a ".
              "serious architecture concern. Seek urgent remedy.",
              'href',
              'javascript:'));
        }
      }
    }
  }

  // For tags which can't self-close, treat null as the empty string -- for
  // example, always render `<div></div>`, never `<div />`.
  static $self_closing_tags = array(
    'area'    => true,
    'base'    => true,
    'br'      => true,
    'col'     => true,
    'command' => true,
    'embed'   => true,
    'frame'   => true,
    'hr'      => true,
    'img'     => true,
    'input'   => true,
    'keygen'  => true,
    'link'    => true,
    'meta'    => true,
    'param'   => true,
    'source'  => true,
    'track'   => true,
    'wbr'     => true,
  );

  $attr_string = '';
  foreach ($attributes as $k => $v) {
    if ($v === null) {
      continue;
    }
    $v = phutil_escape_html($v);
    $attr_string .= ' '.$k.'="'.$v.'"';
  }

  if ($content === null) {
    if (isset($self_closing_tags[$tag])) {
      return new PhutilSafeHTML('<'.$tag.$attr_string.' />');
    } else {
      $content = '';
    }
  } else {
    $content = phutil_escape_html($content);
  }

  return new PhutilSafeHTML('<'.$tag.$attr_string.'>'.$content.'</'.$tag.'>');
}

function phutil_tag_div($class, $content = null) {
  return phutil_tag('div', array('class' => $class), $content);
}

function phutil_escape_html($string) {
  if ($string === null) {
    return '';
  }

  if ($string instanceof PhutilSafeHTML) {
    return $string;
  } else if ($string instanceof PhutilSafeHTMLProducerInterface) {
    $result = $string->producePhutilSafeHTML();
    if ($result instanceof PhutilSafeHTML) {
      return phutil_escape_html($result);
    } else if (is_array($result)) {
      return phutil_escape_html($result);
    } else if ($result instanceof PhutilSafeHTMLProducerInterface) {
      return phutil_escape_html($result);
    } else {
      try {
        assert_stringlike($result);
        return phutil_escape_html((string)$result);
      } catch (Exception $ex) {
        throw new Exception(
          pht(
            "Object (of class '%s') implements %s but did not return anything ".
            "renderable from %s.",
            get_class($string),
            'PhutilSafeHTMLProducerInterface',
            'producePhutilSafeHTML()'));
      }
    }
  } else if (is_array($string)) {
    $result = '';
    foreach ($string as $item) {
      $result .= phutil_escape_html($item);
    }
    return $result;
  }

  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function phutil_escape_html_newlines($string) {
  return PhutilSafeHTML::applyFunction('nl2br', $string);
}

/**
 * Mark string as safe for use in HTML.
 */
function phutil_safe_html($string) {
  if ($string == '') {
    return $string;
  } else if ($string instanceof PhutilSafeHTML) {
    return $string;
  } else {
    return new PhutilSafeHTML($string);
  }
}

/**
 * HTML safe version of `implode()`.
 */
function phutil_implode_html($glue, array $pieces) {
  $glue = phutil_escape_html($glue);

  foreach ($pieces as $k => $piece) {
    $pieces[$k] = phutil_escape_html($piece);
  }

  return phutil_safe_html(implode($glue, $pieces));
}

/**
 * Format a HTML code. This function behaves like `sprintf()`, except that all
 * the normal conversions (like %s) will be properly escaped.
 */
function hsprintf($html /* , ... */) {
  $args = func_get_args();
  array_shift($args);
  return new PhutilSafeHTML(
    vsprintf($html, array_map('phutil_escape_html', $args)));
}

