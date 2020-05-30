<?php

final class AphrontRoutingMapTestCase
  extends PhabricatorTestCase {

  public function testRoutingMaps() {
    $count = 0;

    $sites = AphrontSite::getAllSites();
    foreach ($sites as $site) {
      $maps = $site->getRoutingMaps();
      foreach ($maps as $map) {
        foreach ($map->getRoutes() as $rule => $value) {
          $this->assertRoutable($site, $map, array(), $rule, $value);
          $count++;
        }
      }
    }

    if (!$count) {
      $this->assertSkipped(
        pht('No sites define any routing rules.'));
    }
  }

  private function assertRoutable(
    AphrontSite $site,
    AphrontRoutingMap $map,
    array $path,
    $rule,
    $value) {

    $path[] = $rule;

    $site_description = $site->getDescription();
    $rule_path = implode(' > ', $path);

    $pattern = implode('', $path);
    $pattern = '('.$pattern.')';
    $ok = @preg_match($pattern, '');

    $this->assertTrue(
      ($ok !== false),
      pht(
        'Routing rule ("%s", for site "%s") does not compile into a '.
        'valid regular expression.',
        $rule_path,
        $site_description));

    if (is_array($value)) {
      $this->assertTrue(
        (count($value) > 0),
        pht(
          'Routing rule ("%s", for site "%s") does not have any targets.',
          $rule_path,
          $site_description));

      foreach ($value as $sub_rule => $sub_value) {
        $this->assertRoutable($site, $map, $path, $sub_rule, $sub_value);
      }
      return;
    }

    if (is_string($value)) {
      $this->assertTrue(
        class_exists($value),
        pht(
          'Routing rule ("%s", for site "%s") points at controller ("%s") '.
          'which does not exist.',
          $rule_path,
          $site_description,
          $value));
      return;
    }

    $this->assertFailure(
      pht(
        'Routing rule ("%s", for site "%s") points at unknown value '.
        '(of type "%s"), expected a controller class name string.',
        $rule_path,
        $site_description,
        phutil_describe_type($value)));
  }

}
