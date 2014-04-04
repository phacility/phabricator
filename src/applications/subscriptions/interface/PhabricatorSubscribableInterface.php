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


  /**
   * Return `true` to indicate that the "Subscribe" action should be shown and
   * enabled when rendering action lists for this object, or `false` to omit
   * the action.
   *
   * @param   phid  Viewing or acting user PHID.
   * @return  bool  True to allow the user to subscribe.
   */
  public function shouldAllowSubscription($phid);

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

  public function shouldAllowSubscription($phid) {
    return true;
  }

*/
