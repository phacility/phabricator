<?php


class EmailEvent {
  public string $key;
  /** time of event in seconds since epoch */
  public int $timestamp;
  public bool $isSecure;
  public MinimalEmailContext $minimalContext;
  /** @var PublicEmailContext|SecureEmailContext|null */
  public $context;

  /**
   * @param string $key
   * @param int $timestamp
   * @param bool $isSecure
   * @param MinimalEmailContext $minimalContext
   * @param PublicEmailContext|SecureEmailContext|null $context
   */
  public function __construct(string $key, int $timestamp, bool $isSecure, MinimalEmailContext $minimalContext, $context)
  {
    $this->key = $key;
    $this->timestamp = $timestamp;
    $this->isSecure = $isSecure;
    $this->minimalContext = $minimalContext;
    $this->context = $context;
  }

}