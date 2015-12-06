<?php
//require_once 'unirest-php/src/Unirest.php';

class Stats {
    private $allSentences;
    private $analizedSentences;
    private $analizedSentimentScores;
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
    private function fetch($dbObject) {
        $phpObject = array();
        while ($row = mysql_fetch_assoc($dbObject)) {
            $phpObject[] = $row;
        }
        return $phpObject;
    }

    private function getAllSentenceCount() {
        $query = "SELECT product_id, COUNT(sentence_id) AS cnt FROM sentences GROUP BY product_id";
        $result = mysql_query($query);
        $this->allSentences =  $this->fetch($result)[0];
    }
    private function getAllAnalizedSentences() {
        $query = "SELECT COUNT(DISTINCT(request_type_id)) AS cnt FROM request_log WHERE request_type = 's'";
        $result = mysql_query($query);
        $this->analizedSentences =  $this->fetch($result)[0];
    }
    public function getAnalizedSentimentScores() {
        $query = "SELECT request_id,  neg,  pos, neutral FROM sentiment_score_log GROUP BY request_id";
        $result = mysql_query($query);
        $fetched = $this->fetch($result);
        $this->analizedSentimentScores = array();
        foreach ($fetched as $f) {
            $this->analizedSentimentScores[] = $f;
        }
    }

    public function __construct() {
        $this->connectDb();
        $this->getAllSentenceCount();
        $this->getAllAnalizedSentences();
    }
    public function getGeneralStats() {
        $data = array('allSentences'=>$this->allSentences, 'analizedSentences'=>$this->analizedSentences);
        return json_encode($data);
    }
    public function getSentimentScores() {
        return json_encode(array('sentimentScores'=>$this->analizedSentimentScores));
    }
}

$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

$stats = new Stats();
if ($request->requestType == "sentimentScores") {
    $stats->getAnalizedSentimentScores();
    echo $stats->getSentimentScores();
} else {
    echo $stats->getGeneralStats();
}
?>
