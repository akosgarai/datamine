<?php 

class User {
    private $sentences;
    private $rid;

    public function __construct ($rid) {
        $this->sentences = array();
        $this->rid = $rid;
    }
    public function getRid() {
        return $this->rid;
    }
    public function addSentence(Sentence $sentence) {
        $this->sentences[] = $sentence;
    }
    public function getSentences() {
        return $this->sentences;
    }
    public function getSentence($index) {
        return $this->sentences[$index];
    }
}

?>
