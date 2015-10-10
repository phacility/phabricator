<?php

final class DiffusionMercurialWireProtocolTests extends PhabricatorTestCase {

  public function testFilteringBundle2Capability() {
    // this was the result of running 'capabilities' over
    // `hg serve --stdio` on my systems with Mercurial 3.5.1, 2.6.2

    $capabilities_with_bundle2_hg_351 =
    'lookup changegroupsubset branchmap pushkey '.
    'known getbundle unbundlehash batch stream '.
    'bundle2=HG20%0Achangegroup%3D01%2C02%0Adigests%3Dmd5%2Csha1%2Csha512'.
    '%0Aerror%3Dabort%2Cunsupportedcontent%2Cpushraced%2Cpushkey%0A'.
    'hgtagsfnodes%0Alistkeys%0Apushkey%0Aremote-changegroup%3Dhttp%2Chttps '.
    'unbundle=HG10GZ,HG10BZ,HG10UN httpheader=1024';

    $capabilities_without_bundle2_hg_351 =
    'lookup changegroupsubset branchmap pushkey '.
    'known getbundle unbundlehash batch stream '.
    'unbundle=HG10GZ,HG10BZ,HG10UN httpheader=1024';

    $capabilities_hg_262 =
    'lookup changegroupsubset branchmap pushkey '.
    'known getbundle unbundlehash batch stream '.
    'unbundle=HG10GZ,HG10BZ,HG10UN httpheader=1024 largefiles=serve';

    $cases = array(
      array(
        'name' => pht('Filter bundle2 from Mercurial 3.5.1'),
        'input' => $capabilities_with_bundle2_hg_351,
        'expect' => $capabilities_without_bundle2_hg_351,
      ),

      array(
        'name' => pht('Filter bundle does not affect Mercurial 2.6.2'),
        'input' => $capabilities_hg_262,
        'expect' => $capabilities_hg_262,
      ),
    );

    foreach ($cases as $case) {
      $actual = DiffusionMercurialWireProtocol::filterBundle2Capability(
        $case['input']);
      $this->assertEqual($case['expect'], $actual, $case['name']);
    }
  }

}
