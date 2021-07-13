<?php


class EmailEndpointResponseCursor {
  public int $limit;
  public ?string $after;

  public function __construct(int $limit, ?string $after) {
    $this->limit = $limit;
    $this->after = $after;
  }
}