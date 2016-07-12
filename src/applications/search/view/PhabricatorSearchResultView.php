<?php

final class PhabricatorSearchResultView extends AphrontView {

  private $handle;
  private $query;
  private $object;

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setQuery(PhabricatorSavedQuery $query) {
    $this->query = $query;
    return $this;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function render() {
    $handle = $this->handle;
    if (!$handle->isComplete()) {
      return;
    }

    require_celerity_resource('phabricator-search-results-css');

    $type_name = nonempty($handle->getTypeName(), pht('Document'));

    $raw_title = $handle->getFullName();
    $title = $this->emboldenQuery($raw_title);

    $item = id(new PHUIObjectItemView())
      ->setHeader($title)
      ->setTitleText($raw_title)
      ->setHref($handle->getURI())
      ->setImageURI($handle->getImageURI())
      ->addAttribute($type_name);

    if ($handle->getStatus() == PhabricatorObjectHandle::STATUS_CLOSED) {
      $item->setDisabled(true);
      $item->addAttribute(pht('Closed'));
    }

    return $item;
  }


  /**
   * Find the words which are part of the query string, and bold them in a
   * result string. This makes it easier for users to see why a result
   * matched their query.
   */
  private function emboldenQuery($str) {
    $query = $this->query->getParameter('query');

    if (!strlen($query) || !strlen($str)) {
      return $str;
    }

    // This algorithm is safe but not especially fast, so don't bother if
    // we're dealing with a lot of data. This mostly prevents silly/malicious
    // queries from doing anything bad.
    if (strlen($query) + strlen($str) > 2048) {
      return $str;
    }

    // Keep track of which characters we're going to make bold. This is
    // byte oriented, but we'll make sure we don't put a bold in the middle
    // of a character later.
    $bold = array_fill(0, strlen($str), false);

    // Split the query into words.
    $parts = preg_split('/ +/', $query);

    // Find all occurrences of each word, and mark them to be emboldened.
    foreach ($parts as $part) {
      $part = trim($part);
      $part = trim($part, '"+');
      if (!strlen($part)) {
        continue;
      }

      $matches = null;
      $has_matches = preg_match_all(
        '/(?:^|\b)('.preg_quote($part, '/').')/i',
        $str,
        $matches,
        PREG_OFFSET_CAPTURE);

      if (!$has_matches) {
        continue;
      }

      // Flag the matching part of the range for boldening.
      foreach ($matches[1] as $match) {
        $offset = $match[1];
        for ($ii = 0; $ii < strlen($match[0]); $ii++) {
          $bold[$offset + $ii] = true;
        }
      }
    }

    // Split the string into ranges, applying bold styling as required.
    $out = array();
    $buf = '';
    $pos = 0;
    $is_bold = false;

    // Make sure this is UTF8 because phutil_utf8v() will explode if it isn't.
    $str = phutil_utf8ize($str);
    foreach (phutil_utf8v($str) as $chr) {
      if ($bold[$pos] != $is_bold) {
        if (strlen($buf)) {
          if ($is_bold) {
            $out[] = phutil_tag('strong', array(), $buf);
          } else {
            $out[] = $buf;
          }
          $buf = '';
        }
        $is_bold = !$is_bold;
      }
      $buf .= $chr;
      $pos += strlen($chr);
    }

    if (strlen($buf)) {
      if ($is_bold) {
        $out[] = phutil_tag('strong', array(), $buf);
      } else {
        $out[] = $buf;
      }
    }

    return $out;
  }

}
