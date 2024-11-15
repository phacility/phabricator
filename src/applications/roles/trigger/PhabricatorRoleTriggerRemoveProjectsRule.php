<?php

final class PhabricatorRoleTriggerRemoveRolesRule
  extends PhabricatorRoleTriggerRule {

  const TRIGGERTYPE = 'task.roles.remove';

  public function getSelectControlname() {
    return pht('Remove role tags');
  }

  protected function getValueForEditorField() {
    return $this->getDatasource()->getWireTokens($this->getValue());
  }

  protected function assertValidRuleRecordFormat($value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Remove role rule value should be a list, but is not '.
          '(value is "%s").',
          phutil_describe_type($value)));
    }
  }

  protected function assertValidRuleRecordValue($value) {
    if (!$value) {
      throw new Exception(
        pht(
          'You must select at least one role tag to remove.'));
    }
  }

  protected function newDropTransactions($object, $value) {
    $role_edge_type = PhabricatorRoleObjectHasRoleEdgeType::EDGECONST;

    $xaction = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $role_edge_type)
      ->setNewValue(
        array(
          '-' => array_fuse($value),
        ));

    return array($xaction);
  }

  protected function newDropEffects($value) {
    return array(
      $this->newEffect()
        ->setIcon('fa-briefcase', 'red')
        ->setContent($this->getRuleViewDescription($value)),
    );
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getPHUIXControlType() {
    return 'tokenizer';
  }

  private function getDatasource() {
    return id(new PhabricatorRoleDatasource())
      ->setViewer($this->getViewer());
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
    return pht('Remove Role Tags');
  }

  public function getRuleViewDescription($value) {
    return pht(
      'Remove role tags: %s.',
      phutil_tag(
        'strong',
        array(),
        $this->getViewer()
          ->renderHandleList($value)
          ->setAsInline(true)
          ->render()));
  }

  public function getRuleViewIcon($value) {
    return id(new PHUIIconView())
      ->setIcon('fa-briefcase', 'red');
  }



}
