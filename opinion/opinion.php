<?php 

class Opinion {
    private $s_id;
    private $category;
    private $polarity;

    public function __construct ($category, $polarity, $s_id = '') {
        $this->category = $category;
        $this->polarity = $polarity;
        $this->s_id = $s_id;
    }
    public function getSid() {
        return $this->s_id;
    }
    public function getCategory() {
        return $this->category;
    }
    public function getPolarity() {
        return $this->polarity;
    }
}

?>
