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
    if ($slug === null || !strlen($slug)) {
      throw new Exception(pht('Field "slug" must be non-empty.'));
    }

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
    if ($request->getValue('title')) {
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(
          PhrictionDocumentTitleTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('title'));
    }

    if ($request->getValue('content')) {
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(
          PhrictionDocumentContentTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('content'));
    }

    $editor = id(new PhrictionTransactionEditor())
      ->setActor($request->getUser())
      ->setContentSource($request->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setDescription((string)$request->getValue('description'));

    try {
      $editor->applyTransactions($doc, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      // TODO - some magical hotness via T5873
      throw $ex;
    }

    return $this->buildDocumentInfoDictionary($doc);
  }

}
