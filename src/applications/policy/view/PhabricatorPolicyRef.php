<?php

final class PhabricatorPolicyRef
  extends Phobject {

  private $viewer;
  private $policy;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setPolicy(PhabricatorPolicy $policy) {
    $this->policy = $policy;
    return $this;
  }

  public function getPolicy() {
    return $this->policy;
  }

  public function getPolicyDisplayName() {
    $policy = $this->getPolicy();
    return $policy->getFullName();
  }

  public function newTransactionLink(
    $mode,
    PhabricatorApplicationTransaction $xaction) {

    $policy = $this->getPolicy();

    if ($policy->isCustomPolicy()) {
      $uri = urisprintf(
        '/transactions/%s/%s/',
        $mode,
        $xaction->getPHID());
      $workflow = true;
    } else {
      $uri = $policy->getHref();
      $workflow = false;
    }

    return $this->newLink($uri, $workflow);
  }

  public function newCapabilityLink($object, $capability) {
    $policy = $this->getPolicy();

    $uri = urisprintf(
      '/policy/explain/%s/%s/',
      $object->getPHID(),
      $capability);

    return $this->newLink($uri, true);
  }

  private function newLink($uri, $workflow) {
    $policy = $this->getPolicy();
    $name = $policy->getName();

    if ($uri !== null) {
      $name = javelin_tag(
        'a',
        array(
          'href' => $uri,
          'sigil' => ($workflow ? 'workflow' : null),
        ),
        $name);
    }

    $hint = $this->getPolicyTypeHint();
    if ($hint !== null) {
      $name = pht('%s (%s)', $name, $hint);
    }

    return $name;
  }

  private function getPolicyTypeHint() {
    $policy = $this->getPolicy();

    if ($policy->isProjectPolicy()) {
      return pht('Project');
    }

    if ($policy->isMaskedPolicy()) {
      return pht('You do not have permission to view policy details.');
    }

    return null;
  }

}
