<?php

final class PhabricatorProjectTriggerRemoveProjectsRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'task.projects.remove';

  public function getSelectControlname() {
    return pht('Remove project tags');
  }

  protected function getValueForEditorField() {
    return $this->getDatasource()->getWireTokens($this->getValue());
  }

  protected function assertValidRuleRecordFormat($value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Remove project rule value should be a list, but is not '.
          '(value is "%s").',
          phutil_describe_type($value)));
    }
  }

  protected function assertValidRuleRecordValue($value) {
    if (!$value) {
      throw new Exception(
        pht(
          'You must select at least one project tag to remove.'));
    }
  }

  protected function newDropTransactions($object, $value) {
    $project_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    $xaction = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $project_edge_type)
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
    return id(new PhabricatorProjectDatasource())
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
    return pht('Remove Project Tags');
  }

  public function getRuleViewDescription($value) {
    return pht(
      'Remove project tags: %s.',
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
