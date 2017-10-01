<?php

final class PhabricatorSearchResultView extends AphrontView {

  private $handle;
  private $object;
  private $tokens;

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setTokens(array $tokens) {
    assert_instances_of($tokens, 'PhabricatorFulltextToken');
    $this->tokens = $tokens;
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
    $tokens = $this->tokens;

    if (!$tokens) {
      return $str;
    }

    if (count($tokens) > 16) {
      return $str;
    }

    if (!strlen($str)) {
      return $str;
    }

    if (strlen($str) > 2048) {
      return $str;
    }

    $patterns = array();
    foreach ($tokens as $token) {
      $raw_token = $token->getToken();
      $operator = $raw_token->getOperator();

      $value = $raw_token->getValue();

      switch ($operator) {
        case PhutilSearchQueryCompiler::OPERATOR_SUBSTRING:
          $patterns[] = '(('.preg_quote($value).'))ui';
          break;
        case PhutilSearchQueryCompiler::OPERATOR_AND:
          $patterns[] = '((?<=\W|^)('.preg_quote($value).')(?=\W|\z))ui';
          break;
        default:
          // Don't highlight anything else, particularly "NOT".
          break;
      }
    }

    // Find all matches for all query terms in the document title, then reduce
    // them to a map from offsets to highlighted sequence lengths. If two terms
    // match at the same position, we choose the longer one.
    $all_matches = array();
    foreach ($patterns as $pattern) {
      $matches = null;
      $ok = preg_match_all(
        $pattern,
        $str,
        $matches,
        PREG_OFFSET_CAPTURE);
      if (!$ok) {
        continue;
      }

      foreach ($matches[1] as $match) {
        $match_text = $match[0];
        $match_offset = $match[1];

        if (!isset($all_matches[$match_offset])) {
          $all_matches[$match_offset] = 0;
        }

        $all_matches[$match_offset] = max(
          $all_matches[$match_offset],
          strlen($match_text));
      }
    }

    // Go through the string one display glyph at a time. If a glyph starts
    // on a highlighted byte position, turn on highlighting for the nubmer
    // of matching bytes. If a query searches for "e" and the document contains
    // an "e" followed by a bunch of combining marks, this will correctly
    // highlight the entire glyph.
    $parts = array();
    $highlight = 0;
    $offset = 0;
    foreach (phutil_utf8v_combined($str) as $character) {
      $length = strlen($character);

      if (isset($all_matches[$offset])) {
        $highlight = $all_matches[$offset];
      }

      if ($highlight > 0) {
        $is_highlighted = true;
        $highlight -= $length;
      } else {
        $is_highlighted = false;
      }

      $parts[] = array(
        'text' => $character,
        'highlighted' => $is_highlighted,
      );

      $offset += $length;
    }

    // Combine all the sequences together so we aren't emitting a tag around
    // every individual character.
    $last = null;
    foreach ($parts as $key => $part) {
      if ($last !== null) {
        if ($part['highlighted'] == $parts[$last]['highlighted']) {
          $parts[$last]['text'] .= $part['text'];
          unset($parts[$key]);
          continue;
        }
      }

      $last = $key;
    }

    // Finally, add tags.
    $result = array();
    foreach ($parts as $part) {
      if ($part['highlighted']) {
        $result[] = phutil_tag('strong', array(), $part['text']);
      } else {
        $result[] = $part['text'];
      }
    }

    return $result;
  }

}
