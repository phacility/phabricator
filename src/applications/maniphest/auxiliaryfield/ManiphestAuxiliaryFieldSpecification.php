<?php

/**
 * @group maniphest
 */
abstract class ManiphestAuxiliaryFieldSpecification {

  const RENDER_TARGET_HTML  = 'html';
  const RENDER_TARGET_TEXT  = 'text';

  private $label;
  private $auxiliaryKey;
  private $caption;
  private $value;

  public function setLabel($val) {
    $this->label = $val;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setAuxiliaryKey($val) {
    $this->auxiliaryKey = $val;
    return $this;
  }

  public function getAuxiliaryKey() {
    return $this->auxiliaryKey;
  }

  public function setCaption($val) {
    $this->caption = $val;
    return $this;
  }

  public function getCaption() {
    return $this->caption;
  }

  public function setValue($val) {
    $this->value = $val;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function validate() {
    return true;
  }

  public function isRequired() {
    return false;
  }

  public function setType($val) {
    $this->type = $val;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function renderControl() {
    return null;
  }

  public function renderForDetailView() {
    return phutil_escape_html($this->getValue());
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


}
