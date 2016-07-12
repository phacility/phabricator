<?php

final class SubscriptionListStringBuilder extends Phobject {

  private $handles;
  private $objectPHID;

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function buildTransactionString($change_type) {
    $handles = $this->getHandles();
    if (!$handles) {
      return;
    }
    $list_uri = '/subscriptions/transaction/'.
                $change_type.'/'.
                $this->getObjectPHID().'/';
    return $this->buildString($list_uri);
  }

  public function buildPropertyString() {
    $handles = $this->getHandles();

    if (!$handles) {
      return phutil_tag('em', array(), pht('None'));
    }
    $list_uri = '/subscriptions/list/'.$this->getObjectPHID().'/';
    return $this->buildString($list_uri);
  }

  private function buildString($list_uri) {
    $handles = $this->getHandles();

    // Always show this many subscribers.
    $show_count = 3;
    $subscribers_count = count($handles);

    // It looks a bit silly to render "a, b, c, and 1 other", since we could
    // have just put that other subscriber there in place of the "1 other"
    // link. Instead, render "a, b, c, d" in this case, and then when we get one
    // more render "a, b, c, and 2 others".
    if ($subscribers_count <= ($show_count + 1)) {
      return phutil_implode_html(', ', mpull($handles, 'renderHovercardLink'));
    }

    $show = array_slice($handles, 0, $show_count);
    $show = array_values($show);

    $not_shown_count = $subscribers_count - $show_count;
    $not_shown_txt = pht('%d other(s)', $not_shown_count);
    $not_shown_link = javelin_tag(
      'a',
      array(
        'href' => $list_uri,
        'sigil' => 'workflow',
      ),
      $not_shown_txt);

    return pht(
      '%s, %s, %s and %s',
      $show[0]->renderLink(),
      $show[1]->renderLink(),
      $show[2]->renderLink(),
      $not_shown_link);
  }

}
