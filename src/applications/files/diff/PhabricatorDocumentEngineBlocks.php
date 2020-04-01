<?php

final class PhabricatorDocumentEngineBlocks
  extends Phobject {

  private $lists = array();
  private $messages = array();

  public function addMessage($message) {
    $this->messages[] = $message;
    return $this;
  }

  public function getMessages() {
    return $this->messages;
  }

  public function addBlockList(
    PhabricatorDocumentRef $ref = null,
    array $blocks = array()) {

    assert_instances_of($blocks, 'PhabricatorDocumentEngineBlock');

    $this->lists[] = array(
      'ref' => $ref,
      'blocks' => array_values($blocks),
    );

    return $this;
  }

  public function getDocumentRefs() {
    return ipull($this->lists, 'ref');
  }

  public function newTwoUpLayout() {
    $rows = array();
    $lists = $this->lists;

    if (count($lists) != 2) {
      return array();
    }

    $specs = array();
    foreach ($this->lists as $list) {
      $specs[] = $this->newDiffSpec($list['blocks']);
    }

    $old_map = $specs[0]['map'];
    $new_map = $specs[1]['map'];

    $old_list = $specs[0]['list'];
    $new_list = $specs[1]['list'];

    $changeset = id(new PhabricatorDifferenceEngine())
      ->generateChangesetFromFileContent($old_list, $new_list);

    $hunk_parser = id(new DifferentialHunkParser())
      ->parseHunksForLineData($changeset->getHunks())
      ->reparseHunksForSpecialAttributes();

    $hunk_parser->generateVisibleBlocksMask(2);
    $mask = $hunk_parser->getVisibleLinesMask();

    $old_lines = $hunk_parser->getOldLines();
    $new_lines = $hunk_parser->getNewLines();

    $rows = array();

    $count = count($old_lines);
    for ($ii = 0; $ii < $count; $ii++) {
      $old_line = idx($old_lines, $ii);
      $new_line = idx($new_lines, $ii);

      $is_visible = !empty($mask[$ii]);

      if ($old_line) {
        $old_hash = rtrim($old_line['text'], "\n");
        if (!strlen($old_hash)) {
          // This can happen when one of the sources has no blocks.
          $old_block = null;
        } else {
          $old_block = array_shift($old_map[$old_hash]);
          $old_block
            ->setDifferenceType($old_line['type'])
            ->setIsVisible($is_visible);
        }
      } else {
        $old_block = null;
      }

      if ($new_line) {
        $new_hash = rtrim($new_line['text'], "\n");
        if (!strlen($new_hash)) {
          $new_block = null;
        } else {
          $new_block = array_shift($new_map[$new_hash]);
          $new_block
            ->setDifferenceType($new_line['type'])
            ->setIsVisible($is_visible);
        }
      } else {
        $new_block = null;
      }

      // If both lists are empty, we may generate a row which has two empty
      // blocks.
      if (!$old_block && !$new_block) {
        continue;
      }

      $rows[] = array(
        $old_block,
        $new_block,
      );
    }

    return $rows;
  }

  public function newOneUpLayout() {
    $rows = array();
    $lists = $this->lists;

    $idx = 0;
    while (true) {
      $found_any = false;

      $row = array();
      foreach ($lists as $list) {
        $blocks = $list['blocks'];
        $cell = idx($blocks, $idx);

        if ($cell !== null) {
          $found_any = true;
        }

        if ($cell) {
          $rows[] = $cell;
        }
      }

      if (!$found_any) {
        break;
      }

      $idx++;
    }

    return $rows;
  }


  private function newDiffSpec(array $blocks) {
    $map = array();
    $list = array();

    foreach ($blocks as $block) {
      $hash = $block->getDifferenceHash();

      if (!isset($map[$hash])) {
        $map[$hash] = array();
      }
      $map[$hash][] = $block;

      $list[] = $hash;
    }

    return array(
      'map' => $map,
      'list' => implode("\n", $list)."\n",
    );
  }

}
