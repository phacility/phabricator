<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

final class DifferentialUpliftRequestCustomFieldTestCase
  extends PhabricatorTestCase {

  public function testFormValidation() {
        $field = new DifferentialUpliftRequestCustomField();
        // Ensure the field can't be filled without answering all questions
        $errors = $field->validateUpliftForm("=== junk ===");

        $expected = array();
        foreach(DifferentialUpliftRequestCustomField::BETA_UPLIFT_FIELDS as $err) {
            $expected[] = "Missing the '$err' field";
        }
        $this->assertEqual($expected, $errors);

        // Ensure the field can be set as empty
        $errors = $field->validateUpliftForm("");
        $this->assertEqual(
            array(),
            $errors,
            "The empty form leads to errors - should be allowed.");
  }
}
