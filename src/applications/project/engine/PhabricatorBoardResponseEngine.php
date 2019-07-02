<?php

final class PhabricatorBoardResponseEngine extends Phobject {

  private $viewer;
  private $boardPHID;
  private $objectPHID;
  private $visiblePHIDs;
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

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
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
    $object_phid = $this->getObjectPHID();
    $board_phid = $this->getBoardPHID();
    $ordering = $this->getOrdering();

    // Load all the other tasks that are visible in the affected columns and
    // perform layout for them.
    $all_phids = $this->getAllVisiblePHIDs();

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs($all_phids)
      ->executeLayout();

    $natural = array();

    $update_phids = $this->getAllUpdatePHIDs();
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

    $all_objects = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs($all_phids)
      ->execute();
    $all_objects = mpull($all_objects, null, 'getPHID');

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

  private function getAllVisiblePHIDs() {
    $phids = $this->getAllUpdatePHIDs();

    foreach ($this->getVisiblePHIDs() as $phid) {
      $phids[] = $phid;
    }

    $phids = array_fuse($phids);

    return $phids;
  }

  private function getAllUpdatePHIDs() {
    $phids = $this->getUpdatePHIDs();

    $object_phid = $this->getObjectPHID();
    if ($object_phid) {
      $phids[] = $object_phid;
    }

    $phids = array_fuse($phids);

    return $phids;
  }

  private function newCardTemplates() {
    $viewer = $this->getViewer();

    $update_phids = $this->getAllUpdatePHIDs();
    if (!$update_phids) {
      return array();
    }

    $objects = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs($update_phids)
      ->needProjectPHIDs(true)
      ->execute();

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
