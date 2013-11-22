<?php

final class ManiphestTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = ManiphestTransaction::TYPE_PRIORITY;
    $types[] = ManiphestTransaction::TYPE_STATUS;
    $types[] = ManiphestTransaction::TYPE_TITLE;
    $types[] = ManiphestTransaction::TYPE_DESCRIPTION;
    $types[] = ManiphestTransaction::TYPE_OWNER;
    $types[] = ManiphestTransaction::TYPE_CCS;
    $types[] = ManiphestTransaction::TYPE_PROJECTS;
    $types[] = ManiphestTransaction::TYPE_ATTACH;
    $types[] = ManiphestTransaction::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return (int)$object->getPriority();
      case ManiphestTransaction::TYPE_STATUS:
        if ($this->getIsNewObject()) {
          return null;
        }
        return (int)$object->getStatus();
      case ManiphestTransaction::TYPE_TITLE:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getTitle();
      case ManiphestTransaction::TYPE_DESCRIPTION:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getDescription();
      case ManiphestTransaction::TYPE_OWNER:
        return nonempty($object->getOwnerPHID(), null);
      case ManiphestTransaction::TYPE_CCS:
        return array_values(array_unique($object->getCCPHIDs()));
      case ManiphestTransaction::TYPE_PROJECTS:
        return array_values(array_unique($object->getProjectPHIDs()));
      case ManiphestTransaction::TYPE_ATTACH:
        return $object->getAttached();
      case ManiphestTransaction::TYPE_EDGE:
        // These are pre-populated.
        return $xaction->getOldValue();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
      case ManiphestTransaction::TYPE_STATUS:
        return (int)$xaction->getNewValue();
      case ManiphestTransaction::TYPE_CCS:
      case ManiphestTransaction::TYPE_PROJECTS:
        return array_values(array_unique($xaction->getNewValue()));
      case ManiphestTransaction::TYPE_OWNER:
        return nonempty($xaction->getNewValue(), null);
      case ManiphestTransaction::TYPE_TITLE:
      case ManiphestTransaction::TYPE_DESCRIPTION:
      case ManiphestTransaction::TYPE_ATTACH:
      case ManiphestTransaction::TYPE_EDGE:
        return $xaction->getNewValue();
    }
  }


  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PROJECTS:
      case ManiphestTransaction::TYPE_CCS:
        sort($old);
        sort($new);
        return ($old !== $new);
    }

    return parent::transactionHasEffect($object, $xaction);
  }


  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        return $object->setPriority($xaction->getNewValue());
      case ManiphestTransaction::TYPE_STATUS:
        return $object->setStatus($xaction->getNewValue());
      case ManiphestTransaction::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case ManiphestTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case ManiphestTransaction::TYPE_OWNER:
        $phid = $xaction->getNewValue();

        // Update the "ownerOrdering" column to contain the full name of the
        // owner, if the task is assigned.

        $handle = null;
        if ($phid) {
          $handle = id(new PhabricatorHandleQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($phid))
            ->executeOne();
        }

        if ($handle) {
          $object->setOwnerOrdering($handle->getName());
        } else {
          $object->setOwnerOrdering(null);
        }

        return $object->setOwnerPHID($phid);
      case ManiphestTransaction::TYPE_CCS:
        return $object->setCCPHIDs($xaction->getNewValue());
      case ManiphestTransaction::TYPE_PROJECTS:
        return $object->setProjectPHIDs($xaction->getNewValue());
      case ManiphestTransaction::TYPE_ATTACH:
        return $object->setAttached($xaction->getNewValue());
      case ManiphestTransaction::TYPE_EDGE:
        // These are a weird, funky mess and are already being applied by the
        // time we reach this.
        return;
    }

  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.maniphest.subject-prefix');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    return 'maniphest-task-'.$object->getPHID();
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getOwnerPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return $object->getCCPHIDs();
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ManiphestReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("T{$id}: {$title}")
      ->addHeader('Thread-Topic', "T{$id}: ".$object->getOriginalTitle());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if ($this->getIsNewObject()) {
      $body->addTextSection(
        pht('TASK DESCRIPTION'),
        $object->getDescription());
    }

    $body->addTextSection(
      pht('TASK DETAIL'),
      PhabricatorEnv::getProductionURI('/T'.$object->getID()));

    return $body;
  }

  protected function supportsFeed() {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function supportsHerald() {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldManiphestTaskAdapter())
      ->setTask($object);
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {

    $save_again = false;
    $cc_phids = $adapter->getCcPHIDs();
    if ($cc_phids) {
      $existing_cc = $object->getCCPHIDs();
      $new_cc = array_unique(array_merge($cc_phids, $existing_cc));
      $object->setCCPHIDs($new_cc);
      $save_again = true;
    }

    $assign_phid = $adapter->getAssignPHID();
    if ($assign_phid) {
      $object->setOwnerPHID($assign_phid);
      $save_again = true;
    }

    $project_phids = $adapter->getProjectPHIDs();
    if ($project_phids) {
      $existing_projects = $object->getProjectPHIDs();
      $new_projects = array_unique(
        array_merge($project_phids, $existing_projects));
      $object->setProjectPHIDs($new_projects);
      $save_again = true;
    }

    if ($save_again) {
      $object->save();
    }
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    parent::requireCapabilities($object, $xaction);

    $app_capability_map = array(
      ManiphestTransaction::TYPE_PRIORITY =>
        ManiphestCapabilityEditPriority::CAPABILITY,
      ManiphestTransaction::TYPE_STATUS =>
        ManiphestCapabilityEditStatus::CAPABILITY,
      ManiphestTransaction::TYPE_PROJECTS =>
        ManiphestCapabilityEditProjects::CAPABILITY,
      ManiphestTransaction::TYPE_OWNER =>
        ManiphestCapabilityEditAssign::CAPABILITY,
      PhabricatorTransactions::TYPE_EDIT_POLICY =>
        ManiphestCapabilityEditPolicies::CAPABILITY,
      PhabricatorTransactions::TYPE_VIEW_POLICY =>
        ManiphestCapabilityEditPolicies::CAPABILITY,
    );

    $transaction_type = $xaction->getTransactionType();
    $app_capability = idx($app_capability_map, $transaction_type);

    if ($app_capability) {
      $app = id(new PhabricatorApplicationQuery())
        ->setViewer($this->getActor())
        ->withClasses(array('PhabricatorApplicationManiphest'))
        ->executeOne();
      PhabricatorPolicyFilter::requireCapability(
        $this->getActor(),
        $app,
        $app_capability);
    }
  }


  public static function getNextSubpriority($pri, $sub) {

    // TODO: T603 Figure out what the policies here should be once this gets
    // cleaned up.

    if ($sub === null) {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d ORDER BY subpriority ASC LIMIT 1',
        $pri);
      if ($next) {
        return $next->getSubpriority() - ((double)(2 << 16));
      }
    } else {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d AND subpriority > %s ORDER BY subpriority ASC LIMIT 1',
        $pri,
        $sub);
      if ($next) {
        return ($sub + $next->getSubpriority()) / 2;
      }
    }

    return (double)(2 << 32);
  }

}
