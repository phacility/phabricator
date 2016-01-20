<?php

interface PhabricatorSubscribableInterface {

  /**
   * Return true to indicate that the given PHID is automatically subscribed
   * to the object (for example, they are the author or in some other way
   * irrevocably a subscriber). This will, e.g., cause the UI to render
   * "Automatically Subscribed" instead of "Subscribe".
   *
   * @param PHID  PHID (presumably a user) to test for automatic subscription.
   * @return bool True if the object/user is automatically subscribed.
   */
  public function isAutomaticallySubscribed($phid);


  /**
   * Return `true` to indicate that "Subscribers:" should be shown when
   * rendering property lists for this object, or `false` to omit the property.
   *
   * @return bool True to show the "Subscribers:" property.
   */
  public function shouldShowSubscribersProperty();

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorSubscribableInterface  )----------------------------------- */
/*

  public function isAutomaticallySubscribed($phid) {
    return false;
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

*/
