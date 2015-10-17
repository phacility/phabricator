<?php

final class PhabricatorSlug extends Phobject {

  public static function normalizeProjectSlug($slug) {
    $slug = str_replace('/', ' ', $slug);
    $slug = self::normalize($slug, $hashtag = true);
    return rtrim($slug, '/');
  }

  public static function normalize($slug, $hashtag = false) {
    $slug = preg_replace('@/+@', '/', $slug);
    $slug = trim($slug, '/');
    $slug = phutil_utf8_strtolower($slug);

    $ban =
      // Ban control characters since users can't type them and they create
      // various other problems with parsing and rendering.
      "\\x00-\\x19".

      // Ban characters with special meanings in URIs (and spaces), since we
      // want slugs to produce nice URIs.
      "#%&+=?".
      " ".

      // Ban backslashes and various brackets for parsing and URI quality.
      "\\\\".
      "<>{}\\[\\]".

      // Ban single and double quotes since they can mess up URIs.
      "'".
      '"';

    // In hashtag mode (used for Project hashtags), ban additional characters
    // which cause parsing problems.
    if ($hashtag) {
      $ban .= '`~!@$^*,:;(|)';
    }

    $slug = preg_replace('(['.$ban.']+)', '_', $slug);
    $slug = preg_replace('@_+@', '_', $slug);

    $parts = explode('/', $slug);

    // Remove leading and trailing underscores from each component, if the
    // component has not been reduced to a single underscore. For example, "a?"
    // converts to "a", but "??" converts to "_".
    foreach ($parts as $key => $part) {
      if ($part != '_') {
        $parts[$key] = trim($part, '_');
      }
    }
    $slug = implode('/', $parts);

    // Specifically rewrite these slugs. It's OK to have a slug like "a..b",
    // but not a slug which is only "..".

    // NOTE: These are explicitly not pht()'d, because they should be stable
    // across languages.

    $replace = array(
      '.'   => 'dot',
      '..'  => 'dotdot',
    );

    foreach ($replace as $pattern => $replacement) {
      $pattern = preg_quote($pattern, '@');
      $slug = preg_replace(
        '@(^|/)'.$pattern.'(\z|/)@',
        '\1'.$replacement.'\2', $slug);
    }

    return $slug.'/';
  }

  public static function getDefaultTitle($slug) {
    $parts = explode('/', trim($slug, '/'));
    $default_title = end($parts);
    $default_title = str_replace('_', ' ', $default_title);
    $default_title = phutil_utf8_ucwords($default_title);
    $default_title = nonempty($default_title, pht('Untitled Document'));
    return $default_title;
  }

  public static function getAncestry($slug) {
    $slug = self::normalize($slug);

    if ($slug == '/') {
      return array();
    }

    $ancestors = array(
      '/',
    );

    $slug = explode('/', $slug);
    array_pop($slug);
    array_pop($slug);

    $accumulate = '';
    foreach ($slug as $part) {
      $accumulate .= $part.'/';
      $ancestors[] = $accumulate;
    }

    return $ancestors;
  }

  public static function getDepth($slug) {
    $slug = self::normalize($slug);
    if ($slug == '/') {
      return 0;
    } else {
      return substr_count($slug, '/');
    }
  }

}
