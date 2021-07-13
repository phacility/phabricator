<?php


class StoryQueryResult {
  public string $lastKey;
  /** @var PhabricatorStory[] */
  public array $stories;

  /**
   * @param string $lastKey
   * @param PhabricatorStory[] $stories
   */
  public function __construct(string $lastKey, array $stories) {
    $this->lastKey = $lastKey;
    $this->stories = $stories;
  }


}