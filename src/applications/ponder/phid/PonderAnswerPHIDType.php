<?php

final class PonderAnswerPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ANSW';

  public function getTypeName() {
    return pht('Ponder Answer');
  }

  public function newObject() {
    return new PonderAnswer();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PonderAnswerQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $answer = $objects[$phid];

      $id = $answer->getID();
      $question = $answer->getQuestion();
      $question_title = $question->getFullTitle();

      $handle->setName("{$question_title} (Answer {$id})");
      $handle->setURI($answer->getURI());
    }
  }

}
