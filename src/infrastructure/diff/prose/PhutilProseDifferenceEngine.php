<?php

final class PhutilProseDifferenceEngine extends Phobject {

  public function getDiff($u, $v) {
    return $this->buildDiff($u, $v, 0);
  }

  private function buildDiff($u, $v, $level) {
    $u_parts = $this->splitCorpus($u, $level);
    $v_parts = $this->splitCorpus($v, $level);

    if ($level === 0) {
      $diff = $this->newHashDiff($u_parts, $v_parts);
      $too_large = false;
    } else {
      list($diff, $too_large) = $this->newEditDistanceMatrixDiff(
        $u_parts,
        $v_parts,
        $level);
    }

    $diff->reorderParts();

    // If we just built a character-level diff, we're all done and do not
    // need to go any deeper.
    if ($level == 3) {
      return $diff;
    }

    $blocks = array();
    $block = null;
    foreach ($diff->getParts() as $part) {
      $type = $part['type'];
      $text = $part['text'];
      switch ($type) {
        case '=':
          if ($block) {
            $blocks[] = $block;
            $block = null;
          }
          $blocks[] = array(
            'type' => $type,
            'text' => $text,
          );
          break;
        case '-':
          if (!$block) {
            $block = array(
              'type' => '!',
              'old' => '',
              'new' => '',
            );
          }
          $block['old'] .= $text;
          break;
        case '+':
          if (!$block) {
            $block = array(
              'type' => '!',
              'old' => '',
              'new' => '',
            );
          }
          $block['new'] .= $text;
          break;
      }
    }

    if ($block) {
      $blocks[] = $block;
    }

    $result = new PhutilProseDiff();
    foreach ($blocks as $block) {
      $type = $block['type'];
      if ($type == '=') {
        $result->addPart('=', $block['text']);
      } else {
        $old = $block['old'];
        $new = $block['new'];
        if (!strlen($old) && !strlen($new)) {
          // Nothing to do.
        } else if (!strlen($old)) {
          $result->addPart('+', $new);
        } else if (!strlen($new)) {
          $result->addPart('-', $old);
        } else {
          if ($too_large) {
            // If this text was too big to diff, don't try to subdivide it.
            $result->addPart('-', $old);
            $result->addPart('+', $new);
          } else {
            $subdiff = $this->buildDiff(
              $old,
              $new,
              $level + 1);

            foreach ($subdiff->getParts() as $part) {
              $result->addPart($part['type'], $part['text']);
            }
          }
        }
      }
    }

    $result->reorderParts();

    return $result;
  }

  private function splitCorpus($corpus, $level) {
    switch ($level) {
      case 0:
        // Level 0: Split into paragraphs.
        $expr = '/([\n]+)/';
        break;
      case 1:
        // Level 1: Split into sentences.
        $expr = '/([\n,!;?\.]+)/';
        break;
      case 2:
        // Level 2: Split into words.
        $expr = '/(\s+)/';
        break;
      case 3:
        // Level 3: Split into characters.
        return phutil_utf8v_combined($corpus);
    }

    $pieces = preg_split($expr, $corpus, -1, PREG_SPLIT_DELIM_CAPTURE);
    return $this->stitchPieces($pieces, $level);
  }

  private function stitchPieces(array $pieces, $level) {
    $results = array();
    $count = count($pieces);
    for ($ii = 0; $ii < $count; $ii += 2) {
      $result = $pieces[$ii];
      if ($ii + 1 < $count) {
        $result .= $pieces[$ii + 1];
      }

      if ($level < 2) {
        $trimmed_pieces = $this->trimApart($result);
        foreach ($trimmed_pieces as $trimmed_piece) {
          $results[] = $trimmed_piece;
        }
      } else {
        $results[] = $result;
      }
    }

    // If the input ended with a delimiter, we can get an empty final piece.
    // Just discard it.
    if (last($results) == '') {
      array_pop($results);
    }

    return $results;
  }

