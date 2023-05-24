<?php

final class PhutilRemarkupEngine extends PhutilMarkupEngine {

  const MODE_DEFAULT = 0;
  const MODE_TEXT = 1;
  const MODE_HTML_MAIL = 2;

  const MAX_CHILD_DEPTH = 32;

  private $blockRules = array();
  private $config = array();
  private $mode;
  private $metadata = array();
  private $states = array();
  private $postprocessRules = array();
  private $storage;

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function isTextMode() {
    return $this->mode & self::MODE_TEXT;
  }

  public function isAnchorMode() {
    return $this->getState('toc');
  }

  public function isHTMLMailMode() {
    return $this->mode & self::MODE_HTML_MAIL;
  }

  public function getQuoteDepth() {
    return $this->getConfig('runtime.quote.depth', 0);
  }

  public function setQuoteDepth($depth) {
    return $this->setConfig('runtime.quote.depth', $depth);
  }

  public function setBlockRules(array $rules) {
    assert_instances_of($rules, 'PhutilRemarkupBlockRule');

    $rules = msortv($rules, 'getPriorityVector');

    $this->blockRules = $rules;
    foreach ($this->blockRules as $rule) {
      $rule->setEngine($this);
    }

    $post_rules = array();
    foreach ($this->blockRules as $block_rule) {
      foreach ($block_rule->getMarkupRules() as $rule) {
        $key = $rule->getPostprocessKey();
        if ($key !== null) {
          $post_rules[$key] = $rule;
        }
      }
    }

    $this->postprocessRules = $post_rules;

    return $this;
  }

  public function getTextMetadata($key, $default = null) {
    if (isset($this->metadata[$key])) {
      return $this->metadata[$key];
    }
    return idx($this->metadata, $key, $default);
  }

