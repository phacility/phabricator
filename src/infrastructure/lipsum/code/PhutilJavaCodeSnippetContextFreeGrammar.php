<?php

final class PhutilJavaCodeSnippetContextFreeGrammar
  extends PhutilCLikeCodeSnippetContextFreeGrammar {

  protected function buildRuleSet() {
    $parent_ruleset = parent::buildRuleSet();
    $rulesset = array_merge($parent_ruleset, $this->getClassRuleSets());

    $rulesset[] = $this->getTypeNameGrammarSet();
    $rulesset[] = $this->getNamespaceDeclGrammarSet();
    $rulesset[] = $this->getNamespaceNameGrammarSet();
    $rulesset[] = $this->getImportGrammarSet();
    $rulesset[] = $this->getMethodReturnTypeGrammarSet();
    $rulesset[] = $this->getMethodNameGrammarSet();
    $rulesset[] = $this->getVarDeclGrammarSet();
    $rulesset[] = $this->getClassDerivGrammarSet();

    return $rulesset;
  }

  protected function getStartGrammarSet() {
    return $this->buildGrammarSet('start',
      array(
        '[import, block][nmspdecl, block][classdecl, block]',
      ));
  }

  protected function getClassDeclGrammarSet() {
    return $this->buildGrammarSet('classdecl',
      array(
        '[classinheritancemod] [visibility] class [classname][classderiv] '.
          '{[classbody, indent, block]}',
        '[visibility] class [classname][classderiv] '.
          '{[classbody, indent, block]}',
      ));
  }

  protected function getClassDerivGrammarSet() {
    return $this->buildGrammarSet('classderiv',
      array(
        ' extends [classname]',
        '',
        '',
      ));
  }

  protected function getTypeNameGrammarSet() {
    return $this->buildGrammarSet('type',
      array(
        'int',
        'boolean',
        'char',
        'short',
        'long',
        'float',
        'double',
        '[classname]',
        '[type][]',
      ));
  }

  protected function getMethodReturnTypeGrammarSet() {
    return $this->buildGrammarSet('methodreturn',
      array(
        '[type]',
        'void',
      ));
  }

  protected function getNamespaceDeclGrammarSet() {
    return $this->buildGrammarSet('nmspdecl',
      array(
        'package [nmspname][term]',
      ));
  }

  protected function getNamespaceNameGrammarSet() {
    return $this->buildGrammarSet('nmspname',
      array(
        'java.lang',
        'java.io',
        'com.example.proj.std',
        'derp.example.www',
      ));
  }

  protected function getImportGrammarSet() {
    return $this->buildGrammarSet('import',
      array(
        'import [nmspname][term]',
        'import [nmspname].*[term]',
        'import [nmspname].[classname][term]',
      ));
  }

  protected function getExprGrammarSet() {
    $expr = parent::getExprGrammarSet();

    $expr['expr'][] = 'new [classname]([funccallparam])';

    $expr['expr'][] = '[methodcall]';
    $expr['expr'][] = '[methodcall]';
    $expr['expr'][] = '[methodcall]';
    $expr['expr'][] = '[methodcall]';

    // Add some 'char's
    for ($ii = 0; $ii < 2; $ii++) {
      $expr['expr'][] = "'".Filesystem::readRandomCharacters(1)."'";
    }

    return $expr;
  }

  protected function getStmtGrammarSet() {
    $stmt = parent::getStmtGrammarSet();

    $stmt['stmt'][] = '[vardecl]';
    $stmt['stmt'][] = '[vardecl]';
    // `try` to `throw` a `Ball`!
    $stmt['stmt'][] = 'throw [classname][term]';

    return $stmt;
  }

  protected function getPropDeclGrammarSet() {
    return $this->buildGrammarSet('propdecl',
      array(
        '[visibility] [type] [varname][term]',
      ));
  }

  protected function getVarDeclGrammarSet() {
    return $this->buildGrammarSet('vardecl',
      array(
        '[type] [varname][term]',
        '[type] [assignment][term]',
      ));
  }

  protected function getFuncNameGrammarSet() {
    return $this->buildGrammarSet('funcname',
      array(
        '[methodname]',
        '[classname].[methodname]',
        // This is just silly (too much recursion)
        // '[classname].[funcname]',
        // Don't do this for now, it just clutters up output (thanks to rec.)
        // '[nmspname].[classname].[methodname]',
      ));
  }

  // Renamed from `funcname`
  protected function getMethodNameGrammarSet() {
    $funcnames = head(parent::getFuncNameGrammarSet());
    return $this->buildGrammarSet('methodname', $funcnames);
  }

  protected function getMethodFuncDeclGrammarSet() {
    return $this->buildGrammarSet('methodfuncdecl',
      array(
        '[methodreturn] [methodname]([funcparam]) '.
          '{[methodbody, indent, block, trim=right]}',
      ));
  }

  protected function getFuncParamGrammarSet() {
    return $this->buildGrammarSet('funcparam',
      array(
        '',
        '[type] [varname]',
        '[type] [varname], [type] [varname]',
        '[type] [varname], [type] [varname], [type] [varname]',
      ));
  }

  protected function getAbstractMethodDeclGrammarSet() {
    return $this->buildGrammarSet('abstractmethoddecl',
      array(
        'abstract [methodreturn] [methodname]([funcparam])[term]',
      ));
  }

}
