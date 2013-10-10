<?php

final class PhabricatorPolicyEditController
  extends PhabricatorPolicyController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $root_id = celerity_generate_unique_node_id();

    $action_options = array(
      'allow' => pht('Allow'),
      'deny' => pht('Deny'),
    );

    $rules = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorPolicyRule')
      ->loadObjects();

    $rules = msort($rules, 'getRuleOrder');

    $default_value = 'deny';
    $default_rule = array(
      'action' => head_key($action_options),
      'rule' => head_key($rules),
      'value' => null,
    );

    if ($request->isFormPost()) {
      $data = $request->getStr('rules');
      $data = @json_decode($data, true);
      if (!is_array($data)) {
        throw new Exception("Failed to JSON decode rule data!");
      }

      $rule_data = array();
      foreach ($data as $rule) {
        $action = idx($rule, 'action');
        switch ($action) {
          case 'allow':
          case 'deny':
            break;
          default:
            throw new Exception("Invalid action '{$action}'!");
        }

        $rule_class = idx($rule, 'rule');
        if (empty($rules[$rule_class])) {
          throw new Exception("Invalid rule class '{$rule_class}'!");
        }

        $rule_obj = $rules[$rule_class];

        $value = $rule_obj->getValueForStorage(idx($rule, 'value'));
        $value = $rule_obj->getValueForDisplay($viewer, $value);

        $rule_data[] = array(
          'action' => $action,
          'rule' => $rule_class,
          'value' => $value,
        );
      }

      $default_value = $request->getStr('default');
    } else {
      $rule_data = array(
        $default_rule,
      );
    }

    $default_select = AphrontFormSelectControl::renderSelectTag(
      $default_value,
      $action_options,
      array(
        'name' => 'default',
      ));


    $form = id(new PHUIFormLayoutView())
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'rules',
            'sigil' => 'rules',
          )))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle(pht('Rules'))
          ->setRightButton(
            javelin_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'create-rule',
                'mustcapture' => true
              ),
              pht('New Rule')))
          ->setDescription(
            pht('These rules are processed in order.'))
          ->setContent(javelin_tag(
            'table',
            array(
              'sigil' => 'rules',
              'class' => 'policy-rules-table'
            ),
            '')))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('If No Rules Match'))
          ->setValue(pht(
            "%s all other users.",
            $default_select)));

    $form = phutil_tag(
      'div',
      array(
        'id' => $root_id,
      ),
      $form);

    $rule_options = mpull($rules, 'getRuleDescription');
    $type_map = mpull($rules, 'getValueControlType');
    $templates = mpull($rules, 'getValueControlTemplate');

    require_celerity_resource('policy-edit-css');
    Javelin::initBehavior(
      'policy-rule-editor',
      array(
        'rootID' => $root_id,
        'actions' => $action_options,
        'rules' => $rule_options,
        'types' => $type_map,
        'templates' => $templates,
        'data' => $rule_data,
        'defaultRule' => $default_rule,
      ));

    $dialog = id(new AphrontDialogView())
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setUser($viewer)
      ->setTitle(pht('Edit Policy'))
      ->appendChild($form)
      ->addSubmitButton(pht('Save Policy'))
      ->addCancelButton('#');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
