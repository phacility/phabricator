<?php

final class PhabricatorBoardResponseEngine extends Phobject {

  private $viewer;
  private $boardPHID;
  private $objectPHID;
  private $visiblePHIDs;
  private $ordering;

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

  public function setOrdering(PhabricatorProjectColumnOrder $ordering) {
    $this->ordering = $ordering;
    return $this;
  }

  public function getOrdering() {
    return $this->ordering;
  }

  public function buildResponse() {
    $viewer = $this->getViewer();
    $object_phid = $this->getObjectPHID();
    $board_phid = $this->getBoardPHID();
    $ordering = $this->getOrdering();

    // Load all the other tasks that are visible in the affected columns and
    // perform layout for them.
    $visible_phids = $this->getAllVisiblePHIDs();

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs($visible_phids)
      ->executeLayout();

    $object_columns = $layout_engine->getObjectColumns(
      $board_phid,
      $object_phid);

    $natural = array();
    foreach ($object_columns as $column_phid => $column) {
      $column_object_phids = $layout_engine->getColumnObjectPHIDs(
        $board_phid,
        $column_phid);
      $natural[$column_phid] = array_values($column_object_phids);
    }

    $all_visible = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs($visible_phids)
      ->execute();
    $all_visible = mpull($all_visible, null, 'getPHID');

    if ($ordering) {
      $vectors = $ordering->getSortVectorsForObjects($all_visible);
      $header_keys = $ordering->getHeaderKeysForObjects($all_visible);
      $headers = $ordering->getHeadersForObjects($all_visible);
      $headers = mpull($headers, 'toDictionary');
    } else {
      $vectors = array();
      $header_keys = array();
      $headers = array();
    }

    $object = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->needProjectPHIDs(true)
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $template = $this->buildTemplate($object);

    $cards = array();
    foreach ($all_visible as $card_phid => $object) {
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

        $card['properties'] = array(
          'points' => (double)$object->getPoints(),
          'status' => $object->getStatus(),
        );
      }

      if ($card_phid === $object_phid) {
        $card['nodeHTMLTemplate'] = hsprintf('%s', $template);
      }

      $card['vectors'] = (object)$card['vectors'];
      $card['headers'] = (object)$card['headers'];
      $card['properties'] = (object)$card['properties'];

      $cards[$card_phid] = $card;
    }

    $payload = array(
      'objectPHID' => $object_phid,
      'columnMaps' => $natural,
      'cards' => $cards,
      'headers' => $headers,
    );

    return id(new AphrontAjaxResponse())
      ->setContent($payload);
  }

  private function buildTemplate($object) {
    $viewer = $this->getViewer();
    $object_phid = $this->getObjectPHID();

    $excluded_phids = $this->loadExcludedProjectPHIDs();

    $rendering_engine = id(new PhabricatorBoardRenderingEngine())
      ->setViewer($viewer)
      ->setObjects(array($object))
      ->setExcludedProjectPHIDs($excluded_phids);

    $card = $rendering_engine->renderCard($object_phid);

    return hsprintf('%s', $card->getItem());
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
    $visible_phids = $this->getVisiblePHIDs();
    $visible_phids[] = $this->getObjectPHID();
    $visible_phids = array_fuse($visible_phids);
    return $visible_phids;
  }

}
