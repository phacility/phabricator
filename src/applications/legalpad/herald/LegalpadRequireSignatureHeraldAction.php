<?php

final class LegalpadRequireSignatureHeraldAction
  extends HeraldAction {

  const DO_NO_TARGETS = 'do.no-targets';
  const DO_ALREADY_REQUIRED = 'do.already-required';
  const DO_INVALID = 'do.invalid';
  const DO_SIGNED = 'do.signed';
  const DO_REQUIRED = 'do.required';

  const ACTIONCONST = 'legalpad.require';

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    // TODO: This could probably be more general. Note that we call
    // getAuthorPHID() on the object explicitly below, and this also needs to
    // be generalized.
    return ($object instanceof DifferentialRevision);
  }

  protected function applyRequire(array $phids) {
    $adapter = $this->getAdapter();
    $edgetype_legal = LegalpadObjectNeedsSignatureEdgeType::EDGECONST;

    $phids = array_fuse($phids);

    if (!$phids) {
      $this->logEffect(self::DO_NO_TARGETS);
      return;
    }

    $current = $adapter->loadEdgePHIDs($edgetype_legal);

    $already = array();
    foreach ($phids as $phid) {
      if (isset($current[$phid])) {
        $already[] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($already) {
      $this->logEffect(self::DO_ALREADY_REQUIRED, $phids);
    }

    if (!$phids) {
      return;
    }

    $documents = id(new LegalpadDocumentQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->execute();
    $documents = mpull($documents, null, 'getPHID');

    $invalid = array();
    foreach ($phids as $phid) {
      if (empty($documents[$phid])) {
        $invalid[] = $phid;
        unset($documents[$phid]);
      }
    }

    if ($invalid) {
      $this->logEffect(self::DO_INVALID, $phids);
    }

    if (!$phids) {
      return;
    }

    $object = $adapter->getObject();
    $author_phid = $object->getAuthorPHID();

    $signatures = id(new LegalpadDocumentSignatureQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDocumentPHIDs($phids)
      ->withSignerPHIDs(array($author_phid))
      ->execute();
    $signatures = mpull($signatures, null, 'getDocumentPHID');

    $signed = array();
    foreach ($phids as $phid) {
      if (isset($signatures[$phid])) {
        $signed[] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($signed) {
      $this->logEffect(self::DO_SIGNED, $phids);
    }

    if (!$phids) {
      return;
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edgetype_legal)
      ->setNewValue(
        array(
          '+' => $phids,
        ));

    $adapter->queueTransaction($xaction);

    $this->logEffect(self::DO_REQUIRED, $phids);
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_NO_TARGETS => array(
        'icon' => 'fa-ban',
        'color' => 'grey',
        'name' => pht('No Targets'),
      ),
      self::DO_INVALID => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('Invalid Targets'),
      ),
      self::DO_ALREADY_REQUIRED => array(
        'icon' => 'fa-terminal',
        'color' => 'grey',
        'name' => pht('Already Required'),
      ),
      self::DO_SIGNED => array(
        'icon' => 'fa-terminal',
        'color' => 'green',
        'name' => pht('Already Signed'),
      ),
      self::DO_REQUIRED => array(
        'icon' => 'fa-terminal',
        'color' => 'green',
        'name' => pht('Required Signature'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_NO_TARGETS:
        return pht('Rule lists no targets.');
      case self::DO_INVALID:
        return pht(
          '%s document(s) are not valid: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_REQUIRED:
        return pht(
          '%s document signature(s) are already required: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_SIGNED:
        return pht(
          '%s document(s) are already signed: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_REQUIRED:
        return pht(
          'Required %s signature(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
    }
  }

  public function getHeraldActionName() {
    return pht('Require signatures');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyRequire($effect->getTarget());
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new LegalpadDocumentDatasource();
  }

  public function renderActionDescription($value) {
    return pht(
      'Require document signatures: %s.',
      $this->renderHandleList($value));
  }
}
