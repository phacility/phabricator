<?php

final class PhutilPHPCodeSnippetContextFreeGrammar
  extends PhutilCLikeCodeSnippetContextFreeGrammar {

  protected function buildRuleSet() {
    return array_merge(parent::buildRuleSet(), $this->getClassRuleSets());
  }

  protected function getStartGrammarSet() {
    $start_grammar = parent::getStartGrammarSet();

    $start_grammar['start'][] = '[classdecl]';
    $start_grammar['start'][] = '[classdecl]';

    return $start_grammar;
  }

  protected function getExprGrammarSet() {
    $expr = parent::getExprGrammarSet();

    $expr['expr'][] = 'new [classname]([funccallparam])';

    $expr['expr'][] = '[classname]::[funccall]';

    return $expr;
  }

  protected function getVarNameGrammarSet() {
    $varnames = parent::getVarNameGrammarSet();

    foreach ($varnames as $vn_key => $vn_val) {
      foreach ($vn_val as $vv_key => $vv_value) {
        $varnames[$vn_key][$vv_key] = '$'.$vv_value;
      }
    }

    return $varnames;
  }

  protected function getFuncNameGrammarSet() {
    return $this->buildGrammarSet('funcname',
      array_mergev(get_defined_functions()));
  }

  protected function getMethodCallGrammarSet() {
    return $this->buildGrammarSet('methodcall',
      array(
        '$this->[funccall]',
        'self::[funccall]',
        'static::[funccall]',
        '[varname]->[funccall]',
        '[classname]::[funccall]',
      ));
  }

}
