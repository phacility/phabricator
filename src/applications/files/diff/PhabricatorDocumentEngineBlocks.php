<?php

final class PhabricatorDocumentEngineBlocks
  extends Phobject {

  private $lists = array();

  public function addBlockList(PhabricatorDocumentRef $ref, array $blocks) {
    assert_instances_of($blocks, 'PhabricatorDocumentEngineBlock');

    $this->lists[] = array(
      'ref' => $ref,
      'blocks' => array_values($blocks),
    );

    return $this;
  }

  public function newTwoUpLayout() {
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

        $row[] = $cell;
      }

      if (!$found_any) {
        break;
      }

      $rows[] = $row;
      $idx++;
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


}
