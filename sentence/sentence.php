<?php 

class Sentence {
    private $s_id;
    private $sentence_id;
    private $rid;
    private $product_id;
    private $text;
    private $opinions;

    public function __construct ($sentence_id, $rid, $product_id, $text, $s_id = '') {
        $this->sentence_id = $sentence_id;
        $this->rid = $rid;
        $this->product_id = $product_id;
        $this->text = $text;
        $this->opinions = array();
        $this->s_id = $s_id;
    }
    public function addOpinion(Opinion $opinion) {
        $this->opinions[] = $opinion;
    }
    public function getOpinions() {
        return $this->opinions;
    }
    public function getOpinion($index) {
        return $this->opinions[$index];
    }
    public function getSentenceParams() {
        return array(
            's_id' => $this->s_id,
            'sentence_id' => $this->sentence_id,
            'rid' => $this->rid,
            'product_id' => $this->product_id,
            'text' => $this->text
        );
    }
}

?>
