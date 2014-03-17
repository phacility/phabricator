<?php

final class SubscriptionListStringBuilder {

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

    $html = array();
    $show_count = 3;
    $subscribers_count = count($handles);
    if ($subscribers_count <= $show_count) {
      return phutil_implode_html(', ', mpull($handles, 'renderLink'));
    }

    $args = array('%s, %s, %s, and %s');
    $shown = 0;
    foreach ($handles as $handle) {
      $shown++;
      if ($shown > $show_count) {
        break;
      }
      $args[] = $handle->renderLink();
    }
    $not_shown_count = $subscribers_count - $show_count;
    $not_shown_txt = pht('%d other(s)', $not_shown_count);
    $args[] = javelin_tag(
      'a',
      array(
        'href' => $list_uri,
        'sigil' => 'workflow'
      ),
      $not_shown_txt);

    return call_user_func_array('pht', $args);
  }

}
