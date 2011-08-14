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
 * @task conduit Extending the Conduit View Interface
 * @task context Contextual Data
 */
abstract class DifferentialFieldSpecification {

  private $revision;
  private $diff;


/* -(  Storage  )------------------------------------------------------------ */


  /**
   * Return a unique string used to key storage of this field's value, like
   * "mycompany.fieldname" or similar. You can return null (the default) to
   * indicate that this field does not use any storage. This is appropriate for
   * display fields, like @{class:DifferentialLinesFieldSpecification}. If you
   * implement this, you must also implement @{method:getValueForStorage} and
   * @{method:setValueFromStorage}.
   *
   * @return string|null  Unique key which identifies this field in auxiliary
   *                      field storage. Maximum length is 32. Alternatively,
   *                      null (default) to indicate that this field does not
   *                      use auxiliary field storage.
   * @task storage
   */
  public function getStorageKey() {
    return null;
  }


  /**
   * Return a serialized representation of the field value, appropriate for
   * storing in auxiliary field storage. You must implement this method if
   * you implement @{method:getStorageKey}.
   *
   * @return string Serialized field value.
   * @task storage
   */
  public function getValueForStorage() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Set the field's value given a serialized storage value. This is called
   * when the field is loaded; if no data is available, the value will be
   * null. You must implement this method if you implement
   * @{method:getStorageKey}.
   *
   * @param string|null Serialized field representation (from
   *                    @{method:getValueForStorage}) or null if no value has
   *                    ever been stored.
   * @return this
   * @task storage
   */
  public function setValueFromStorage($value) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


/* -(  Extending the Revision Edit Interface  )------------------------------ */


  /**
   * Determine if this field should appear on the "Edit Revision" interface. If
   * you return true from this method, you must implement
   * @{method:setValueFromRequest}, @{method:renderEditControl} and
   * @{method:validateField}.
   *
   * For a concrete example of a field which implements an edit interface, see
   * @{class:DifferentialRevertPlanFieldSpecification}.
   *
   * @return bool True to indicate that this field implements an edit interface.
   * @task edit
   */
  public function shouldAppearOnEdit() {
    return false;
  }


  /**
   * Set the field's value from an HTTP request. Generally, you should read
   * the value of some field name you emitted in @{method:renderEditControl}
   * and save it into the object, e.g.:
   *
   *   $this->value = $request->getStr('my-custom-field');
   *
   * If you have some particularly complicated field, you may need to read
   * more data; this is why you have access to the entire request.
   *
   * You must implement this if you implement @{method:shouldAppearOnEdit}.
   *
   * You should not perform field validation here; instead, you should implement
   * @{method:validateField}.
   *
   * @param AphrontRequest HTTP request representing a user submitting a form
   *                       with this field in it.
   * @return this
   * @task edit
   */
  public function setValueFromRequest(AphrontRequest $request) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Build a renderable object (generally, some @{class:AphrontFormControl})
   * which can be appended to a @{class:AphrontFormView} and represents the
   * interface the user sees on the "Edit Revision" screen when interacting
   * with this field.
   *
   * For example:
   *
   *   return id(new AphrontFormTextControl())
   *     ->setLabel('Custom Field')
   *     ->setName('my-custom-key')
   *     ->setValue($this->value);
   *
   * You must implement this if you implement @{method:shouldAppearOnEdit}.
   *
   * @return AphrontView|string Something renderable.
   * @task edit
   */
  public function renderEditControl() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * This method will be called after @{method:setValueFromRequest} but before
   * the field is saved. It gives you an opportunity to inspect the field value
   * and throw a @{class:DifferentialFieldValidationException} if there is a
   * problem with the value the user has provided (for example, the value the
   * user entered is not correctly formatted).
   *
   * By default, fields are not validated.
   *
   * @return void
   * @task edit
   */
  public function validateField() {
    return;
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


/* -(  Extending the Conduit Interface  )------------------------------------ */


  /**
   * @task conduit
   */
  public function shouldAppearOnConduitView() {
    return false;
  }

  /**
   * @task conduit
   */
  public function getValueForConduit() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * @task conduit
   */
  public function getKeyForConduit() {
    $key = $this->getStorageKey();
    if ($key === null) {
      throw new DifferentialFieldSpecificationIncompleteException($this);
    }
    return $key;
  }


/* -(  Contextual Data  )---------------------------------------------------- */

  /**
   * @task context
   */
  final public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  /**
   * @task context
   */
  final public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  /**
   * @task context
   */
  final protected function getRevision() {
    if (empty($this->revision)) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->revision;
  }

  /**
   * @task context
   */
  final protected function getDiff() {
    if (empty($this->diff)) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->diff;
  }

}
