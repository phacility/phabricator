<?php

final class PhrictionEditConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.edit';
  }

  public function getMethodDescription() {
    return pht('Update a Phriction document.');
  }

  protected function defineParamTypes() {
    return array(
      'slug'          => 'required string',
      'title'         => 'optional string',
      'content'       => 'optional string',
      'description'   => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');

    $doc = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->needContent(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$doc) {
      throw new Exception(pht('No such document.'));
    }

    $xactions = array();
    $xactions[] = id(new PhrictionTransaction())
      ->setTransactionType(PhrictionTransaction::TYPE_TITLE)
      ->setNewValue($request->getValue('title'));
    $xactions[] = id(new PhrictionTransaction())
      ->setTransactionType(PhrictionTransaction::TYPE_CONTENT)
      ->setNewValue($request->getValue('content'));

    $editor = id(new PhrictionTransactionEditor())
      ->setActor($request->getUser())
      ->setContentSourceFromConduitRequest($request)
      ->setContinueOnNoEffect(true)
      ->setDescription($request->getValue('description'));

    try {
      $editor->applyTransactions($doc, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      // TODO - some magical hotness via T5873
      throw $ex;
    }

    return $this->buildDocumentInfoDictionary($doc);
  }

}
