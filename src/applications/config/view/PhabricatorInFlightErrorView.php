<?php

final class PhabricatorInFlightErrorView extends AphrontView {

  private $message;

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

  public function render() {
    return phutil_tag(
      'div',
      array(
        'class' => 'in-flight-error-detail',
      ),
      array(
        phutil_tag(
          'h1',
          array(
            'class' => 'in-flight-error-title',
          ),
          pht('A Troublesome Encounter!')),
        phutil_tag(
          'div',
          array(
            'class' => 'in-flight-error-body',
          ),
          pht(
            'Woe! This request had its journey cut short by unexpected '.
            'circumstances (%s).',
            $this->getMessage())),
      ));
  }

}
