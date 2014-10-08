<?php

// e.g. metadata on Stripe objects.
class Stripe_AttachedObject extends Stripe_Object
{
  /**
   * Updates this object.
   *
   * @param array $properties A mapping of properties to update on this object.
   */
  public function replaceWith($properties)
  {
    $removed = array_diff(array_keys($this->_values), array_keys($properties));
    // Don't unset, but rather set to null so we send up '' for deletion.
    foreach ($removed as $k) {
      $this->$k = null;
    }

    foreach ($properties as $k => $v) {
      $this->$k = $v;
    }
  }
}
