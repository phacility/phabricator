<?php

final class PhabricatorSlug {

  public static function normalize($slug) {
    $slug = preg_replace('@/+@', '/', $slug);
    $slug = trim($slug, '/');
    $slug = phutil_utf8_strtolower($slug);
    $slug = preg_replace("@[\\x00-\\x19#%&+=\\\\?<> ]+@", '_', $slug);
    $slug = preg_replace('@_+@', '_', $slug);
    $slug = trim($slug, '_');

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