  private function newEditDistanceMatrixDiff(
    array $u_parts,
    array $v_parts,
    $level) {

    $matrix = id(new PhutilEditDistanceMatrix())
      ->setMaximumLength(128)
      ->setSequences($u_parts, $v_parts)
      ->setComputeString(true);

    // For word-level and character-level changes, smooth the output string
    // to reduce the choppiness of the diff.
    if ($level > 1) {
      $matrix->setApplySmoothing(PhutilEditDistanceMatrix::SMOOTHING_FULL);
    }

    $u_pos = 0;
    $v_pos = 0;

    $edits = $matrix->getEditString();
    $edits_length = strlen($edits);

    $diff = new PhutilProseDiff();
    for ($ii = 0; $ii < $edits_length; $ii++) {
      $c = $edits[$ii];
      if ($c == 's') {
        $diff->addPart('=', $u_parts[$u_pos]);
        $u_pos++;
        $v_pos++;
      } else if ($c == 'd') {
        $diff->addPart('-', $u_parts[$u_pos]);
        $u_pos++;
      } else if ($c == 'i') {
        $diff->addPart('+', $v_parts[$v_pos]);
        $v_pos++;
      } else if ($c == 'x') {
        $diff->addPart('-', $u_parts[$u_pos]);
        $diff->addPart('+', $v_parts[$v_pos]);
        $u_pos++;
        $v_pos++;
      } else {
        throw new Exception(
          pht(
            'Unexpected character ("%s") in edit string.',
            $c));
      }
    }

    return array($diff, $matrix->didReachMaximumLength());
  }

  private function newHashDiff(array $u_parts, array $v_parts) {

    $u_ref = new PhabricatorDocumentRef();
    $v_ref = new PhabricatorDocumentRef();

    $u_blocks = $this->newDocumentEngineBlocks($u_parts);
    $v_blocks = $this->newDocumentEngineBlocks($v_parts);

    $rows = id(new PhabricatorDocumentEngineBlocks())
      ->addBlockList($u_ref, $u_blocks)
      ->addBlockList($v_ref, $v_blocks)
      ->newTwoUpLayout();

    $diff = new PhutilProseDiff();
    foreach ($rows as $row) {
      list($u_block, $v_block) = $row;

      if ($u_block && $v_block) {
        if ($u_block->getDifferenceType() === '-') {
          $diff->addPart('-', $u_block->getContent());
          $diff->addPart('+', $v_block->getContent());
        } else {
          $diff->addPart('=', $u_block->getContent());
        }
      } else if ($u_block) {
        $diff->addPart('-', $u_block->getContent());
      } else {
        $diff->addPart('+', $v_block->getContent());
      }
    }

    return $diff;
  }

  private function newDocumentEngineBlocks(array $parts) {
    $blocks = array();

    foreach ($parts as $part) {
      $hash = PhabricatorHash::digestForIndex($part);

      $blocks[] = id(new PhabricatorDocumentEngineBlock())
        ->setContent($part)
        ->setDifferenceHash($hash);
    }

    return $blocks;
  }

  public static function trimApart($input) {
    // Split pieces into separate text and whitespace sections: make one
    // piece out of all the whitespace at the beginning, one piece out of
    // all the actual text in the middle, and one piece out of all the
    // whitespace at the end.

    $parts = array();

    $length = strlen($input);

    $corpus = ltrim($input);
    $l_length = strlen($corpus);
    if ($l_length !== $length) {
      $parts[] = substr($input, 0, $length - $l_length);
    }

    $corpus = rtrim($corpus);
    $lr_length = strlen($corpus);

    if ($lr_length) {
      $parts[] = $corpus;
    }

    if ($lr_length !== $l_length) {
      // NOTE: This will be a negative value; we're slicing from the end of
      // the input string.
      $parts[] = substr($input, $lr_length - $l_length);
    }

    return $parts;
  }

}
