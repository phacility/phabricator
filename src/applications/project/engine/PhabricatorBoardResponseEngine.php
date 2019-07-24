<?php

final class PhabricatorBoardResponseEngine extends Phobject {

  private $viewer;
  private $objects;
  private $boardPHID;
  private $visiblePHIDs = array();
  private $updatePHIDs = array();
  private $ordering;
  private $sounds;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setBoardPHID($board_phid) {
    $this->boardPHID = $board_phid;
    return $this;
  }

  public function getBoardPHID() {
    return $this->boardPHID;
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObjects() {
    return $this->objects;
  }

  public function setVisiblePHIDs(array $visible_phids) {
    $this->visiblePHIDs = $visible_phids;
    return $this;
  }

  public function getVisiblePHIDs() {
    return $this->visiblePHIDs;
  }

  public function setUpdatePHIDs(array $update_phids) {
    $this->updatePHIDs = $update_phids;
    return $this;
  }

  public function getUpdatePHIDs() {
    return $this->updatePHIDs;
  }

  public function setOrdering(PhabricatorProjectColumnOrder $ordering) {
    $this->ordering = $ordering;
    return $this;
  }

  public function getOrdering() {
    return $this->ordering;
  }

  public function setSounds(array $sounds) {
    $this->sounds = $sounds;
    return $this;
  }

  public function getSounds() {
    return $this->sounds;
  }

  public function buildResponse() {
    $viewer = $this->getViewer();
    $board_phid = $this->getBoardPHID();
    $ordering = $this->getOrdering();

    $update_phids = $this->getUpdatePHIDs();
    $update_phids = array_fuse($update_phids);

    $visible_phids = $this->getVisiblePHIDs();
    $visible_phids = array_fuse($visible_phids);

    $all_phids = $update_phids + $visible_phids;

    // Load all the other tasks that are visible in the affected columns and
    // perform layout for them.

    if ($this->objects !== null) {
      $all_objects = $this->getObjects();
      $all_objects = mpull($all_objects, null, 'getPHID');
    } else {
      $all_objects = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withPHIDs($all_phids)
        ->execute();
      $all_objects = mpull($all_objects, null, 'getPHID');
    }

    // NOTE: The board layout engine is sensitive to PHID input order, and uses
    // the input order as a component of the "natural" column ordering if no
    // explicit ordering is specified. Rearrange the PHIDs in ID order.

    $all_objects = msort($all_objects, 'getID');
    $ordered_phids = mpull($all_objects, 'getPHID');

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs($ordered_phids)
      ->executeLayout();

    $natural = array();

    $update_columns = array();
    foreach ($update_phids as $update_phid) {
      $update_columns += $layout_engine->getObjectColumns(
        $board_phid,
        $update_phid);
    }

    foreach ($update_columns as $column_phid => $column) {
      $column_object_phids = $layout_engine->getColumnObjectPHIDs(
        $board_phid,
        $column_phid);
      $natural[$column_phid] = array_values($column_object_phids);
    }

    if ($ordering) {
      $vectors = $ordering->getSortVectorsForObjects($all_objects);
      $header_keys = $ordering->getHeaderKeysForObjects($all_objects);
      $headers = $ordering->getHeadersForObjects($all_objects);
      $headers = mpull($headers, 'toDictionary');
    } else {
      $vectors = array();
      $header_keys = array();
      $headers = array();
    }

    $templates = $this->newCardTemplates();

    $cards = array();
    foreach ($all_objects as $card_phid => $object) {
      $card = array(
        'vectors' => array(),
        'headers' => array(),
        'properties' => array(),
        'nodeHTMLTemplate' => null,
      );

      if ($ordering) {
        $order_key = $ordering->getColumnOrderKey();

        $vector = idx($vectors, $card_phid);
        if ($vector !== null) {
          $card['vectors'][$order_key] = $vector;
        }

        $header = idx($header_keys, $card_phid);
        if ($header !== null) {
          $card['headers'][$order_key] = $header;
        }

        $card['properties'] = self::newTaskProperties($object);
      }

      if (isset($templates[$card_phid])) {
        $card['nodeHTMLTemplate'] = hsprintf('%s', $templates[$card_phid]);
        $card['update'] = true;
      } else {
        $card['update'] = false;
      }

      $card['vectors'] = (object)$card['vectors'];
      $card['headers'] = (object)$card['headers'];
      $card['properties'] = (object)$card['properties'];

      $cards[$card_phid] = $card;
    }

    // Mark cards which are currently visible on the client but not visible
    // on the board on the server for removal from the client view of the
    // board state.
    foreach ($visible_phids as $card_phid) {
      if (!isset($cards[$card_phid])) {
        $cards[$card_phid] = array(
          'remove' => true,
        );
      }
    }

    $payload = array(
      'columnMaps' => $natural,
      'cards' => $cards,
      'headers' => $headers,
      'sounds' => $this->getSounds(),
    );

    return id(new AphrontAjaxResponse())
      ->setContent($payload);
  }

  public static function newTaskProperties($task) {
    return array(
      'points' => (double)$task->getPoints(),
      'status' => $task->getStatus(),
      'priority' => (int)$task->getPriority(),
      'owner' => $task->getOwnerPHID(),
    );
  }

  private function loadExcludedProjectPHIDs() {
    $viewer = $this->getViewer();
    $board_phid = $this->getBoardPHID();

    $exclude_phids = array($board_phid);

    $descendants = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withAncestorProjectPHIDs($exclude_phids)
      ->execute();

    foreach ($descendants as $descendant) {
      $exclude_phids[] = $descendant->getPHID();
    }

    return array_fuse($exclude_phids);
  }

  private function newCardTemplates() {
    $viewer = $this->getViewer();

    $update_phids = $this->getUpdatePHIDs();
    if (!$update_phids) {
      return array();
    }
    $update_phids = array_fuse($update_phids);

    if ($this->objects === null) {
      $objects = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withPHIDs($update_phids)
        ->needProjectPHIDs(true)
        ->execute();
    } else {
      $objects = $this->getObjects();
      $objects = mpull($objects, null, 'getPHID');
      $objects = array_select_keys($objects, $update_phids);
    }

    if (!$objects) {
      return array();
    }

    $excluded_phids = $this->loadExcludedProjectPHIDs();

    $rendering_engine = id(new PhabricatorBoardRenderingEngine())
      ->setViewer($viewer)
      ->setObjects($objects)
      ->setExcludedProjectPHIDs($excluded_phids);

    $templates = array();
    foreach ($objects as $object) {
      $object_phid = $object->getPHID();

      $card = $rendering_engine->renderCard($object_phid);
      $item = $card->getItem();
      $template = hsprintf('%s', $item);

      $templates[$object_phid] = $template;
    }

    return $templates;
  }

}
