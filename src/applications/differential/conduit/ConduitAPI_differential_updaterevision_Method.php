<?php

final class ConduitAPI_differential_updaterevision_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return pht("Update a Differential revision.");
  }

  public function defineParamTypes() {
    return array(
      'id'        => 'required revisionid',
      'diffid'    => 'required diffid',
      'fields'    => 'required dict',
      'message'   => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => 'Bad diff ID.',
      'ERR_BAD_REVISION' => 'Bad revision ID.',
      'ERR_WRONG_USER' => 'You are not the author of this revision.',
      'ERR_CLOSED' => 'This revision has already been closed.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getValue('diffid')))
      ->executeOne();
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($request->getValue('id')))
      ->needReviewerStatus(true)
      ->needActiveDiffs(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    if ($revision->getStatus() == ArcanistDifferentialRevisionStatus::CLOSED) {
      throw new ConduitException('ERR_CLOSED');
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      DifferentialCustomField::ROLE_COMMITMESSAGEEDIT);

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($revision);
    $field_map = mpull($field_list->getFields(), null, 'getFieldKeyForConduit');

    $xactions = array();

    $xactions[] = id(new DifferentialTransaction())
      ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
      ->setNewValue($diff->getPHID());

    $values = $request->getValue('fields', array());
    foreach ($values as $key => $value) {
      $field = idx($field_map, $key);
      if (!$field) {
        // NOTE: We're just ignoring fields we don't know about. This isn't
        // ideal, but the way the workflow currently works involves us getting
        // several read-only fields, like the revision ID field, which we should
        // just skip.
        continue;
      }

      $role = PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS;
      if (!$field->shouldEnableForRole($role)) {
        throw new Exception(
          pht(
            'Request attempts to update field "%s", but that field can not '.
            'perform transactional updates.',
            $key));
      }

      // TODO: This is fairly similar to PhabricatorCustomField's
      // buildFieldTransactionsFromRequest() method, but that's currently not
      // easy to reuse.

      $transaction_type = $field->getApplicationTransactionType();
      $xaction = id(new DifferentialTransaction())
        ->setTransactionType($transaction_type);

      if ($transaction_type == PhabricatorTransactions::TYPE_CUSTOMFIELD) {
        // For TYPE_CUSTOMFIELD transactions only, we provide the old value
        // as an input.
        $old_value = $field->getOldValueForApplicationTransactions();
        $xaction->setOldValue($old_value);
      }

      // The transaction itself will be validated so this is somewhat
      // redundant, but this validator will sometimes give us a better error
      // message or a better reaction to a bad value type.
      $field->validateCommitMessageValue($value);
      $field->readValueFromCommitMessage($value);

      $xaction
        ->setNewValue($field->getNewValueForApplicationTransactions());

      if ($transaction_type == PhabricatorTransactions::TYPE_CUSTOMFIELD) {
        // For TYPE_CUSTOMFIELD transactions, add the field key in metadata.
        $xaction->setMetadataValue('customfield:key', $field->getFieldKey());
      }

      $metadata = $field->getApplicationTransactionMetadata();
      foreach ($metadata as $meta_key => $meta_value) {
        $xaction->setMetadataValue($meta_key, $meta_value);
      }

      $xactions[] = $xaction;
    }

    $message = $request->getValue('message');
    if (strlen($message)) {
      // This is a little awkward, and should maybe move inside the transaction
      // editor. It largely exists for legacy reasons.
      $first_line = head(phutil_split_lines($message, false));
      $diff->setDescription($first_line);
      $diff->save();

      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new DifferentialTransactionComment())
            ->setContent($message));
    }

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSourceFromConduitRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $editor->applyTransactions($revision, $xactions);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
