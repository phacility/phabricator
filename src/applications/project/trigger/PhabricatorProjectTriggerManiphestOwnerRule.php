<?php

final class PhabricatorProjectTriggerManiphestOwnerRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'task.owner';

  public function getSelectControlName() {
    return pht('Assign task to');
  }

  protected function getValueForEditorField() {
    return $this->getDatasource()->getWireTokens($this->getValue());
  }

  private function convertTokenizerValueToOwner($value) {
    $value = head($value);
    if ($value === PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN) {
      $value = null;
    }
    return $value;
  }

  protected function assertValidRuleRecordFormat($value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Owner rule value should be a list, but is not (value is "%s").',
          phutil_describe_type($value)));
    }
  }

  protected function assertValidRuleRecordValue($value) {
    if (!$value) {
      throw new Exception(
        pht(
          'Owner rule value is required. Specify a user to assign tasks '.
          'to, or the token "none()" to unassign tasks.'));
    }

    if (count($value) > 1) {
      throw new Exception(
        pht(
          'Owner rule value must have only one elmement (value is "%s").',
          implode(', ', $value)));
    }

    $owner_phid = $this->convertTokenizerValueToOwner($value);
    if ($owner_phid !== null) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($owner_phid))
        ->executeOne();
      if (!$user) {
        throw new Exception(
          pht(
            'User PHID ("%s") is not a valid user.',
            $owner_phid));
      }
    }
  }

  protected function newDropTransactions($object, $value) {
    $value = $this->convertTokenizerValueToOwner($value);
    return array(
      $this->newTransaction()
        ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
        ->setNewValue($value),
    );
  }

  protected function newDropEffects($value) {
    $owner_value = $this->convertTokenizerValueToOwner($value);

    return array(
      $this->newEffect()
        ->setIcon('fa-user')
        ->setContent($this->getRuleViewDescription($value))
        ->addCondition('owner', '!=', $owner_value),
    );
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getPHUIXControlType() {
    return 'tokenizer';
  }

  private function getDatasource() {
    $datasource = id(new ManiphestAssigneeDatasource())
      ->setLimit(1);

    if ($this->getViewer()) {
      $datasource->setViewer($this->getViewer());
    }

    return $datasource;
  }

  protected function getPHUIXControlSpecification() {
    $template = id(new AphrontTokenizerTemplateView())
      ->setViewer($this->getViewer());

    $template_markup = $template->render();
    $datasource = $this->getDatasource();

    return array(
      'markup' => (string)hsprintf('%s', $template_markup),
      'config' => array(
        'src' => $datasource->getDatasourceURI(),
        'browseURI' => $datasource->getBrowseURI(),
        'placeholder' => $datasource->getPlaceholderText(),
        'limit' => $datasource->getLimit(),
      ),
      'value' => null,
    );
  }

  public function getRuleViewLabel() {
    return pht('Change Owner');
  }

  public function getRuleViewDescription($value) {
    $value = $this->convertTokenizerValueToOwner($value);

    if (!$value) {
      return pht('Unassign task.');
    } else {
      return pht(
        'Assign task to %s.',
        phutil_tag(
          'strong',
          array(),
          $this->getViewer()
            ->renderHandle($value)
            ->render()));
    }
  }

  public function getRuleViewIcon($value) {
    return id(new PHUIIconView())
      ->setIcon('fa-user', 'green');
  }


}
