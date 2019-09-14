<?php

abstract class PhabricatorSystemAction extends Phobject {

  final public function getActionConstant() {
    return $this->getPhobjectClassConstant('TYPECONST', 32);
  }

  abstract public function getScoreThreshold();

  public function shouldBlockActor($actor, $score) {
    return ($score > $this->getScoreThreshold());
  }

  public function getLimitExplanation() {
    return pht('You are performing too many actions too quickly.');
  }

  public function getRateExplanation($score) {
    return pht(
      'The maximum allowed rate for this action is %s. You are taking '.
      'actions at a rate of %s.',
      $this->formatRate($this->getScoreThreshold()),
      $this->formatRate($score));
  }

  protected function formatRate($rate) {
    if ($rate > 10) {
      $str = pht('%d / second', $rate);
    } else {
      $rate *= 60;
      if ($rate > 10) {
        $str = pht('%d / minute', $rate);
      } else {
        $rate *= 60;
        $str = pht('%d / hour', $rate);
      }
    }

    return phutil_tag('strong', array(), $str);
  }

}
