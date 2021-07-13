<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Extends Commit Message with a 'Bugzilla Bug ID' field.
 */
final class DifferentialBugzillaBugIDCommitMessageField
  extends DifferentialCommitMessageCustomField {

  // returned with
  // `DifferentialCommitMessageField::getCommitMessageFieldKey`
  // to provide error messages
  // and then in `DifferentialCommitMessageParser::setCommitMessageFields`
  // to prepare the fields and then save in
  // `DifferentialRevisionTransactionType`
  const FIELDKEY = 'bugzilla.bug-id';
  const CUSTOM_FIELD_KEY = 'differential:bugzilla-bug-id';

  /* -- Commit Message Field descriptions ---------------------------- */

  public function getFieldName() {
    return pht('Bug #');
  }

  public function getCustomFieldKey() {
    // Link to DifferentialBugzillaBugIDField.
    return self::CUSTOM_FIELD_KEY;
  }

  // Should Label appear in Arcanist message
  public function isFieldEditable() {
    return true;
  }

  public function getFieldAliases() {
    // Possible alternative labels to be parsed for in CVS or Arcanist
    // commit message.
    return array(
      'Bugzilla Bug ID',
      'Bugzilla',
    );
  }

  /* -- Parsing commits --------------------------------------------- */

  public function validateFieldValue($bug_id) {

    // Get the transactor's phid
    $author_phid = $this->getViewer()->getPHID();

    $bug_id = DifferentialBugzillaBugIDValidator::formatBugID($bug_id);
    $errors = DifferentialBugzillaBugIDValidator::validate($bug_id, $author_phid);

    foreach($errors as $error) {
      $this->raiseValidationException($error);
    }

    return $bug_id;
  }
}
