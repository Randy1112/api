<?php
require 'config.php';
require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->post('/login','login'); /* User login */
$app->post('/signup','signup'); /* User Signup  */
$app->post('/membres','membres'); /* User Membre */
$app->post('/modifierProfil','modifierProfil'); /* Modifier profil */
$app->post('/deleteUser','deleteUser');
$app->post('/feed','feed'); /* User Feeds  */
$app->post('/feedUpdate','feedUpdate'); /* User Feeds  */
$app->post('/feedDelete','feedDelete'); /* User Feeds  */
//$app->post('/userDetails','userDetails'); /* User Details */

$app->run();

/************************* USER LOGIN *************************************/
/* ### User login ### */

function deleteUser(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    $data;

    $db = getDB();
    $sql = "DELETE FROM user WHERE user_id =:user_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $data);
    $stmt->execute();

    $db = null;

    echo json_encode($data);
}

function modifierProfil(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    $id = $data->user_id;
    $description = $data->description;
    $img = $data->user_img_url;

    $db = getDB();
    $sql = "UPDATE user SET description='" . addslashes($description) . "', user_img_url='" . addslashes($img) . "' WHERE user_id ='" . $id . "'";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $sql2 = "SELECT * FROM user WHERE user_id ='" . $id . "'";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute();
    $data = $stmt2->fetch(PDO::FETCH_OBJ);

    $db = null;

    echo json_encode($data);

}

function membres(){
    $db = getDB();
    $data = '';
    //$array = [];
    $sql = "SELECT user_id as id, username, description, user_img_url FROM user";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $membres = $stmt->fetchAll(PDO::FETCH_OBJ);

    $db = null;
    /*
    if($data){
        foreach($membres as $membre){
            $array[] = $membre;
        }
        $array = json_encode($array);
        echo '{"membresTableau": ' . $array . '}';
    } else {
        echo '{"error":{"text":"Vous n\'avez pas pu récupérer la liste des membres"}}';
    }*/
    $array = json_encode($membres);

    echo $array;
}

function login() {
    
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    try {
        $db = getDB();
        $userData ='';
        $sql = "SELECT user_id, username, description, user_img_url FROM user WHERE username=:username and password=:password ";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':username', $data->username);
        $password = hash('sha256', $data->password);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_OBJ);
        
        if(!empty($userData))
        {
            $user_id=$userData->user_id;
            $userData->token = apiToken($user_id);
        }
        
        $db = null;
         if($userData){
             $userData = json_encode($userData);
             //echo '{"userData": ' . $userData . '}';
             echo $userData;
         } else {
             echo 'false';
         }
    }
    catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}


/* ### User registration ### */
function signup() {
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $username=$data->username;
    $password=$data->password;
    $description=$data->description;
    $photo=$data->user_img_url;

    
    try {
        
        if (strlen(trim($username))>0 && strlen(trim($password))>0) {
            $db = getDB();
            $userData = '';
            $sql = "SELECT user_id FROM user WHERE username=:username";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("username", $username, PDO::PARAM_STR);
            $stmt->execute();
            $mainCount = $stmt->rowCount();
            if ($mainCount == 0) {
                /*Inserting user values*/
                $sql1 = "INSERT INTO user(username,password, description, user_img_url)VALUES(:username,:password,:description,:user_img_url)";
                $stmt1 = $db->prepare($sql1);
                $stmt1->bindParam("username", $username, PDO::PARAM_STR);
                $password = hash('sha256', $data->password);
                $stmt1->bindParam("password", $password, PDO::PARAM_STR);
                $stmt1->bindParam("description", $description, PDO::PARAM_STR);
                $stmt1->bindParam("user_img_url", $photo, PDO::PARAM_STR);
                $stmt1->execute();
            }

            $db = null;

            if ($userData) {
                $userData = json_encode($userData);
                echo '{"userData": ' . $userData . '}';
            } else {
                echo '{"error":{"text":"Enter valid data NCNBVCNVBC"}}';
            }

        }
        else{
            echo '{"error":{"text":"Enter valid data lqkjsfhlk"}}';
        }
    }
    catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}


/* ### internal Username Details ### */
function internalUserDetails($input) {
    
    try {
        $db = getDB();
        $sql = "SELECT user_id, username FROM users WHERE username=:input";
        $stmt = $db->prepare($sql);
        $stmt->bindParam("input", $input,PDO::PARAM_STR);
        $stmt->execute();
        $usernameDetails = $stmt->fetch(PDO::FETCH_OBJ);
        $usernameDetails->token = apiToken($usernameDetails->user_id);
        $db = null;
        return $usernameDetails;
        
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
    
}

function feed(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $lastCreated = $data->lastCreated;
    $systemToken=apiToken($user_id);
   
    try {
         
        if($systemToken == $token){
            $feedData = '';
            $db = getDB();
            if($lastCreated){
                $sql = "SELECT * FROM feed WHERE user_id_fk=:user_id AND created<:lastCreated ORDER BY feed_id DESC LIMIT 10";
                $stmt = $db->prepare($sql);
                $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
                $stmt->bindParam("lastCreated", $lastCreated, PDO::PARAM_STR);
             }
            else{
                $sql = "SELECT * FROM feed WHERE user_id_fk=:user_id ORDER BY feed_id DESC LIMIT 10";
                $stmt = $db->prepare($sql);
                $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $feedData = $stmt->fetchAll(PDO::FETCH_OBJ);
           
            $db = null;
            if($feedData){
                echo '{"feedData": ' . json_encode($feedData) . '}';
            }
            else{
                echo '{"feedData": "" }';
            }

        } else{
            echo '{"error":{"text":"No access"}}';
        }
       
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
    
    
    
}

function feedUpdate(){

    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $feed=$data->feed;
    
    $systemToken=apiToken($user_id);
   
    try {
         
        if($systemToken == $token){
            $feedData = '';
            $db = getDB();
            $sql = "INSERT INTO feed ( feed, created, user_id_fk) VALUES (:feed,:created,:user_id)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("feed", $feed, PDO::PARAM_STR);
            $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $created = time();
            $stmt->bindParam("created", $created, PDO::PARAM_INT);
            $stmt->execute();
            


            $sql1 = "SELECT * FROM feed WHERE user_id_fk=:user_id ORDER BY feed_id DESC LIMIT 1";
            $stmt1 = $db->prepare($sql1);
            $stmt1->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $stmt1->execute();
            $feedData = $stmt1->fetch(PDO::FETCH_OBJ);


            $db = null;
            echo '{"feedData": ' . json_encode($feedData) . '}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }
       
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}

function feedDelete(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $feed_id=$data->feed_id;
    
    $systemToken=apiToken($user_id);
   
    try {
         
        if($systemToken == $token){
            $feedData = '';
            $db = getDB();
            $sql = "Delete FROM feed WHERE user_id_fk=:user_id AND feed_id=:feed_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam("feed_id", $feed_id, PDO::PARAM_INT);
            $stmt->execute();
            
           
            $db = null;
            echo '{"success":{"text":"Feed deleted"}}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }
       
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
    
    
    
}

