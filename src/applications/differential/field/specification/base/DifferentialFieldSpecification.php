<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Describes and implements the behavior for a custom field on Differential
 * revisions. Along with other configuration, you can extend this class to add
 * custom fields to Differential revisions and commit messages.
 *
 * Generally, you should implement all methods from the storage task and then
 * the methods from one or more interface tasks.
 *
 * @task storage Field Storage
 * @task edit Extending the Revision Edit Interface
 * @task view Extending the Revision View Interface
 */
abstract class DifferentialFieldSpecification {


/* -(  Storage  )------------------------------------------------------------ */


  /**
   * Return a unique string used to key storage of this field's value, like
   * "mycompany.fieldname" or similar.
   *
   * @return string Unique key which identifies this field in auxiliary field
   *                storage. Maximum length is 32.
   * @group storage
   */
  abstract public function getStorageKey();


  /**
   * Return a serialized representation of the field value, appropriate for
   * storing in auxiliary field storage.
   *
   * @return string Serialized field value.
   * @group storage
   */
  abstract public function getValueForStorage();


  /**
   * Set the field's value given a serialized storage value. This is called
   * when the field is loaded; if no data is available, the value will be
   * null.
   *
   * @param string|null Serialized field representation (from
   *                    getValueForStorage) or null if no value has ever been
   *                    stored.
   * @return this
   * @group storage
   */
  abstract public function setValueFromStorage($value);


/* -(  Extending the Revision Edit Interface  )------------------------------ */


  /**
   * @task edit
   */
  public function shouldAppearOnEdit() {
    return false;
  }


  /**
   * @task edit
   */
  public function setValueFromRequest(AphrontRequest $request) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * @task edit
   */
  public function renderEditControl() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * @task edit
   */
  public function validateField() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


/* -(  Extending the Revision View Interface  )------------------------------ */


  /**
   * @task view
   */
  public function shouldAppearOnRevisionView() {
    return false;
  }


  /**
   * @task view
   */
  public function renderLabelForRevisionView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * @task view
   */
  public function renderValueForRevisionView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


}