  public function setTextMetadata($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function storeText($text) {
    if ($this->isTextMode()) {
      $text = phutil_safe_html($text);
    }
    return $this->storage->store($text);
  }

  public function overwriteStoredText($token, $new_text) {
    if ($this->isTextMode()) {
      $new_text = phutil_safe_html($new_text);
    }
    $this->storage->overwrite($token, $new_text);
    return $this;
  }

  public function markupText($text) {
    return $this->postprocessText($this->preprocessText($text));
  }

  public function pushState($state) {
    if (empty($this->states[$state])) {
      $this->states[$state] = 0;
    }
    $this->states[$state]++;
    return $this;
  }

  public function popState($state) {
    if (empty($this->states[$state])) {
      throw new Exception(pht("State '%s' pushed more than popped!", $state));
    }
    $this->states[$state]--;
    if (!$this->states[$state]) {
      unset($this->states[$state]);
    }
    return $this;
  }

  public function getState($state) {
    return !empty($this->states[$state]);
  }

  public function preprocessText($text) {
    $this->metadata = array();
    $this->storage = new PhutilRemarkupBlockStorage();

    $blocks = $this->splitTextIntoBlocks($text);

    $output = array();
    foreach ($blocks as $block) {
      $output[] = $this->markupBlock($block);
    }
    $output = $this->flattenOutput($output);

    $map = $this->storage->getMap();
    $this->storage = null;
    $metadata = $this->metadata;


    return array(
      'output'    => $output,
      'storage'   => $map,
      'metadata'  => $metadata,
    );
  }

  private function splitTextIntoBlocks($text, $depth = 0) {
    // Apply basic block and paragraph normalization to the text. NOTE: We don't
    // strip trailing whitespace because it is semantic in some contexts,
    // notably inlined diffs that the author intends to show as a code block.
    $text = phutil_split_lines($text, true);
    $block_rules = $this->blockRules;
    $blocks = array();
    $cursor = 0;

    $can_merge = array();
    foreach ($block_rules as $key => $block_rule) {
      if ($block_rule instanceof PhutilRemarkupDefaultBlockRule) {
        $can_merge[$key] = true;
      }
    }

    $last_block = null;
    $last_block_key = -1;

    // See T13487. For very large inputs, block separation can dominate
    // runtime. This is written somewhat clumsily to attempt to handle
    // very large inputs as gracefully as is practical.

    while (isset($text[$cursor])) {
      $starting_cursor = $cursor;
      foreach ($block_rules as $block_key => $block_rule) {
        $num_lines = $block_rule->getMatchingLineCount($text, $cursor);

        if ($num_lines) {
          $current_block = array(
            'start' => $cursor,
            'num_lines' => $num_lines,
            'rule' => $block_rule,
            'empty' => self::isEmptyBlock($text, $cursor, $num_lines),
            'children' => array(),
            'merge' => isset($can_merge[$block_key]),
          );

          $should_merge = self::shouldMergeParagraphBlocks(
            $text,
            $last_block,
            $current_block);

          if ($should_merge) {
            $last_block['num_lines'] =
              ($last_block['num_lines'] + $current_block['num_lines']);

            $last_block['empty'] =
              ($last_block['empty'] && $current_block['empty']);

            $blocks[$last_block_key] = $last_block;
          } else {
            $blocks[] = $current_block;

            $last_block = $current_block;
            $last_block_key++;
          }

          $cursor += $num_lines;

          break;
        }
      }

      if ($starting_cursor === $cursor) {
        throw new Exception(pht('Block in text did not match any block rule.'));
      }
    }

    // See T13487. It's common for blocks to be small, and this loop seems to
    // measure as faster if we manually concatenate blocks than if we
    // "array_slice()" and "implode()" blocks. This is a bit muddy.

    foreach ($blocks as $key => $block) {
      $min = $block['start'];
      $max = $min + $block['num_lines'];

      $lines = '';
      for ($ii = $min; $ii < $max; $ii++) {
        if (isset($text[$ii])) {
          $lines .= $text[$ii];
        }
      }

      $blocks[$key]['text'] = $lines;
    }

    // Stop splitting child blocks apart if we get too deep. This arrests
    // any blocks which have looping child rules, and stops the stack from
    // exploding if someone writes a hilarious comment with 5,000 levels of
    // quoted text.

    if ($depth < self::MAX_CHILD_DEPTH) {
      foreach ($blocks as $key => $block) {
        $rule = $block['rule'];
        if (!$rule->supportsChildBlocks()) {
          continue;
        }

        list($parent_text, $child_text) = $rule->extractChildText(
          $block['text']);
        $blocks[$key]['text'] = $parent_text;
        $blocks[$key]['children'] = $this->splitTextIntoBlocks(
          $child_text,
          $depth + 1);
      }
    }

    return $blocks;
  }

  private function markupBlock(array $block) {
    $rule = $block['rule'];

    $rule->willMarkupChildBlocks();

    $children = array();
    foreach ($block['children'] as $child) {
      $children[] = $this->markupBlock($child);
    }

    $rule->didMarkupChildBlocks();

    if ($children) {
      $children = $this->flattenOutput($children);
    } else {
      $children = null;
    }

    return $rule->markupText($block['text'], $children);
  }

  private function flattenOutput(array $output) {
    if ($this->isTextMode()) {
      $output = implode("\n\n", $output)."\n";
    } else {
      $output = phutil_implode_html("\n\n", $output);
    }

    return $output;
  }

  private static function shouldMergeParagraphBlocks(
    $text,
    $last_block,
    $current_block) {

    // If we're at the beginning of the input, we can't merge.
    if ($last_block === null) {
      return false;
    }

    // If the previous block wasn't a default block, we can't merge.
    if (!$last_block['merge']) {
      return false;
    }

    // If the current block isn't a default block, we can't merge.
    if (!$current_block['merge']) {
      return false;
    }

    // If the last block was empty, we definitely want to merge.
    if ($last_block['empty']) {
      return true;
    }

    // If this block is empty, we definitely want to merge.
    if ($current_block['empty']) {
      return true;
    }

    // Check if the last line of the previous block or the first line of this
    // block have any non-whitespace text. If they both do, we're going to
    // merge.

    // If either of them are a blank line or a line with only whitespace, we
    // do not merge: this means we've found a paragraph break.

    $tail = $text[$current_block['start'] - 1];
    $head = $text[$current_block['start']];
    if (strlen(trim($tail)) && strlen(trim($head))) {
      return true;
    }

    return false;
  }

  private static function isEmptyBlock($text, $start, $num_lines) {
    for ($cursor = $start; $cursor < $start + $num_lines; $cursor++) {
      if (strlen(trim($text[$cursor]))) {
        return false;
      }
    }
    return true;
  }

  public function postprocessText(array $dict) {
    $this->metadata = idx($dict, 'metadata', array());

    $this->storage = new PhutilRemarkupBlockStorage();
    $this->storage->setMap(idx($dict, 'storage', array()));

    foreach ($this->blockRules as $block_rule) {
      $block_rule->postprocess();
    }

    foreach ($this->postprocessRules as $rule) {
      $rule->didMarkupText();
    }

    return $this->restoreText(idx($dict, 'output'));
  }

  public function restoreText($text) {
    return $this->storage->restore($text, $this->isTextMode());
  }
}
