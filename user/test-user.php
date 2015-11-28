<?php

class userTest extends PHPUnit_Framework_TestCase {
    public function testGetRidFunction () {
        $u = new User('test-rid');
        $this->assertEquals('test-rid', $u->getRid());
    }
}
?>
