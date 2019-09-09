<?php

/**
 * Generates non-sense code snippets according to context-free rules, respecting
 * indentation etc.
 *
 * Also provides a common ruleset shared among many mainstream programming
 * languages (that is, not Lisp).
 */
abstract class PhutilCodeSnippetContextFreeGrammar
  extends PhutilContextFreeGrammar {

  public function generate() {
    // A trailing newline is favorable for source code
    return trim(parent::generate())."\n";
  }

  final protected function getRules() {
    return array_merge(
      $this->getStartGrammarSet(),
      $this->getStmtGrammarSet(),
      array_mergev($this->buildRuleSet()));
  }

  abstract protected function buildRuleSet();

  protected function buildGrammarSet($name, array $set) {
    return array(
      $name => $set,
    );
  }

  protected function getStartGrammarSet() {
    return $this->buildGrammarSet('start',
      array(
        "[stmt]\n[stmt]",
        "[stmt]\n[stmt]\n[stmt]",
        "[stmt]\n[stmt]\n[stmt]\n[stmt]",
      ));
  }

  protected function getStmtGrammarSet() {
    return $this->buildGrammarSet('stmt',
      array(
        '[assignment][term]',
        '[assignment][term]',
        '[assignment][term]',
        '[assignment][term]',
        '[funccall][term]',
        '[funccall][term]',
        '[funccall][term]',
        '[funccall][term]',
        '[cond]',
        '[loop]',
      ));
  }

  protected function getFuncNameGrammarSet() {
    return $this->buildGrammarSet('funcname',
      array(
        'do_something',
        'nonempty',
        'noOp',
        'call_user_func',
        'getenv',
        'render',
        'super',
        'derpify',
        'awesomize',
        'equals',
        'run',
        'flee',
        'fight',
        'notify',
        'listen',
        'calculate',
        'aim',
        'open',
      ));
  }

  protected function getVarNameGrammarSet() {
    return $this->buildGrammarSet('varname',
      array(
        'is_something',
        'object',
        'name',
        'token',
        'label',
        'piece_of_the_pie',
        'type',
        'state',
        'param',
        'action',
        'key',
        'timeout',
        'result',
      ));
  }

  protected function getNullExprGrammarSet() {
    return $this->buildGrammarSet('null', array('null'));
  }

  protected function getNumberGrammarSet() {
    return $this->buildGrammarSet('number',
      array(
        mt_rand(-1, 100),
        mt_rand(-100, 1000),
        mt_rand(-1000, 5000),
        mt_rand(0, 1).'.'.mt_rand(1, 1000),
        mt_rand(0, 50).'.'.mt_rand(1, 1000),
      ));
  }

  protected function getExprGrammarSet() {
    return $this->buildGrammarSet('expr',
      array(
        '[null]',
        '[number]',
        '[number]',
        '[varname]',
        '[varname]',
        '[boolval]',
        '[boolval]',
        '[boolexpr]',
        '[boolexpr]',
        '[funccall]',
        '[arithexpr]',
        '[arithexpr]',
        // Some random strings
        '"'.Filesystem::readRandomCharacters(4).'"',
        '"'.Filesystem::readRandomCharacters(5).'"',
      ));
  }

  protected function getBoolExprGrammarSet() {
    return $this->buildGrammarSet('boolexpr',
      array(
        '[varname]',
        '![varname]',
        '[varname] == [boolval]',
        '[varname] != [boolval]',
        '[ternary]',
      ));
  }

  protected function getBoolValGrammarSet() {
    return $this->buildGrammarSet('boolval',
      array(
        'true',
        'false',
      ));
  }

  protected function getArithExprGrammarSet() {
    return $this->buildGrammarSet('arithexpr',
      array(
        '[varname]++',
        '++[varname]',
        '[varname] + [number]',
        '[varname]--',
        '--[varname]',
        '[varname] - [number]',
      ));
  }

  protected function getAssignmentGrammarSet() {
    return $this->buildGrammarSet('assignment',
      array(
        '[varname] = [expr]',
        '[varname] = [arithexpr]',
        '[varname] += [expr]',
      ));
  }

  protected function getCondGrammarSet() {
    return $this->buildGrammarSet('cond',
      array(
        'if ([boolexpr]) {[stmt, indent, block]}',
        'if ([boolexpr]) {[stmt, indent, block]} else {[stmt, indent, block]}',
      ));
  }

  protected function getLoopGrammarSet() {
    return $this->buildGrammarSet('loop',
      array(
        'while ([boolexpr]) {[stmt, indent, block]}',
        'do {[stmt, indent, block]} while ([boolexpr])[term]',
        'for ([assignment]; [boolexpr]; [expr]) {[stmt, indent, block]}',
      ));
  }

  protected function getTernaryExprGrammarSet() {
    return $this->buildGrammarSet('ternary',
      array(
        '[boolexpr] ? [expr] : [expr]',
      ));
  }

  protected function getStmtTerminationGrammarSet() {
    return $this->buildGrammarSet('term', array(''));
  }

}
