<?php
class Mysql {
  public $conn;
  public $usercheck = 0;
  public $user_result;
  public $tb_user = null;
  public $tb_token = null;
  public $storage = null;

  public static function connect($db=0,$user=0,$pass=0,$server=0){
    global $conn;
    global $storage;
    $storage = $storage===null ? Candy::storage('sys')->get('mysql') : $storage;
    $storage->error = isset($storage->error) && is_object($storage->error) ? $storage->error : new \stdClass;
    $storage->error->info = isset($storage->error->info) && is_object($storage->error->info) ? $storage->error->info : new \stdClass;

    $db = $db===0 ? (defined('MYSQL_DB') ? MYSQL_DB : '') : $db;
    $user = $user===0 ? (defined('MYSQL_USER') ? MYSQL_USER : '') : $user;
    $pass = $pass===0 ? (defined('MYSQL_PASS') ? MYSQL_PASS : '') : $pass;
    $server = $server===0 ? (defined('MYSQL_SERVER') ? MYSQL_SERVER : '127.0.0.1') : $server;
    $conn = mysqli_connect($server, $user, $pass, $db);
    if($conn){
      mysqli_set_charset($conn,"utf8");
      mysqli_query($conn,"SET NAMES utf8mb4");
    }else{
      if(Config::check('MASTER_MAIL') && (!isset($storage->error->info->date) || $storage->error->info->date!=date('d/m/Y'))){
        Candy::quickMail( MASTER_MAIL,
                    '<b>Date</b>: '.date("Y-m-d H:i:s").'<br />
                     <b>Message</b>: Unable to connect to mysql server<br /><br />
                     <b>Server</b>: '.$server.'<br />
                     <b>Database</b>: '.$db.'<br />
                     <b>Username</b>: '.$user.'<br />
                     <b>Password</b>: '.$pass.'<br /><br />
                     <b>Details</b>: <br />
                     SERVER:
                     <pre>'.print_r($_SERVER,true).'</pre>
                     SESSION:
                     <pre>'.print_r($_SESSION,true).'</pre>
                     COOKIE:
                     <pre>'.print_r($_COOKIE,true).'</pre>
                     POST:
                     <pre>'.print_r($_POST,true).'</pre>
                     GET:
                     <pre>'.print_r($_GET,true).'</pre>',
                     $_SERVER['SERVER_NAME'].' - INFO',
                     ['mail' => 'candyphp@'.$_SERVER['SERVER_NAME'], 'name' => 'Candy PHP']
                   );
        $storage->error->info->date = date('d/m/Y');
        Candy::storage('sys')->set('mysql',$storage);
      }
        echo "Mysql connection error" . PHP_EOL;
        exit;
    }
    return $conn;
  }

  public static function query($query,$b = true){
    global $conn;
    $result = new \stdClass();
    $sql = mysqli_query($conn, $query);
    $data = array();
    while($row = mysqli_fetch_assoc($sql)){
      $data[] = $row;
    }
    $result->rows = mysqli_num_rows($sql);
    $result->fetch = $data;
    if($b){
      return $result;
    }
  }

  public static function loginCheck($arr,$t = true){
    global $conn;
    global $storage;
    global $table_token;
    global $table_user;
    $result = new \stdClass();
    $query = '';
    foreach ($arr as $key => $value) {
      if($key!='table_user' && $key!='table_token'){
        $query .= $key.'="'.mysqli_real_escape_string($conn,$value).'" AND ';
      }
    }
    $query = '('.substr($query,0,-4).')';
    $sql_user = mysqli_query($conn, 'SELECT * FROM '.$arr['table_user'].' WHERE '.$query);
    if(mysqli_num_rows($sql_user)==1){
      $result->success = true;
      $data = array();
      while($row = mysqli_fetch_assoc($sql_user)){
        $data[] = $row;
      }
      $result->fetch = $data;
      $result->rows = mysqli_num_rows($sql_user);
      if($t){
        $token1 = uniqid(mt_rand(), true).rand(10000,99999).(time()*100);
        $token2 = md5($_SERVER['REMOTE_ADDR']);
        $token3 = md5($_SERVER['HTTP_USER_AGENT']);
        setcookie("token1", $token1, time() + 61536000, "/");
        setcookie("token2", $token2, time() + 61536000, "/");
        $sql_token = mysqli_query($conn, 'INSERT INTO '.$arr['table_token'].' (userid,token1,token2,token3,ip) VALUES ("' . $result->fetch[0]['id'] . '","' . $token1 . '","' . $token2 . '","' . $token3 . '","'.$_SERVER['REMOTE_ADDR'].'")');
      }
      $table_token = $arr['table_token'];
      $table_user = $arr['table_user'];
      $storage = $storage===null ? Candy::storage('sys')->get('mysql') : $storage;
      if(!isset($storage->login->table_token) || !isset($storage->login->table_user) || $table_token!=$storage->login->table_token || $table_user!=$storage->login->table_user){
        $storage->login = isset($storage->login) && is_object($storage->login) ? $storage->login : new \stdClass;
        $storage->login->table_user = $arr['table_user'];
        $storage->login->table_token = $arr['table_token'];
        Candy::storage('sys')->set('mysql',$storage);
      }
      return $result;
    }else{
      $result->success = false;
      return $result;
    }
  }

