<?php

final class PhabricatorProjectColumnStatusOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'status';

  public function getDisplayName() {
    return pht('Group by Status');
  }

  protected function newMenuIconIcon() {
    return 'fa-check';
  }

  public function getHasHeaders() {
    return true;
  }

  public function getCanReorder() {
    return true;
  }

  public function getMenuOrder() {
    return 4000;
  }

  protected function newHeaderKeyForObject($object) {
    return $this->newHeaderKeyForStatus($object->getStatus());
  }

  private function newHeaderKeyForStatus($status) {
    return sprintf('status(%s)', $status);
  }

  protected function newSortVectorsForObjects(array $objects) {
    $status_sequence = $this->newStatusSequence();

    $vectors = array();
    foreach ($objects as $object_key => $object) {
      $vectors[$object_key] = array(
        (int)idx($status_sequence, $object->getStatus(), 0),
      );
    }

    return $vectors;
  }

  private function newStatusSequence() {
    $statuses = ManiphestTaskStatus::getTaskStatusMap();
    return array_combine(
      array_keys($statuses),
      range(1, count($statuses)));
  }

  protected function newHeadersForObjects(array $objects) {
    $headers = array();

    $statuses = ManiphestTaskStatus::getTaskStatusMap();
    $sequence = $this->newStatusSequence();

    foreach ($statuses as $status_key => $status_name) {
      $header_key = $this->newHeaderKeyForStatus($status_key);

      $sort_vector = array(
        (int)idx($sequence, $status_key, 0),
      );

      $status_icon = ManiphestTaskStatus::getStatusIcon($status_key);
      $status_color = ManiphestTaskStatus::getStatusColor($status_key);

      $icon_view = id(new PHUIIconView())
        ->setIcon($status_icon, $status_color);

      $drop_effect = $this->newEffect()
        ->setIcon($status_icon)
        ->setColor($status_color)
        ->addCondition('status', '!=', $status_key)
        ->setContent(
          pht(
            'Change status to %s.',
            phutil_tag('strong', array(), $status_name)));

      $header = $this->newHeader()
        ->setHeaderKey($header_key)
        ->setSortVector($sort_vector)
        ->setName($status_name)
        ->setIcon($icon_view)
        ->setEditProperties(
          array(
            'value' => $status_key,
          ))
        ->addDropEffect($drop_effect);

      $headers[] = $header;
    }

    return $headers;
  }

  protected function newColumnTransactions($object, array $header) {
    $new_status = idx($header, 'value');

    if ($object->getStatus() === $new_status) {
      return null;
    }

    $xactions = array();
    $xactions[] = $this->newTransaction($object)
      ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
      ->setNewValue($new_status);

    return $xactions;
  }

}
