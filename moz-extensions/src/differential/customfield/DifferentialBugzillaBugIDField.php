<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Extends Differential with a 'Bugzilla Bug ID' field.
 */
final class DifferentialBugzillaBugIDField
  extends DifferentialStoredCustomField {

    private $proxy;

/* -(  Core Properties and Field Identity  )--------------------------------- */

  public function getFieldKey() {
    return 'differential:bugzilla-bug-id';
  }

  public function getFieldName() {
    return pht('Bugzilla Bug ID');
  }

  public function getFieldKeyForConduit() {
    // Link to DifferentialBugzillaBugIDCommitMessageField
    return 'bugzilla.bug-id';
  }

  public function getFieldDescription() {
    // Rendered in 'Config > Differential > differential.fields'
    return pht('Displays associated Bugzilla Bug ID.');
  }

  public function isFieldEnabled() {
    return true;
  }

  public function canDisableField() {
    // Field can't be switched off in configuration
    return false;
  }

/* -(  ApplicationTransactions  )-------------------------------------------- */

  public function shouldAppearInApplicationTransactions() {
    // Required to be editable
    return true;
  }

/* -(  Edit View  )---------------------------------------------------------- */

  public function shouldAppearInEditView() {
    // Should the field appear in Edit Revision feature
    // If set to false value will not be read from Arcanist commit message.
    // ERR-CONDUIT-CORE: Transaction with key "6" has invalid type
    // "bugzilla.bug-id". This type is not recognized. Valid types are: update,
    // [...]
    return true;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $bug_id = $request->getStr($this->getFieldKey());
    $bug_id = DifferentialBugzillaBugIDValidator::formatBugID($bug_id);
    $this->setValue($bug_id);
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setLabel($this->getFieldName())
      ->setCaption(
        pht('Example: %s', phutil_tag('tt', array(), '2345')))
      ->setName($this->getFieldKey())
      ->setValue($this->getValue(), '');
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type, array $xactions) {

    $errors = parent::validateApplicationTransactions($editor, $type, $xactions);

    foreach($xactions as $xaction) {
      // Get the transactor's ExternalAccount->accountID using the author's phid
      $xaction_author_phid = $xaction->getAuthorPHID();

      // Validate that the user may see the bug they've submitted a revision for
      $validation_errors = DifferentialBugzillaBugIDValidator::validate(
        $xaction->getNewValue(),
        $xaction_author_phid
      );
      // Push errors into the revision save stack
      foreach($validation_errors as $validation_error) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $type,
          '',
          pht($validation_error)
        );
      }
    }

    return $errors;
  }

/* -(  Property View  )------------------------------------------------------ */

  public function shouldAppearInPropertyView() {
    // Should bug id be visible in Differential UI.
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    $bug_id = (int) $this->getValue();

    if($bug_id) {
      return $this->renderFieldLink($bug_id);
    }

    return 'Not provided';
  }

  private function renderFieldLink($value) {
    $bug_link = '';
    if($value) {
      $bug_uri = (string) id(new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
        ->setPath('/show_bug.cgi')
        ->setQueryParam('id', (int) $value);

      $bug_link = phutil_tag('a', array('href' => $bug_uri), $value);
    }

    return $bug_link;
  }

/* -(  List View  )---------------------------------------------------------- */

  // Switched of as renderOnListItem is undefined
  // public function shouldAppearInListView() {
  //   return true;
  // }

  // TODO Find out if/how to implement renderOnListItem
  // It throws Incomplete if not overriden, but doesn't appear anywhere else
  // except of it's definition in `PhabricatorCustomField`

/* -(  Global Search  )------------------------------------------------------ */

  public function shouldAppearInGlobalSearch() {
    return true;
  }

/* -(  Conduit  )------------------------------------------------------------ */

  public function shouldAppearInConduitDictionary() {
    // Should the field appear in `differential.revision.search`
    return true;
  }

  public function shouldAppearInConduitTransactions() {
    // Required if needs to be saved via Conduit (i.e. from `arc diff`)
    return true;
  }

  protected function newConduitSearchParameterType() {
    return new ConduitStringParameterType();
  }

  protected function newConduitEditParameterType() {
    // Define the type of the parameter for Conduit
    return new ConduitStringParameterType();
  }

  public function readFieldValueFromConduit(string $value) {
    return $value;
  }

  public function isFieldEditable() {
    // Has to be editable to be written from `arc diff`
    return true;
  }

  public function shouldDisableByDefault() {
    return false;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {

    if($this->proxy) {
      return $this->proxy->getApplicationTransactionTitle($xaction);
    }

    $author_phid = $xaction->getAuthorPHID();
    $old_value = $xaction->getOldValue();
    $new_value = $xaction->getNewValue();
    $handle_link = $xaction->renderHandleLink($author_phid);

    if($old_value && !$new_value) {
      return pht(
        '%s removed %s %s.',
        $handle_link,
        $this->getFieldName(),
        $this->renderFieldLink($old_value)
      );
    }
    else if(!$old_value && $new_value) {
      return pht(
        '%s added %s %s.',
        $handle_link,
        $this->getFieldName(),
        $this->renderFieldLink($new_value)
      );
    }
    else if($old_value && $new_value) {
      return pht(
        '%s changed the %s from %s to %s.',
        $handle_link,
        $this->getFieldName(),
        $this->renderFieldLink($old_value),
        $this->renderFieldLink($new_value)
      );
    }

    return pht('%s updated the bug number.', $xaction->renderHandleLink($author_phid));
  }
}
