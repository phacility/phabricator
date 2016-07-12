<?php

final class PhabricatorSubscriptionsEditor extends PhabricatorEditor {

  private $object;

  private $explicitSubscribePHIDs = array();
  private $implicitSubscribePHIDs = array();
  private $unsubscribePHIDs       = array();

  public function setObject(PhabricatorSubscribableInterface $object) {
    $this->object = $object;
    return $this;
  }

  /**
   * Add explicit subscribers. These subscribers have explicitly subscribed
   * (or been subscribed) to the object, and will be added even if they
   * had previously unsubscribed.
   *
   * @param list<phid>  List of PHIDs to explicitly subscribe.
   * @return this
   */
  public function subscribeExplicit(array $phids) {
    $this->explicitSubscribePHIDs += array_fill_keys($phids, true);
    return $this;
  }


  /**
   * Add implicit subscribers. These subscribers have taken some action which
   * implicitly subscribes them (e.g., adding a comment) but it will be
   * suppressed if they've previously unsubscribed from the object.
   *
   * @param list<phid>  List of PHIDs to implicitly subscribe.
   * @return this
   */
  public function subscribeImplicit(array $phids) {
    $this->implicitSubscribePHIDs += array_fill_keys($phids, true);
    return $this;
  }


  /**
   * Unsubscribe PHIDs and mark them as unsubscribed, so implicit subscriptions
   * will not resubscribe them.
   *
   * @param list<phid>  List of PHIDs to unsubscribe.
   * @return this
   */
  public function unsubscribe(array $phids) {
    $this->unsubscribePHIDs += array_fill_keys($phids, true);
    return $this;
  }


  public function save() {
    if (!$this->object) {
      throw new PhutilInvalidStateException('setObject');
    }
    $actor = $this->requireActor();

    $src = $this->object->getPHID();

    if ($this->implicitSubscribePHIDs) {
      $unsub = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $src,
        PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST);
      $unsub = array_fill_keys($unsub, true);
      $this->implicitSubscribePHIDs = array_diff_key(
        $this->implicitSubscribePHIDs,
        $unsub);
    }

    $add = $this->implicitSubscribePHIDs + $this->explicitSubscribePHIDs;
    $del = $this->unsubscribePHIDs;

    // If a PHID is marked for both subscription and unsubscription, treat
    // unsubscription as the stronger action.
    $add = array_diff_key($add, $del);

    if ($add || $del) {
      $u_type = PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST;
      $s_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;

      $editor = new PhabricatorEdgeEditor();

      foreach ($add as $phid => $ignored) {
        $editor->removeEdge($src, $u_type, $phid);
        $editor->addEdge($src, $s_type, $phid);
      }

      foreach ($del as $phid => $ignored) {
        $editor->removeEdge($src, $s_type, $phid);
        $editor->addEdge($src, $u_type, $phid);
      }

      $editor->save();
    }
  }

}
