<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Extends Differential with a 'Uplift Request' field.
 */
final class DifferentialUpliftRequestCustomField
  extends DifferentialStoredCustomField {

  const BETA_UPLIFT_FIELDS = array(
    "User impact if declined",
    "Code covered by automated testing",
    "Fix verified in Nightly",
    "Needs manual QE test",
    "Steps to reproduce for manual QE testing",
    "Risk associated with taking this patch",
    "Explanation of risk level",
    "String changes made/needed",
  );

  // How each field is formatted in ReMarkup.
  const QUESTION_FORMATTING = "==== %s ====";

  private $proxy;

/* -(  Core Properties and Field Identity  )--------------------------------- */

  public function readValueFromRequest(AphrontRequest $request) {
    $uplift_data = $request->getStr($this->getFieldKey());
    $this->setValue($uplift_data);
  }

  public function getFieldKey() {
    return 'differential:uplift-request';
  }

  public function getFieldKeyForConduit() {
    return 'uplift.request';
  }

  public function getFieldValue() {
    return $this->getValue();
  }

  public function getFieldName() {
    return pht('Uplift Request form');
  }

  public function getFieldDescription() {
    // Rendered in 'Config > Differential > differential.fields'
    return pht('Renders uplift request form.');
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
    return true;
  }

  // How the uplift text is rendered in the "Details" section.
  public function renderPropertyViewValue(array $handles) {
    if (!strlen($this->getValue())) {
      return null;
    }

    return new PHUIRemarkupView($this->getViewer(), pht($this->getValue()));
  }

  // How the field can be edited in the "Edit Revision" menu.
  public function renderEditControl(array $handles) {
    if (!$this->isUpliftTagSet()) {
        return null; 
    }

    return id(new PhabricatorRemarkupControl())
      ->setLabel($this->getFieldName())
      ->setCaption(pht('Please answer all questions.'))
      ->setName($this->getFieldKey())
      ->setValue($this->getValue(), '');
  }

  // -- Comment action things

  public function getCommentActionLabel() {
    return pht('Request Uplift');
  }

  // Return `true` if the `uplift` tag is set on the repository belonging to
  // this revision.
  private function isUpliftTagSet() {
    $revision = $this->getObject();
    $viewer = $this->getViewer();

    if ($revision == null || $viewer == null) {
        return false;
    }

    try {
        $repository_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $revision->getFieldValuesForConduit()['repositoryPHID'],
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    } catch (Exception $e) {
      return false;
    }

    if (!(bool)$repository_projects) {
      return false;
    }

    $uplift_project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withNames(array('uplift'))
      ->executeOne();

    // The `uplift` project isn't created or can't be found.
    if (!(bool)$uplift_project) {
        return false;
    }

    // If the `uplift` project PHID is in the set of all project PHIDs
    // attached to the repo, return `true`.
    if (in_array($uplift_project->getPHID(), $repository_projects)) {
      return true;
    }

    return false;
  }

    private function getUpliftFormQuestions() {
        $questions = array();

        foreach (self::BETA_UPLIFT_FIELDS as $section) {
            $questions[] = sprintf(self::QUESTION_FORMATTING, $section);
            $questions[] = "\n";
        }

        return implode("\n", $questions);
    }

  public function newCommentAction() {
    // Returning `null` causes no comment action to render, effectively
    // "disabling" the field.
    if (!$this->isUpliftTagSet()) {
        return null;
    }

    $action = id(new PhabricatorUpdateUpliftCommentAction())
      ->setConflictKey('revision.action')
      ->setValue($this->getValue())
      ->setInitialValue($this->getUpliftFormQuestions())
      ->setSubmitButtonText(pht('Request Uplift'));

    return $action;
  }

  public function validateUpliftForm($form) {
    $validation_errors = array();

    # Allow clearing the form.
    if (empty($form)) {
      return $validation_errors;
    }

    # Check each question in the form is present as a header
    # in the field.
    foreach(self::BETA_UPLIFT_FIELDS as $section) {
      if (strpos($form, sprintf(self::QUESTION_FORMATTING, $section)) === false) {
        $validation_errors[] = "Missing the '$section' field";
      }
    }

    return $validation_errors;
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type, array $xactions) {

    $errors = parent::validateApplicationTransactions($editor, $type, $xactions);

    foreach($xactions as $xaction) {
      // Validate that the form is correctly filled out
      $validation_errors = $this->validateUpliftForm(
        $xaction->getNewValue(),
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
    return true;
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

  // TODO see what this controls and consider using it
  public function shouldDisableByDefault() {
    return false;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return false;
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {

    if($this->proxy) {
      return $this->proxy->getApplicationTransactionTitle($xaction);
    }

    $author_phid = $xaction->getAuthorPHID();

    return pht('%s updated the uplift request field.', $xaction->renderHandleLink($author_phid));
  }
}

