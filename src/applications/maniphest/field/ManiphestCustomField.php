<?php

abstract class ManiphestCustomField
  extends PhabricatorCustomField {

  public function newStorageObject() {
    return new ManiphestCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new ManiphestCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new ManiphestCustomFieldNumericIndex();
  }

  /**
   * When the user creates a task, the UI prompts them to "Create another
   * similar task". This copies some fields (e.g., Owner and CCs) but not other
   * fields (e.g., description). If this custom field should also be copied,
   * return true from this method.
   *
   * @return bool True to copy the default value from the template task when
   *              creating a new similar task.
   */
  public function shouldCopyWhenCreatingSimilarTask() {
    return false;
  }

  // TODO: All of this is legacy junk.
  public function getRequiredHandlePHIDs() {
    return array();
  }

  public function setHandles(array $handles) {
  }

  public function isRequired() {
    return false;
  }

  public function renderControl() {
    return $this->renderEditControl();
  }

  public function validate() {
    return true;
  }

  /**
   * Render a verb to appear in email titles when a transaction involving this
   * field occurs. Specifically, Maniphest emails are formatted like this:
   *
   *   [Maniphest] [Verb Here] TNNN: Task title here
   *                ^^^^^^^^^
   *
   * You should optionally return a title-case verb or short phrase like
   * "Created", "Retitled", "Closed", "Resolved", "Commented On",
   * "Lowered Priority", etc., which describes the transaction.
   *
   * @param ManiphestTransaction The transaction which needs description.
   * @return string|null A short description of the transaction.
   */
  public function renderTransactionEmailVerb(
    ManiphestTransaction $transaction) {
    return null;
  }


  /**
   * Render a short description of the transaction, to appear above comments
   * in the Maniphest transaction log. The string will be rendered after the
   * acting user's name. Examples are:
   *
   *    added a comment
   *    added alincoln to CC
   *    claimed this task
   *    created this task
   *    closed this task out of spite
   *
   * You should return a similar string, describing the transaction.
   *
   * Note the ##$target## parameter -- Maniphest needs to render transaction
   * descriptions for different targets, like web and email. This method will
   * be called with a ##ManiphestAuxiliaryFieldSpecification::RENDER_TARGET_*##
   * constant describing the intended target.
   *
   * @param ManiphestTransaction The transaction which needs description.
   * @param const Constant describing the rendering target (e.g., html or text).
   * @return string|null Description of the transaction.
   */
  public function renderTransactionDescription(
    ManiphestTransaction $transaction,
    $target) {
    return 'updated a custom field';
  }

  public function getMarkupFields() {
    return array();
  }


}
