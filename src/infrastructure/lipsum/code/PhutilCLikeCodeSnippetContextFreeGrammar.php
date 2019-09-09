<?php

/**
 * Generates valid context-free code for most programming languages that could
 * pass as C. Except for PHP. But includes Java (mostly).
 */
abstract class PhutilCLikeCodeSnippetContextFreeGrammar
  extends PhutilCodeSnippetContextFreeGrammar {

  protected function buildRuleSet() {
    return array(
      $this->getStmtTerminationGrammarSet(),
      $this->getVarNameGrammarSet(),
      $this->getNullExprGrammarSet(),
      $this->getNumberGrammarSet(),
      $this->getExprGrammarSet(),
      $this->getCondGrammarSet(),
      $this->getLoopGrammarSet(),
      $this->getStmtGrammarSet(),
      $this->getAssignmentGrammarSet(),
      $this->getArithExprGrammarSet(),
      $this->getBoolExprGrammarSet(),
      $this->getBoolValGrammarSet(),
      $this->getTernaryExprGrammarSet(),

      $this->getFuncNameGrammarSet(),
      $this->getFuncCallGrammarSet(),
      $this->getFuncCallParamGrammarSet(),
      $this->getFuncDeclGrammarSet(),
      $this->getFuncParamGrammarSet(),
      $this->getFuncBodyGrammarSet(),
      $this->getFuncReturnGrammarSet(),
    );
  }

  protected function getStartGrammarSet() {
    $start_grammar = parent::getStartGrammarSet();

    $start_grammar['start'][] = '[funcdecl]';

    return $start_grammar;
  }

  protected function getStmtTerminationGrammarSet() {
    return $this->buildGrammarSet('term', array(';'));
  }

  protected function getFuncCallGrammarSet() {
    return $this->buildGrammarSet('funccall',
      array(
        '[funcname]([funccallparam])',
      ));
  }

  protected function getFuncCallParamGrammarSet() {
    return $this->buildGrammarSet('funccallparam',
      array(
        '',
        '[expr]',
        '[expr], [expr]',
      ));
  }

  protected function getFuncDeclGrammarSet() {
    return $this->buildGrammarSet('funcdecl',
      array(
        'function [funcname]([funcparam]) '.
          '{[funcbody, indent, block, trim=right]}',
      ));
  }

  protected function getFuncParamGrammarSet() {
    return $this->buildGrammarSet('funcparam',
      array(
        '',
        '[varname]',
        '[varname], [varname]',
        '[varname], [varname], [varname]',
      ));
  }

  protected function getFuncBodyGrammarSet() {
    return $this->buildGrammarSet('funcbody',
      array(
        "[stmt]\n[stmt]\n[funcreturn]",
        "[stmt]\n[stmt]\n[stmt]\n[funcreturn]",
        "[stmt]\n[stmt]\n[stmt]\n[stmt]\n[funcreturn]",
      ));
  }

  protected function getFuncReturnGrammarSet() {
    return $this->buildGrammarSet('funcreturn',
      array(
        'return [expr][term]',
        '',
      ));
  }

  // Not really C, but put it here because of the curly braces and mostly shared
  // among Java and PHP
  protected function getClassDeclGrammarSet() {
    return $this->buildGrammarSet('classdecl',
      array(
        '[classinheritancemod] class [classname] {[classbody, indent, block]}',
        'class [classname] {[classbody, indent, block]}',
      ));
  }

  protected function getClassNameGrammarSet() {
    return $this->buildGrammarSet('classname',
      array(
        'MuffinHouse',
        'MuffinReader',
        'MuffinAwesomizer',
        'SuperException',
        'Librarian',
        'Book',
        'Ball',
        'BallOfCode',
        'AliceAndBobsSharedSecret',
        'FileInputStream',
        'FileOutputStream',
        'BufferedReader',
        'BufferedWriter',
        'Cardigan',
        'HouseOfCards',
        'UmbrellaClass',
        'GenericThing',
      ));
  }

  protected function getClassBodyGrammarSet() {
    return $this->buildGrammarSet('classbody',
      array(
        '[methoddecl]',
        "[methoddecl]\n\n[methoddecl]",
        "[propdecl]\n[propdecl]\n\n[methoddecl]\n\n[methoddecl]",
        "[propdecl]\n[propdecl]\n[propdecl]\n\n[methoddecl]\n\n[methoddecl]".
          "\n\n[methoddecl]",
      ));
  }

  protected function getVisibilityGrammarSet() {
    return $this->buildGrammarSet('visibility',
      array(
        'private',
        'protected',
        'public',
      ));
  }

  protected function getClassInheritanceModGrammarSet() {
    return $this->buildGrammarSet('classinheritancemod',
      array(
        'final',
        'abstract',
      ));
  }

  // Keeping this separate so we won't give abstract methods a function body
  protected function getMethodInheritanceModGrammarSet() {
    return $this->buildGrammarSet('methodinheritancemod',
      array(
        'final',
      ));
  }

  protected function getMethodDeclGrammarSet() {
    return $this->buildGrammarSet('methoddecl',
      array(
        '[visibility] [methodfuncdecl]',
        '[visibility] [methodfuncdecl]',
        '[methodinheritancemod] [visibility] [methodfuncdecl]',
        '[abstractmethoddecl]',
      ));
  }

  protected function getMethodFuncDeclGrammarSet() {
    return $this->buildGrammarSet('methodfuncdecl',
      array(
        'function [funcname]([funcparam]) '.
          '{[methodbody, indent, block, trim=right]}',
      ));
  }

  protected function getMethodBodyGrammarSet() {
    return $this->buildGrammarSet('methodbody',
      array(
        "[methodstmt]\n[methodbody]",
        "[methodstmt]\n[funcreturn]",
      ));
  }

  protected function getMethodStmtGrammarSet() {
    $stmts = $this->getStmtGrammarSet();

    return $this->buildGrammarSet('methodstmt',
      array_merge(
        $stmts['stmt'],
        array(
          '[methodcall][term]',
        )));
  }

  protected function getMethodCallGrammarSet() {
    // Java/JavaScript
    return $this->buildGrammarSet('methodcall',
      array(
        'this.[funccall]',
        '[varname].[funccall]',
        '[classname].[funccall]',
      ));
  }

  protected function getAbstractMethodDeclGrammarSet() {
    return $this->buildGrammarSet('abstractmethoddecl',
      array(
        'abstract function [funcname]([funcparam])[term]',
      ));
  }

  protected function getPropDeclGrammarSet() {
    return $this->buildGrammarSet('propdecl',
      array(
        '[visibility] [varname][term]',
      ));
  }

  protected function getClassRuleSets() {
    return array(
      $this->getClassInheritanceModGrammarSet(),
      $this->getMethodInheritanceModGrammarSet(),
      $this->getClassDeclGrammarSet(),
      $this->getClassNameGrammarSet(),
      $this->getClassBodyGrammarSet(),
      $this->getMethodDeclGrammarSet(),
      $this->getMethodFuncDeclGrammarSet(),
      $this->getMethodBodyGrammarSet(),
      $this->getMethodStmtGrammarSet(),
      $this->getMethodCallGrammarSet(),
      $this->getAbstractMethodDeclGrammarSet(),
      $this->getPropDeclGrammarSet(),
      $this->getVisibilityGrammarSet(),
    );
  }

  public function generateClass() {
    $rules = array_merge($this->getRules(), $this->getClassRuleSets());
    $rules['start'] = array('[classdecl]');
    $count = 0;
    return $this->applyRules('[start]', $count, $rules);
  }

}
