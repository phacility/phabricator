<?php

final class NuanceItemQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $sourcePHIDs;
  private $queuePHIDs;
  private $itemTypes;
  private $itemKeys;
  private $containerKeys;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withSourcePHIDs(array $source_phids) {
    $this->sourcePHIDs = $source_phids;
    return $this;
  }

  public function withQueuePHIDs(array $queue_phids) {
    $this->queuePHIDs = $queue_phids;
    return $this;
  }

  public function withItemTypes(array $item_types) {
    $this->itemTypes = $item_types;
    return $this;
  }

  public function withItemKeys(array $item_keys) {
    $this->itemKeys = $item_keys;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withItemContainerKeys(array $container_keys) {
    $this->containerKeys = $container_keys;
    return $this;
  }

  public function newResultObject() {
    return new NuanceItem();
  }

  protected function willFilterPage(array $items) {
    $viewer = $this->getViewer();
    $source_phids = mpull($items, 'getSourcePHID');

    $sources = id(new NuanceSourceQuery())
      ->setViewer($viewer)
      ->withPHIDs($source_phids)
      ->execute();
    $sources = mpull($sources, null, 'getPHID');

    foreach ($items as $key => $item) {
      $source = idx($sources, $item->getSourcePHID());
      if (!$source) {
        $this->didRejectResult($items[$key]);
        unset($items[$key]);
        continue;
      }
      $item->attachSource($source);
    }

    $type_map = NuanceItemType::getAllItemTypes();
    foreach ($items as $key => $item) {
      $type = idx($type_map, $item->getItemType());
      if (!$type) {
        $this->didRejectResult($items[$key]);
        unset($items[$key]);
        continue;
      }
      $item->attachImplementation($type);
    }

    $queue_phids = array();
    foreach ($items as $item) {
      $queue_phid = $item->getQueuePHID();
      if ($queue_phid) {
        $queue_phids[$queue_phid] = $queue_phid;
      }
    }

    if ($queue_phids) {
      $queues = id(new NuanceQueueQuery())
        ->setViewer($viewer)
        ->withPHIDs($queue_phids)
        ->execute();
      $queues = mpull($queues, null, 'getPHID');
    } else {
      $queues = array();
    }

    foreach ($items as $key => $item) {
      $queue_phid = $item->getQueuePHID();

      if (!$queue_phid) {
        $item->attachQueue(null);
        continue;
      }

      $queue = idx($queues, $queue_phid);

      if (!$queue) {
        unset($items[$key]);
        $this->didRejectResult($item);
        continue;
      }

      $item->attachQueue($queue);
    }

    return $items;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->sourcePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'sourcePHID IN (%Ls)',
        $this->sourcePHIDs);
    }

    if ($this->queuePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'queuePHID IN (%Ls)',
        $this->queuePHIDs);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->itemTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'itemType IN (%Ls)',
        $this->itemTypes);
    }

    if ($this->itemKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'itemKey IN (%Ls)',
        $this->itemKeys);
    }

    if ($this->containerKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'itemContainerKey IN (%Ls)',
        $this->containerKeys);
    }

    return $where;
  }

}
