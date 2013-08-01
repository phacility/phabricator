<?php

final class PonderPHIDTypeQuestion extends PhabricatorPHIDType {

  const TYPECONST = 'QUES';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Question');
  }

  public function newObject() {
    return new PonderQuestion();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PonderQuestionQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $question = $objects[$phid];

      $id = $question->getID();
      $title = $question->getTitle();

      $handle->setName("Q{$id}");
      $handle->setURI("/Q{$id}");
      $handle->setFullName("Q{$id}: {$title}");
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^Q\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PonderQuestionQuery())
      ->setViewer($query->getViewer())
      ->withIDs(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      foreach (idx($id_map, $id, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