  public static function userCheck($fetch = false){
    global $conn;
    global $usercheck;
    global $user;
    global $storage;
    global $tb_token;
    global $tb_user;
    global $user_result;
    $storage = $storage===null ? Candy::storage('sys')->get('mysql') : $storage;
    if($tb_token===null){
      $tb_token = isset($storage->login->table_token) ? $storage->login->table_token : 'tb_token';
    }
    if($tb_user===null){
      $tb_user = isset($storage->login->table_user) ? $storage->login->table_user : 'tb_user';
    }
    if($usercheck==0 || $fetch){
      $result = new \stdClass();
      if(isset($_COOKIE['token1']) && isset($_COOKIE['token2'])){
        $token1 = mysqli_real_escape_string($conn, $_COOKIE['token1']);
        $token2 = mysqli_real_escape_string($conn, $_COOKIE['token2']);
        $token3 = md5($_SERVER['HTTP_USER_AGENT']);
        $sql_token = mysqli_query($conn, 'SELECT * FROM '.mysqli_real_escape_string($conn,$tb_token).' WHERE token1="'.$token1.'" AND token2="'.$token2.'" AND token3="'.$token3.'"');
        if(mysqli_num_rows($sql_token) == 1){
          if($fetch){
            $get_token = mysqli_fetch_assoc($sql_token);
            $sql_user = mysqli_query($conn,'SELECT * FROM '.$tb_user.' WHERE id="'.$get_token['userid'].'"');
            $result->success = true;
            $data = array();
            $row = mysqli_fetch_assoc($sql_user);
            $data = $row;
            $result->fetch = $data;
            $user = $data;
            $result->rows = mysqli_num_rows($sql_user);
            $user_result = $result;
            return $result;
          }else{
            $usercheck = 1;
            return true;
          }
        }
      }else{
        $usercheck = 2;
        return false;
      }
    }else{
        return $usercheck==1;
    }
  }

  public static function logout(){
    global $conn;
    global $storage;
    global $tb_token;
    $storage = $storage===null ? Candy::storage('sys')->get('mysql') : $storage;
    if($tb_token===null){
      $tb_token = isset($storage->login->table_token) ? $storage->login->table_token : 'tb_token';
    }
    if(isset($_COOKIE['token1']) && isset($_COOKIE['token2'])){
      $token1 = mysqli_real_escape_string($conn, $_COOKIE['token1']);
      $token2 = mysqli_real_escape_string($conn, $_COOKIE['token2']);
      $token3 = md5($_SERVER['HTTP_USER_AGENT']);
      $sql_token = mysqli_query($conn, 'DELETE FROM '.mysqli_real_escape_string($conn,$tb_token).' WHERE token1="'.$token1.'" AND token2="'.$token2.'" AND token3="'.$token3.'"');
      setcookie("token1", "", time() - 3600);
      setcookie("token2", "", time() - 3600);
    }
  }

  public static function select($tb = '0',$where = null){
    global $conn;

    $result = new \stdClass();
    if(is_array($tb) || $tb!='0'){
      if(!is_array($tb)){
        if($where===null){
          $query = 'SELECT * FROM '.$tb;
        }else{
          $query = is_numeric($where) ? 'SELECT * FROM '.$tb.' WHERE id="'.$where.'"' : 'SELECT * FROM '.$tb.' WHERE '.$where;
        }
      }else{
        if(isset($tb['SELECT'])){
          $query = 'SELECT '.$tb['SELECT'];
        }elseif(isset($tb['select'])){
          $query = 'SELECT '.$tb['select'];
        }else{
          $query = 'SELECT *';}
        foreach ($tb as $key => $value){
          if(strtoupper($key)!='SELECT'){
            $query .= ' '.strtoupper($key).' '.$value;
          }
        }
      }
      $result->query = $query;
      if($sql = mysqli_query($conn, $query)){
        $result->success = true;
        $result->rows = mysqli_num_rows($sql);
        $data = array();
        while($row = mysqli_fetch_assoc($sql)){
          $data[] = $row;
        }

        $result->fetch = $data;
        mysqli_free_result($sql);
        return $result;
      }else{
        $result->success = false;
        return $result;
      }
    }else{
      $result->success = false;
      return $result;
    }
  }

  public static function insert($table, $value){
    global $conn;
    $result = new \stdClass();
    if(is_array($value)){
      $query_key = '';
      $query_val = '';
      foreach ($value as $key => $val) {
        $query_key .= $key.',';
        $query_val .= '"'.mysqli_real_escape_string($conn, $val).'",';
      }
      $query = 'INSERT INTO '.$table.' ('.substr($query_key,0,-1).') VALUES ('.substr($query_val,0,-1).')';
      $sql = mysqli_query($conn, $query);
      $result->query = $query;
      $result->success = $sql;
      $result->message = mysql_errno($conn) . ": " . mysql_error($conn);
      $result->id = $conn->insert_id;
      return $result;
    }else{
      return false;
    }
  }

  public static function update($table,$where,$value){
    global $conn;
    if(is_array($value)){
      $query = 'UPDATE '.$table.' SET ';

      foreach ($value as $key => $val) {
        $query .= $key.'="'.mysqli_real_escape_string($conn,$val).'",';
      }
      if(is_numeric($where)){
        $query = substr($query,0,-1) . ' WHERE id="'.$where.'"';
      }else{
        $query = substr($query,0,-1) . ' WHERE '.$where;
      }
      $sql = mysqli_query($conn, $query);
      return $sql;
    }else{
      return false;
    }
  }

  public static function delete($table,$where){
    global $conn;
    if(is_numeric($where)){
      return $sql = mysqli_query($conn, 'DELETE FROM '.$table.' WHERE id="'.$where.'"');
    }else{
      return $sql = mysqli_query($conn, 'DELETE FROM '.$table.' WHERE '.$where);
    }
  }
}
global $mysql;
$mysql = new Mysql();
$class = $mysql;
