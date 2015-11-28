<?php
require 'user/user.php';
require 'sentence/sentence.php';
require 'opinion/opinion.php';
require_once 'unirest-php/src/Unirest.php';

class site {
    private $products;
    private $users;
    private $sentences;
    private $connection;
    private $db;

    private function connectDb() {
        $username = 'datamine';
        $password = 'datamine';
        $hostname = 'localhost';
        $dbname = 'datamine';

        $this->connection = mysql_connect($hostname, $username, $password) or die ('Unable to connect to DB');
        $this->db = mysql_select_db($dbname, $this->connection) or die ('Could not select datamine DB');
    }
    public function __construct($userId = NULL, $productId = NULL, $opinionFlag = false) {
        $this->connectDb();
        $this->users = array();
        $this->products = array();
        $this->sentences = array();
        $this->init();
        $this->sentences = $this->generalSentenceSelector($userId, $productId, $opinionFlag);
        if ($opinionFlag) {
            $idsToCheck = $this->getSentenceIds();
            $opinions = $this->getOpinionsForSId($idsToCheck);
            foreach ($opinions as $o) {
                $sentence = $this->getSentenceBySentenceId($o->s_id);
                if (!$sentence->opinions) {
                    $sentence->opinions = array();
                }
                $sentence->opinions[] = $o;
            }
        }
    }

    public function getJSONData() {
        $data = array('products' => $this->products, 'users' => $this->users, 'sentences' => $this->sentences);
        return json_encode($data);
    }
    public function getJSONOpinion($s_id) {
        $opinions = $this->getOpinionsForSid($s_id);
        return json_encode(array('opinions'=>$opinions));
    }
    private function init() {
        $this->products = $this->getProducts();
        $this->users = $this->getUsers();
    }

    private function getSentenceBySentenceId($s_id) {
        foreach ($this->sentences as $s) {
            if ($s->s_id = $s_id) {
                return $s;
            }
        }
        return NULL;
    }

    private function getSentenceIds() {
        $sentenceIds = array();
        foreach ($this->sentences as $sentence) {
            $sentenceId = $sentence->s_id;
            $sentenceIds[] = $sentenceId;
        }
        return $sentenceIds;
    }

    private function getOpinionsBySId() {
    }

    private function getProducts() {
        $query = "SELECT * FROM products";
        $result = mysql_query($query);
        return $this->fetch($result);
    }

    private function getUsers() {
        $query = "SELECT * FROM reviewers";
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    private function generalSentenceSelector($userId, $productId, $opinionFlag) {
        $query = "SELECT sentences.product_id, sentences.rid, sentences.s_id, sentences.sentence_id, sentences.text FROM sentences ";
        if ($opinionFlag) {
            $query .= "JOIN opinions USING (s_id) ";
        }
        $query .= "WHERE ";
        if ($userId) {
            $query .= "rid = '".$userId."' AND ";
        }
        if ($productId) {
            $query .= "product_id = '".$productId."' AND ";
        }
        if ($opinionFlag) {
            $query .= "opinion_index = 1 AND ";
        }
        $query .= "1 = 1";
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    private function fetch($dbObject) {
        $phpObject = array();
        while ($row = mysql_fetch_assoc($dbObject)) {
            $phpObject[] = $row;
        }
        return $phpObject;
    }
    private function getOpinionsForSid($s_id) {
        $query = '';
        if (is_array($s_id)) {
            $query = "SELECT * FROM opinions WHERE s_id IN '".implode('\', \'',$s_id)."'";
        } else {
            $query = "SELECT * FROM opinions WHERE s_id = '".$s_id."'";
        }
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    public function processAnalyzation($sid, $text, $productId, $force = false) {
        $previousAnalization = $this->getLastSkyttleAnalization($sid);
        if (!$previousAnalization || $force) {
            $options = '';
            if ($productId == 1) {
                $options = 'electronic';
            }
            $response = $this->doSkyttleAnalyzation($text, $options);
        }
    }

    private function getLastSkyttleAnalization($s_id) { //TODO finish this after the implementation of saveSkyttleResponse function
        return false;
    }
    private function doSkyttleAnalyzation($text, $options) {
        $url = "https://sentinelprojects-skyttle20.p.mashape.com/";
        $headers = array(
            "X-Mashape-Key" => "xyumIBzeMJmshIB41rhsw7ALq5btp1QopRZjsnfDjm4RnA4pDR",    //my Private Mashup Key
            "Content-Type" => "application/x-www-form-urlencoded",
            "Accept" => "application/json"
        );
        $body = array(
            "annotate" => 0,
            "keywords" => 1,
            "lang" => "en",
            "sentiment" => 1,
            "text" => $text
        );
        if ($options != '') {
            $body->domain = $options;
        }
        $response = Unirest\Request::post($url, $header, json_encode($body));
        if ($response->code == "200") {
            return $response->body;
        } else {
            return $response;
        }
    }
}
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);
if ($request->requestType == 'opinion') {
    $site = new Site();
    echo $site->getJSONOpinion($request->sid);
} else if ($request->requestType == 'analyze') {
    if (!$request->s_id || !$request->text) {   //no data -> no analyzation
        echo '';
    } else {
        $site = new Site();
        $analization = $site->processAnalyzation($request->s_id, $request->productId, $request->text, $request->forceSkyttle);
        echo var_dump($analization);
    }
} else {
    $id_user = NULL;
    $id_product = NULL;
    $opinionFlag = false;
    if ($request->id_user != '') {
        $id_user = $request->id_user;
    }
    if ($request->id_product != '') {
        $id_product = $request->id_product;
    }
    if ($request->opinion_flag) {
        $opinionFlag = $request->opinion_flag;
    }
    //echo var_dump($request);
    $site = new Site($id_user, $id_product, $opinionFlag);
    echo $site->getJSONData();
}
?>
