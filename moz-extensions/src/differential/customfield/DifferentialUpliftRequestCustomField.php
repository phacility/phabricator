<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
* Extends Differential with a 'Uplift Request' field.
*/
final class DifferentialUpliftRequestCustomField
    extends DifferentialStoredCustomField {

    // Questions for beta, with defaults.
    const BETA_UPLIFT_FIELDS = array(
        "User impact if declined" => "",
        "Code covered by automated testing" => false,
        "Fix verified in Nightly" => false,
        "Needs manual QE test" => false,
        "Steps to reproduce for manual QE testing" => "",
        "Risk associated with taking this patch" => "",
        "Explanation of risk level" => "",
        "String changes made/needed" => "",
    );

    // How each field is formatted in ReMarkup:
    // a bullet point with text in bold.
    const QUESTION_FORMATTING = "- **%s** %s";

    private $proxy;

    /* -(  Core Properties and Field Identity  )--------------------------------- */

    public function readValueFromRequest(AphrontRequest $request) {
        $uplift_data = $request->getJSONMap($this->getFieldKey());
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

    /* Set QE required flag on the relevant Bugzilla bug. */
    public function setManualQERequiredFlag(int $bug) {
        // Construct request for setting `qe-verify` flag, see
        // https://bmo.readthedocs.io/en/latest/api/core/v1/bug.html#update-bug
        $url = id(new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
            ->setPath('/rest/bug/'.$bug);
        $api_key = PhabricatorEnv::getEnvConfig('bugzilla.automation_api_key');

        // Encode here because `setData` below will fail due to nested arrays.
        $data = phutil_json_encode(
            array(
                'flags' => array(
                    array(
                        'name' => 'qe-verify',
                        'status' => '?',
                    ),
                ),
            ),
        );

        $future = id(new HTTPSFuture($url))
            ->addHeader('Accept', 'application/json')
            ->addHeader('Content-Type', 'application/json')
            ->addHeader('User-Agent', 'Phabricator')
            ->addHeader('X-Bugzilla-API-Key', $api_key)
            ->setData($data)
            ->setMethod('PUT')
            ->setTimeout(PhabricatorEnv::getEnvConfig('bugzilla.timeout'));

        try {
            list($status, $body) = $future->resolve();
            $status_code = (int) $status->getStatusCode();

            # Return an error string and invalidate transaction if Bugzilla can't be contacted.
            $body = phutil_json_decode($body);
            if (array_key_exists("error", $body) && $body["error"]) {
                throw new Exception(
                    'Could not set `qe-verify` on Bugzilla: status code: '.$status_code.'! Please file a bug.'
                );
            }

        } catch (PhutilJSONParserException $ex) {
            throw new Exception(
                'Expected invalid JSON response from BMO while setting `qe-verify` flag.'
            );
        }
    }

    /* Comment the uplift request form on the relevant Bugzilla bug. */
    public function commentUpliftOnBugzilla(int $bug) {
        // Construct request for leaving a comment, see
        // https://bmo.readthedocs.io/en/latest/api/core/v1/comment.html#create-comments
        $url = id(new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
            ->setPath('/rest/bug/'.$bug.'/comment');
        $api_key = PhabricatorEnv::getEnvConfig('bugzilla.automation_api_key');

        $data = array(
            'comment' => $this->getRemarkup(),
            'is_markdown' => true,
            'is_private' => false,
        );

        $future = id(new HTTPSFuture($url))
            ->addHeader('Accept', 'application/json')
            ->addHeader('User-Agent', 'Phabricator')
            ->addHeader('X-Bugzilla-API-Key', $api_key)
            ->setData($data)
            ->setMethod('POST')
            ->setTimeout(PhabricatorEnv::getEnvConfig('bugzilla.timeout'));

        try {
            list($status, $body) = $future->resolve();
            $status_code = (int) $status->getStatusCode();

            # Return an error string and invalidate transaction if Bugzilla can't be contacted.
            $body = phutil_json_decode($body);
            if (array_key_exists("error", $body) && $body["error"]) {
                throw new Exception(
                    'Could not leave a comment on Bugzilla: status code '.$status_code.'! Please file a bug.',
                );
            }

        } catch (PhutilJSONParserException $ex) {
            throw new Exception(
                'Received invalid JSON response from BMO while leaving a comment.'
            );
        }
    }

    /* -(  Edit View  )---------------------------------------------------------- */

    public function shouldAppearInEditView() {
        // Should the field appear in Edit Revision feature
        return true;
    }

    // How the uplift text is rendered in the "Details" section.
    public function renderPropertyViewValue(array $handles) {
        if (empty($this->getValue())) {
            return null;
        }

        return new PHUIRemarkupView($this->getViewer(), $this->getRemarkup());
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
            ->setValue($this->getRemarkup(), '');
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
                PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
            );
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

    // Convert `bool` types to readable text, or return base text.
    private function valueAsAnswer($value): string {
        if ($value === true) {
            return "yes";
        } else if ($value === false) {
            return "no";
        } else {
            return $value;
        }
    }

    private function getRemarkup(): string {
        $questions = array();

        $value = $this->getValue();
        foreach ($value as $question => $answer) {
            $answer_readable = $this->valueAsAnswer($answer);
            $questions[] = sprintf(
                self::QUESTION_FORMATTING, $question, $answer_readable
            );
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
            ->setInitialValue(self::BETA_UPLIFT_FIELDS)
            ->setSubmitButtonText(pht('Request Uplift'));

        return $action;
    }

    public function validateUpliftForm(array $form): array {
        $validation_errors = array();

        # Allow clearing the form.
        if (empty($form)) {
            return $validation_errors;
        }

        foreach($form as $question => $answer) {
            // `empty(false)` is `true` so handle `bool` separately.
            if (is_bool($answer)) {
                continue;
            }

            if (empty($answer)) {
                $validation_errors[] = "Need to answer '$question'";
            }
        }

        return $validation_errors;
    }

    public function qeRequired() {
        return $this->getValue()['Needs manual QE test'];
    }

    public function validateApplicationTransactions(
        PhabricatorApplicationTransactionEditor $editor,
        $type, array $xactions) {

        $errors = parent::validateApplicationTransactions($editor, $type, $xactions);

        $field_is_empty = true;
        foreach($xactions as $xaction) {
            // We can skip validation if the field is empty.
            $new_value = $xaction->getNewValue();
            if (!empty($new_value)) {
                $field_is_empty = false;
                continue;
            }

            // Validate that the form is correctly filled out.
            // This should always be a string (think if the value came from the remarkup edit)
            $validation_errors = $this->validateUpliftForm(
                phutil_json_decode($xaction->getNewValue()),
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

        // No need to check for a bug if we haven't submitted the form, or are clearing it.
        if ($field_is_empty) {
            return $errors;
        }

        // Similar idea to `BugStore::resolveBug`.
        $bugzillaField = new DifferentialBugzillaBugIDField();
        $bugzillaField->setObject($this->getObject());
        (new PhabricatorCustomFieldStorageQuery())
            ->addField($bugzillaField)
            ->execute();
        $bug = $bugzillaField->getValue();

        // Assert we have a bug attached to the revision.
        if (!$bug) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                '',
                pht("Can't submit an uplift request without a bug."),
            );
        }

        return $errors;
    }

    // Update Bugzilla when applying effects.
    public function applyApplicationTransactionExternalEffects(
        PhabricatorApplicationTransaction $xaction
    ) {
        $ret = parent::applyApplicationTransactionExternalEffects($xaction);

        // Similar idea to `BugStore::resolveBug`.
        // We should have already checked for the bug during validation.
        $bugzillaField = new DifferentialBugzillaBugIDField();
        $bugzillaField->setObject($this->getObject());
        (new PhabricatorCustomFieldStorageQuery())
            ->addField($bugzillaField)
            ->execute();
        $bug = $bugzillaField->getValue();

        // Always comment the uplift form.
        $this->commentUpliftOnBugzilla($bug);

        // If QE is required, set the Bugzilla flag.
        if ($this->qeRequired()) {
            $this->setManualQERequiredFlag($bug);
        }

        return $ret;
    }

    // When storing the value convert the question => answer mapping to a JSON string.
    public function getValueForStorage(): string {
        return phutil_json_encode($this->getValue());
    }

    public function setValueFromStorage($value) {
        try {
            $this->setValue(phutil_json_decode($value));
        } catch (PhutilJSONParserException $ex) {
            $this->setValue(array());
        }
        return $this;
    }

    public function setValueFromApplicationTransactions($value) {
        $this->setValue($value);
        return $this;
    }

    public function setValue($value) {
        if (!is_array($value)) {
            parent::setValue(phutil_json_decode($value));
        } else {
            parent::setValue($value);
        }
    }


    /* -(  Property View  )------------------------------------------------------ */

    public function shouldAppearInPropertyView() {
        return true;
    }

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

