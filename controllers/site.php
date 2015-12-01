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
        if ($userId || $productId || $opinionFlag) {
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
        return array();
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
    public function processAnalyzation($sid, $text, $productId, $type, $force = false) {
        $previousAnalization = $this->getLastSkyttleAnalization($type, $sid);
        if (!$previousAnalization || $force) {
            $options = '';
            if ($productId == 1) {
                $options = 'electronic';
            }
            $response = $this->doSkyttleAnalyzation($text, $options);
            if (!$response->responseCode) {
                $this->saveAnalization($response, 's', $sid);
                return $response;
            }
        } else {
            $request_id = $previousAnalization[0]->request_id;
            $sentiments = $this->getSentimentsForAnalization($request_id);
            $terms = $this->getTermsForAnalization($request_id);
            $sentimentScores = $this->getSentimentScoresForAnalize($request_id);
            $finalResponse = $this->constructSkyttleLikeResponse($sentiments, $terms, $sentimentScores);
            return $finalResponse;
        }
    }

    private function saveAnalization($response, $request_type, $request_type_id) {
        foreach ($response->docs as $doc) {
            $request_id = $this->getRequestLogId($request_type, $request_type_id);
            if ($request_id == 'ERROR') {
                return array('fuckup_meter'=>'ERROR');
            }
            foreach ($doc->sentiment as $sentiment) {
                $this->insertSentimentLog($request_id, $sentiment->polarity, $sentiment->text);
            }
            $this->insertSentimentScoreLog($request_id, $doc->sentiment_scores->pos, $doc->sentiment_scores->neu, $doc->sentiment_scores->neg);
            foreach ($doc->terms as $term) {
                $term_id = $this->getTermIdFromTermsToOpinions($term->id, $term->term);
                $this->insertTermsLog($request_id, $term->count, $term_id, $term->id);
            }
        }
    }

    private function constructSkyttleLikeResponse($sentiments, $terms, $sentimentScores) {
        $response = array('docs'=>array());
        $element = array(
            'sentiment'=>array(),
            'sentiment_scores'=>array('pos'=>$sentimentScores->pos, 'neu'=>$sentimentScores->neutral, 'neg'=>$sentimentScores->neg),
            'terms'=>array()
        );
        for ($i = 0; $i < count($sentiments); $i++) {
            $element->sentiment[] = array('polarity'=>$sentiment[$i]->polarity, 'text'=>$sentiment[$i]->text);
        }
        for ($i = 0; $i < count($terms); $i++) {
            $element->terms[] = array('count'=>$terms[$i]->count, 'id'=>$terms[$i]->skyttle_id, 'term'=>$terms[$i]->term_text);
        }
        $response->docs[] = $element;
        return $element;
    }

    private function getRequestLogId($request_type, $request_type_id) {
        $checkId = $this->getRequestId($request_type, $request_type_id);
        if ($checkId == 'NEMETPORNO') {
            $this->insertRequestLog($request_type, $request_type_id);
            $request_id = $this->getRequestId($request_type, $request_type_id);
            if($request_id == 'NEMETPORNO') {
                return 'ERROR';
            }
            return $request_id;
        } else {
            return $checkId;
        }
    }
    private function getSentimentsForAnalization($request_id) {
        $query = "SELECT * FROM sentiment_log WHERE request_id = '".$request_id."'";
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    private function getSentimentScoresForAnalize($request_id) {
        $query = "SELECT * FROM sentiment_score_log WHERE request_id = '".$request_id."'";
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    private function getTermsForAnalization($request_id) {
        $query = "SELECT terms_log.count, terms_log.skyttle_id, term_text FROM terms_log JOIN terms_to_opinions USING (term_id) WHERE request_id = '".$request_id."'";
        $result = mysql_query($query);
        return $this->fetch($result);
    }
    private function insertRequestLog($request_type, $request_type_id) {
        $query = "INSERT INTO request_log (request_type, request_type_id) VALUES ('".$request_type."', '".$request_type_id."')";
        //$query = "INSERT INTO request_log  VALUES ('".$request_type."', '".$request_type_id."')";
        mysql_query($query);
    }
    private function getRequestId($request_type, $request_type_id) {
        $query = "SELECT request_id as request_id FROM request_log WHERE request_type = '".$request_type."' AND request_type_id = '".$request_type_id."'";
        $tmp = mysql_query($query);
        if (mysql_num_rows($tmp) == 0) {
            return 'NEMETPORNO';
        }
        $result = $this->fetch($tmp);
        return $result[0]['request_id'];
    }
    private function insertSentimentScoreLog($request_id, $pos, $neu, $neg) {
        $query = "INSERT INTO sentiment_score_log (request_id, neg, pos, neutral) VALUES(".$request_id.", '".$neg."', '".$pos."', '".$neu."')";
        mysql_query($query);
    }
    private function insertSentimentLog($request_id, $polarity, $text) {
        $query = "INSERT INTO sentiment_log (request_id, polarity, text) VALUES(".$request_id.", '".$polarity."', '".$text."')";
        mysql_query($query);
    }
    private function insertTermsLog($request_id, $count, $term_id, $skyttle_id) {
        $query = "INSERT INTO terms_log (request_id, count, term_id, skyttle_id) VALUES(".$request_id.", '".$count."', '".$term_id."', '".$skyttle_id."')";
        mysql_query($query);
    }
    private function getTermIdFromTermsToOpinions($term_id, $term_text) {
        $query = "SELECT term_id FROM terms_to_opinions WHERE term_text = '".$term_text."' AND skyttle_id = '".$term_id."'";
        $result = mysql_query($query);
        if (mysql_num_rows($result) == 0) {
            $query = "INSERT INTO terms_to_opinions (term_text, skyttle_id) VALUES ('".$term_text."', '".$term_id."')";
            mysql_query($query);
            return mysql_insert_id();
        } else {
            $term_id = $this->fetch($result);
            return $term_id[0]->term_id;
        }
    }

    private function getLastSkyttleAnalization($type, $id) { //TODO finish this after the implementation of saveSkyttleResponse function
        $query = "SELECT request_id, timestamp FROM request_log WHERE request_type = '".$type."' AND request_type_id = '".$id."' ORDER BY timestamp DESC LIMIT 1";
        $result = mysql_query($query);
        if (mysql_num_rows($result) == 0) {
            return false;
        }
        return $this->fetch($result);
    }
    private function doSkyttleAnalyzation($text, $options) {
        $url = "https://sentinelprojects-skyttle20.p.mashape.com/";
        $mashape_key = "xyumIBzeMJmshIB41rhsw7ALq5btp1QopRZjsnfDjm4RnA4pDR";
        $headers = array(
            "X-Mashape-Key" => $mashape_key,
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
        // Mashape auth
        Unirest\Request::setMashapeKey($mashape_key);
        $response = Unirest\Request::post($url, $header, $body);
        if ($response->code == "200") {
            //return json_encode(array('status'=>'OK', 'site'=>'doSkyttleAnalyzation_200_OK'));
            return $response->body;
        } else {
            return array('responseCode'=>$response->code);
        }
    }
}
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);
if ($request->requestType == 'opinion') {
    $site = new Site();
    echo $site->getJSONOpinion($request->sid);
    //echo json_encode(array('status'=>'OK', 'site'=>'opinion'));
} else if ($request->requestType == 'analyze_sid') {
    if (!$request->s_id || !$request->text) {   //no data -> no analyzation
        //echo '';
    } else {
        $site = new Site();
        $analization = $site->processAnalyzation($request->s_id, $request->text, $request->productId, 's', $request->forceSkyttle);
        echo json_encode($analization);
    }
    //echo json_encode(array('status'=>'OK', 'site'=>'analize'));
} else {
    //echo json_encode(array('status'=>'OK', 'site'=>'default'));
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
