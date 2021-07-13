<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

final class DifferentialBugzillaBugIDCustomFieldTestCase
  extends PhabricatorTestCase {

  public function testBugIdCommitMessageFieldBasicValidation() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('bugzilla.require_bugs', true);

    $invalids = array(null, '', ' ', '12.3', '1e3');

    $field = new DifferentialBugzillaBugIDCommitMessageField();
    $field->setViewer(PhabricatorUser::getOmnipotentUser());

    foreach ($invalids as $value) {
      $caught = false;
      try {
        $result = $field->validateFieldValue($value);
      } catch (DifferentialFieldValidationException $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
          ($caught instanceof DifferentialFieldValidationException));
    }
  }

  public function testBugIdPropertyViewRendering() {
    $field = new DifferentialBugzillaBugIDField();
    $field->setValue('123');
    $this->assertEqual(
      $field->renderPropertyViewValue(array())->getHTMLContent(),
      '<a href="'.PhabricatorEnv::getEnvConfig('bugzilla.url').'/show_bug.cgi?id=123">123</a>'
    );
  }

  public function testLinkBetweenCommitMessageAndCustomField() {
    $cm_field = new DifferentialBugzillaBugIDCommitMessageField();
    $custom_field = new DifferentialBugzillaBugIDField();
    $this->assertEqual(
      $cm_field->getCustomFieldKey(),
      $custom_field->getFieldKey()
    );
    $this->assertEqual(
      $custom_field->getFieldKeyForConduit(),
      DifferentialBugzillaBugIDCommitMessageField::FIELDKEY
    );
  }
}
