<?php

final class PhabricatorProjectColumnPriorityOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'priority';

  public function getDisplayName() {
    return pht('Group by Priority');
  }

  protected function newMenuIconIcon() {
    return 'fa-sort-numeric-asc';
  }

  public function getHasHeaders() {
    return true;
  }

  public function getCanReorder() {
    return true;
  }

  public function getMenuOrder() {
    return 1000;
  }

  protected function newHeaderKeyForObject($object) {
    return $this->newHeaderKeyForPriority($object->getPriority());
  }

  private function newHeaderKeyForPriority($priority) {
    return sprintf('priority(%d)', $priority);
  }

  protected function newSortVectorForObject($object) {
    return $this->newSortVectorForPriority($object->getPriority());
  }

  private function newSortVectorForPriority($priority) {
    return array(
      -1 * (int)$priority,
    );
  }

  protected function newHeadersForObjects(array $objects) {
    $priorities = ManiphestTaskPriority::getTaskPriorityMap();

    // It's possible for tasks to have an invalid/unknown priority in the
    // database. We still want to generate a header for these tasks so we
    // don't break the workboard.
    $priorities = $priorities + mpull($objects, null, 'getPriority');

    $priorities = array_keys($priorities);

    $headers = array();
    foreach ($priorities as $priority) {
      $header_key = $this->newHeaderKeyForPriority($priority);
      $sort_vector = $this->newSortVectorForPriority($priority);

      $priority_name = ManiphestTaskPriority::getTaskPriorityName($priority);
      $priority_color = ManiphestTaskPriority::getTaskPriorityColor($priority);
      $priority_icon = ManiphestTaskPriority::getTaskPriorityIcon($priority);

      $icon_view = id(new PHUIIconView())
        ->setIcon($priority_icon, $priority_color);

      $drop_effect = $this->newEffect()
        ->setIcon($priority_icon)
        ->setColor($priority_color)
        ->addCondition('priority', '!=', $priority)
        ->setContent(
          pht(
            'Change priority to %s.',
            phutil_tag('strong', array(), $priority_name)));

      $header = $this->newHeader()
        ->setHeaderKey($header_key)
        ->setSortVector($sort_vector)
        ->setName($priority_name)
        ->setIcon($icon_view)
        ->setEditProperties(
          array(
            'value' => (int)$priority,
          ))
        ->addDropEffect($drop_effect);

      $headers[] = $header;
    }

    return $headers;
  }

  protected function newColumnTransactions($object, array $header) {
    $new_priority = idx($header, 'value');

    if ($object->getPriority() === $new_priority) {
      return null;
    }

    $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
    $keyword = head(idx($keyword_map, $new_priority));

    $xactions = array();
    $xactions[] = $this->newTransaction($object)
      ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
      ->setNewValue($keyword);

    return $xactions;
  }


}
