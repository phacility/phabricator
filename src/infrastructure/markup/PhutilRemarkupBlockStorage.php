<?php

/**
 * Remarkup prevents several classes of text-processing problems by replacing
 * tokens in the text as they are marked up. For example, if you write something
 * like this:
 *
 *   //D12//
 *
 * It is processed in several stages. First the "D12" matches and is replaced
 * with a token, in the form of "<0x01><ID number><literal "Z">". The first
 * byte, "<0x01>" is a single byte with value 1 that marks a token. If this is
 * token ID "444", the text may now look like this:
 *
 *   //<0x01>444Z//
 *
 * Now the italics match and are replaced, using the next token ID:
 *
 *   <0x01>445Z
 *
 * When processing completes, all the tokens are replaced with their final
 * equivalents. For example, token 444 is evaluated to:
 *
 *   <a href="http://...">...</a>
 *
 * Then token 445 is evaluated:
 *
 *   <em><0x01>444Z</em>
 *
 * ...and all tokens it contains are replaced:
 *
 *   <em><a href="http://...">...</a></em>
 *
 * If we didn't do this, the italics rule could match the "//" in "http://",
 * or any other number of processing mistakes could occur, some of which create
 * security risks.
 *
 * This class generates keys, and stores the map of keys to replacement text.
 */
final class PhutilRemarkupBlockStorage extends Phobject {

  const MAGIC_BYTE = "\1";

  private $map = array();
  private $index = 0;

  public function store($text) {
    $key = self::MAGIC_BYTE.(++$this->index).'Z';
    $this->map[$key] = $text;
    return $key;
  }

  public function restore($corpus, $text_mode = false) {
    $map = $this->map;

    if (!$text_mode) {
      foreach ($map as $key => $content) {
        $map[$key] = phutil_escape_html($content);
      }
      $corpus = phutil_escape_html($corpus);
    }

    // NOTE: Tokens may contain other tokens: for example, a table may have
    // links inside it. So we can't do a single simple find/replace, because
    // we need to find and replace child tokens inside the content of parent
    // tokens.

    // However, we know that rules which have child tokens must always store
    // all their child tokens first, before they store their parent token: you
    // have to pass the "store(text)" API a block of text with tokens already
    // in it, so you must have created child tokens already.

    // Thus, all child tokens will appear in the list before parent tokens, so
    // if we start at the beginning of the list and replace all the tokens we
    // find in each piece of content, we'll end up expanding all subtokens
    // correctly.

    $map[] = $corpus;
    $seen = array();
    foreach ($map as $key => $content) {
      $seen[$key] = true;

      // If the content contains no token magic, we don't need to replace
      // anything.
      if (strpos($content, self::MAGIC_BYTE) === false) {
        continue;
      }

      $matches = null;
      preg_match_all(
        '/'.self::MAGIC_BYTE.'\d+Z/',
        $content,
        $matches,
        PREG_OFFSET_CAPTURE);

      $matches = $matches[0];

      // See PHI1114. We're replacing all the matches in one pass because this
      // is significantly faster than doing "substr_replace()" in a loop if the
      // corpus is large and we have a large number of matches.

      // Build a list of string pieces in "$parts" by interleaving the
      // plain strings between each token and the replacement token text, then
      // implode the whole thing when we're done.

      $parts = array();
      $pos = 0;
      foreach ($matches as $next) {
        $subkey = $next[0];

        // If we've matched a token pattern but don't actually have any
        // corresponding token, just skip this match. This should not be
        // possible, and should perhaps be an error.
        if (!isset($seen[$subkey])) {
          if (!isset($map[$subkey])) {
            throw new Exception(
              pht(
                'Matched token key "%s" while processing remarkup block, but '.
                'this token does not exist in the token map.',
                $subkey));
          } else {
            throw new Exception(
              pht(
                'Matched token key "%s" while processing remarkup block, but '.
                'this token appears later in the list than the key being '.
                'processed ("%s").',
                $subkey,
                $key));
          }
        }

        $subpos = $next[1];

        // If there were any non-token bytes since the last token, add them.
        if ($subpos > $pos) {
          $parts[] = substr($content, $pos, $subpos - $pos);
        }

        // Add the token replacement text.
        $parts[] = $map[$subkey];

        // Move the non-token cursor forward over the token.
        $pos = $subpos + strlen($subkey);
      }

      // Add any leftover non-token bytes after the last token.
      $parts[] = substr($content, $pos);

      $content = implode('', $parts);

      $map[$key] = $content;
    }
    $corpus = last($map);

    if (!$text_mode) {
      $corpus = phutil_safe_html($corpus);
    }

    return $corpus;
  }

  public function overwrite($key, $new_text) {
    $this->map[$key] = $new_text;
    return $this;
  }

  public function getMap() {
    return $this->map;
  }

  public function setMap(array $map) {
    $this->map = $map;
    return $this;
  }

}
