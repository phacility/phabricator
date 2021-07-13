<?php


class EmailEndpointResponseData {
  /** email events (secure and regular) */
  public array $events;
  public int $storyErrors;

  public function __construct(array $events, int $storyErrors) {
    $this->events = $events;
    $this->storyErrors = $storyErrors;
  }


}
