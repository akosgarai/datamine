<?php 
require 'user/user.php';
require 'opinion/opinion.php';
require 'sentence/sentence.php';

class ImporterV2 {
    private $xml;
    private $connection;
    private $db;
    private $productId;
    private $users;
    private $sentenceObject;

    public function __construct($filename, $productType) {
        if (file_exists($filename)) {
            $this->xml = simplexml_load_file('./'.$filename);
        } else {
            echo "File not found. 404. filename: $filename\n";
            die;
        }
        $this->productId = $productType;
        $this->connectDb();
        $this->users = array();
    }

    private function connectDb() {
        $username = 'datamine';
        $password = 'datamine';
        $hostname = 'localhost';
        $dbname = 'datamine';

        $this->connection = mysql_connect($hostname, $username, $password) or die ('Unable to connect to DB');
        $this->db = mysql_select_db($dbname, $this->connection) or die ('Could not select datamine DB');
    }

    private function createSentenceObject() {
        $sentenceObject = array();
        foreach ($this->xml as $f) {
            $rid = (string)$f->attributes()->rid;
            if (!array_key_exists($rid, $sentenceObject)) {
                $sentenceObject[$rid] = array();
            }
            foreach($f->sentences as $ss) {
                foreach($ss->sentence as $s) {
                    $sentenceId = (string) $s->attributes()->id;
                    if (!array_key_exists($sentenceId, $sentenceObject[$rid])) {
                        $sentenceObject[$rid][$sentenceId] = array();
                    }
                    $text = $s->text;
                    $sentenceObject[$rid][$sentenceId]['text'] = (string) $text;
                    if ($s->Opinions) {
                        if (!array_key_exists('opinions', $sentenceObject[$rid][$sentenceId]) ) {
                            $sentenceObject[$rid][$sentenceId]['opinions'] = array();
                        }
                        foreach ($s->Opinions->Opinion as $o) {
                            $opinion = array('category' => (string)$o->attributes()->category, 'polarity' => (string)$o->attributes()->polarity);
                            $sentenceObject[$rid][$sentenceId]['opinions'][] = $opinion;
                        }
                    }
                }
            }
        }
        $this->sentenceObject = $sentenceObject;
        echo "Sentence Object construction done\n";
    }
    private function createUserObjects() {
    /*
     * sentenceObject = {
     *  rid : {
     *      sentenceId : [
     *          {
     *              text : string, 
     *              opinions : [{
     *                  category : string,
     *                  polarity : string
     *              }]
     *          }
     *      ]
     *  }
     * }
     * */
        foreach ($this->sentenceObject as $rid => $s) {
            $user = new User($rid);
            foreach($s as $sentenceId => $ss) {
                $sentence = new Sentence($sentenceId, $rid, $this->productId, $ss['text']);
                if (array_key_exists('opinions', $ss)) {
                    foreach ($ss['opinions'] as $o) {
                        $opinion = new Opinion($o['category'], $o['polarity']);
                        $sentence->addOpinion($opinion);
                    }
                }
                $user->addSentence($sentence);
            }
            $this->users[] = $user;
        }
        echo "User Object construction completed.\n";
    }
    private function insertUserToDb($rid) {
        $query = "INSERT INTO reviewers VALUES(NULL, '".$rid."')";
        $result = mysql_query($query);
        if (!$result) {
            echo "Something wrong happened during user insertion. Query: $query\n";
            return "ERR";
        }
        return "OK";
    }
    private function checkUserByRid($rid) {
        $query = "SELECT * FROM reviewers WHERE rid = '".$rid."'";
        $result = mysql_query($query);
        return $result;
    }
    private function userImport($users) {
        echo "User import started. Try to insert ".count($users)." user.\n";
        $numOk = 0;
        $numErr = 0;
        $numSkipped = 0;
        $errRid = array();
        foreach ($users as $user) {
            $continueSentence = false;
            $userRid = $user->getRid();
            $result = $this->checkUserByRid($userRid);
            if(mysql_num_rows($result) == 0) {
                if ($this->insertUserToDb($userRid) == "OK") {
                    $numOk++;
                    $continueSentence = true;
                } else {
                    $numErr++;
                    $errRid[] = $userRid;
                }
            } else {
                echo "User exists in DB. rid: ".$userRid."\n";
                $numSkipped++;
                $continueSentence = true;
            }
            if ($continueSentence) {
                $this->sentenceImport($user);
            }
        }
        echo "User Import Statistics:\n\n";
        echo "Skipped: ".$numSkipped."\nInserted: ".$numOk."\nError: ".$numErr."\nRids: ".implode(', ', $errRid)."\n";
    }
    private function getSentenceBySenteceId($sid) {
        $query = "SELECT * FROM sentences WHERE sentence_id = '".$sid."'";
        $result = mysql_query($query);
        return $result;
    }
    private function insertSentenceToDb ($sentence_id, $rid, $productId, $text) {
        $replaced_text = preg_replace("/'/", " ", $text);
        $query = "INSERT INTO sentences VALUES(NULL, '".$sentence_id."', '".$rid."', '".$productId."', '".$replaced_text."')";
        $result = mysql_query($query);
        if (!$result) {
            echo "Something wrong happened during sentence insertion. Query: $query\n";
            return "ERR";
        }
        return "OK";
    }
    private function sentenceImport ($user){
        echo "Sentence import started for user(".$user->getRid().") Try to insert ".count($user->getSentences())." sentence.\n";
        $numOk = 0;
        $numErr = 0;
        $numSkipped = 0;
        $errSentence = array();
        $continueOpinions = false;
        $doInsert = false;
        foreach ($user->getSentences() as $sentence) {
            $sentenceId = $sentence->getSentenceParams()['sentence_id'];
            $rid = $sentence->getSentenceParams()['rid'];
            $text = $sentence->getSentenceParams()['text'];
            $tmp = $this->getSentenceBySenteceId($sentenceId);
            if (!$tmp) {
                $doInsert = true;
            } else {
                if (mysql_num_rows($tmp) == 0) {
                    $doInsert = true;
                } else {
                    $numSkipped++;
                    $continueOpinions = true;
                    echo "Sentence exists in DB. sentence_id: ".$sentenceId."\n";
                }
            }
            if ($doInsert) {
                $insertResponse = $this->insertSentenceToDb($sentenceId, $rid, $this->productId, $text);
                if ($insertResponse == "OK") {
                    $numOk++;
                    $continueOpinions = true;
                } else {
                    $numErr++;
                }
            }
            if ($continueOpinions) {
                $this->opinionImport($sentence);
            }
        }
        echo "Sentence Import Statistics:\n\n";
        echo "Skipped: ".$numSkipped."\nInserted: ".$numOk."\nError: ".$numErr."\n";
    }
    private function opinionImport ($sentence) {
        $ops = $sentence->getOpinions();
        if (count($ops) > 0) {
            $index = 1;
            foreach ($ops as $opinion) {
                $insert = false;
                $sentence_id = $sentence->getSentenceParams()['sentence_id'];
                $db_sentence = $this->getSentenceBySenteceId($sentence_id);
                if (!$db_sentence) {
                    echo "Sentence not exists in db. sentence_id: ".$sentence_id."\n";
                    continue;
                } else {
                    $cntRows = mysql_num_rows($db_sentence);
                    if ($cntRows == 0) {
                        echo "Sentence not exists in db. sentence_id: ".$sentence_id."\n";
                        continue;
                    }
                    $row = mysql_fetch_row($db_sentence);
                    $s_id = $row[0];
                    $polarity = $opinion->getPolarity();
                    $category = $opinion->getCategory();
                    $db_options = $this->getSameOption($s_id, $polarity, $category, NULL);
                    if (!$db_options) {
                        $insert = true;
                    } else {
                        if(mysql_num_rows($db_options) != 0) {
                            echo "Something wrong happened. We found ".mysql_num_rows($db_options)." entry for s_id: ".$s_id.", polarity: ".$polarity.", category: ".$category."\n";
                        } else {
                            $insert = true;
                        }
                    }
                    if ($insert) {
                        $result = $this->insertOpinionToDb($s_id, $polarity, $category, $index);
                        if ($result == "OK") {
                            $index++;
                        }
                    }
                }
            }
            echo "Opinion Import Statistics:\n\n";
            echo "Inserted ".--$index." opinion from ".count($ops)."\n";
        }
    }
    private function getSameOption ($sentenceId, $polarity, $category, $index = NULL) {
        $query = "SELECT * FROM opinions WHERE s_id = '".$sentenceId."' AND polarity = '".$polarity."' AND category = '".$category."'";
        if ($index) {
            $query .= " AND opinion_index = '".$index."'";
        }
        $result = mysql_query($query);
        return $result;
    }
    private function insertOpinionToDb($sentenceId, $polarity, $category, $index) {
        $query = "INSERT INTO opinions VALUES('".$sentenceId."', '".$polarity."', '".$category."', NULL, '".$index."')";
        $result = mysql_query($query);
        if (!$result) {
            echo "Something wrong happened during opinion insertion. Query: $query\n";
            return "ERR";
        }
        return "OK";
    }
    public function import() {
        $this->createSentenceObject();
        $this->createUserObjects();
        $this->userImport($this->users);
    }
}
$iv2 = new ImporterV2('ABSA16_Laptops_Train_SB1.xml', 1);
$iv2->import();
?>
