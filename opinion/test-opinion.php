<?php
class opinionTest extends PHPUnit_Framework_TestCase {

    public function testGetSID () {
        $o = new Opinion('testCategory', 'testPolarity', 'testSId');
        $this->assertEquals('testSId', $o->getSid());
    }
    public function testGetCategory () {
        $o = new Opinion('testCategory', 'testPolarity', 'testSId');
        $this->assertEquals('testCategory', $o->getCategory());
    }
    public function testGetPolarity () {
        $o = new Opinion('testCategory', 'testPolarity', 'testSId');
        $this->assertEquals('testPolarity', $o->getPolarity());
    }
}
?>

