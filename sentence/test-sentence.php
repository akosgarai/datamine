<?php
class sentenceTest extends PHPUnit_Framework_TestCase {

    public function testGetSentenceParams () {
        $s = new Sentence('testSentenceId', 'testSentenceRid', 'testProductId', 'testText', 'testSId');
        $expected = array(
            's_id' => 'testSId',
            'sentence_id' => 'testSentenceId',
            'rid' => 'testSentenceRid',
            'product_id' => 'testProductId',
            'text' => 'testText'
        );
        $this->assertEquals($expected, $s->getSentenceParams());
    }
}
?>
