<?php

abstract class MultimeterController extends PhabricatorController {

  private $dimensions = array();

  protected function loadDimensions(array $rows) {
    if (!$rows) {
      return;
    }

    $map = array(
      'eventLabelID' => new MultimeterLabel(),
      'eventViewerID' => new MultimeterViewer(),
      'eventHostID' => new MultimeterHost(),
      'eventContextID' => new MultimeterContext(),
    );

    $ids = array();
    foreach ($map as $key => $object) {
      foreach ($rows as $row) {
        $ids[$key][] = $row[$key];
      }
    }

    foreach ($ids as $key => $list) {
      $object = $map[$key];
      if (empty($this->dimensions[$key])) {
        $this->dimensions[$key] = array();
      }
      $this->dimensions[$key] += $object->loadAllWhere(
        'id IN (%Ld)',
        $list);
    }
  }

  protected function getLabelDimension($id) {
    if (empty($this->dimensions['eventLabelID'][$id])) {
      return $this->newMissingDimension(new MultimeterLabel(), $id);
    }
    return $this->dimensions['eventLabelID'][$id];
  }

  protected function getViewerDimension($id) {
    if (empty($this->dimensions['eventViewerID'][$id])) {
      return $this->newMissingDimension(new MultimeterViewer(), $id);
    }
    return $this->dimensions['eventViewerID'][$id];
  }

  protected function getHostDimension($id) {
    if (empty($this->dimensions['eventHostID'][$id])) {
      return $this->newMissingDimension(new MultimeterHost(), $id);
    }
    return $this->dimensions['eventHostID'][$id];
  }

  protected function getContextDimension($id) {
    if (empty($this->dimensions['eventContextID'][$id])) {
      return $this->newMissingDimension(new MultimeterContext(), $id);
    }
    return $this->dimensions['eventContextID'][$id];
  }

  private function newMissingDimension(MultimeterDimension $dim, $id) {
    $dim->setName('<missing:'.$id.'>');
    return $dim;
  }

}
