<?php
session_start();

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 

$LiveChat = new LiveChat();

if(isset($_REQUEST['mode'])){
	switch($_REQUEST['mode']){
		case 'GetChats':
			echo json_encode(array('viewers' => $LiveChat->GetViewers(), 'chats'=>$LiveChat->GetChats()));
		break;

		case 'GetViewers':
			echo json_encode($LiveChat->GetViewers());
		break;

		case 'SendChat':
			$LiveChat->SendChat($_REQUEST['hash'], $_REQUEST['chat']);
		break;

		case 'StartChat':
			$LiveChat->StartChat($_REQUEST['hash'], $_REQUEST['positions']);
		break;

		case 'CloseChat':
			$LiveChat->CloseChat($_REQUEST['hash'], $_REQUEST['option'], $_REQUEST['positions']);
		break;

		case 'MinimizeChat':
			$LiveChat->MinimizeChat($_REQUEST['hash'], $_REQUEST['option']);
		break;

		case 'SetUserName':
			$LiveChat->SetUserName($_REQUEST['name'], $_REQUEST['email']);
		break;

		case 'SetNewVisit':
			echo json_encode($LiveChat->SetNewVisit());
		break;
	}
}

class LiveChat{
	var $sql_host='192.168.1.3';
	var $sql_user='root';
	var $sql_pass='tical';
	var $sql_db='livechat';

	var $idleLimit='300';
	var $flushTime=false;
	var $defaultuser='Unknown';
	var $defaultemail='Unknown';
	var $cookiename='juichat_user';
	var $hashcookie='juichat_hash';
	var $hash='';

	function __construct(){
		$this->SetUserHash();
		$this->ConnectToMySQL();
		$this->SetViewer();
	}

	function SetNewVisit(){
		$_SESSION['juichat']['viewed_rows']=array();

		$_email=$this->GetUserEmail($this->GetHash());

		return array('hash'=>$this->GetHash(), 'email' => $_email, 'user' => $this->GetUserName($this->GetHash()), 'gravitar' => $this->GetGravitarHash($_email),'viewers' => $this->GetViewers(), 'chat' => $this->GetChats());
	}

	function SetUserHash(){
		$_hash=$this->GetHashCookie();
		if(!$_hash) $_hash=$this->SetHashCookie();
		$this->hash=$_hash;
	}

	function ConnectToMySQL(){
		$conn = @mysql_connect($this->sql_host, $this->sql_user, $this->sql_pass) or die ("Unable to connect to MySQL server.");
		@mysql_select_db($this->sql_db, $conn) or die ("Unable to select MySQL database.");
	}

	private static function SortByTime($a, $b){
		if ($a[0] == $b[0]) return 0;
		return ($a[0] < $b[0]) ? -1 : 1;
	}

	function Sanitize($_string){
		// TO-DO: could use more!
		$_string = str_replace('<3', '&lt;3', $_string);
		$_string = str_replace(':<', ':&lt;', $_string);
		return strip_tags($_string, '<br><i>');
	}

	function GetHash(){
		return $this->hash;
	}

	function GetIP(){
		return $_SERVER['REMOTE_ADDR'];
	}

	function GetBrowser(){
		$browser=$_SERVER['HTTP_USER_AGENT'];
		return $this->Sanitize($browser);
	}

	function SetHashCookie(){
		$_hash=md5($this->Sanitize(str_replace('.','',uniqid($this->GetIP(), true))));
		setcookie($this->hashcookie, $_hash);
		return $_hash;
	}

	function GetHashCookie(){
		if(isset($_COOKIE[$this->hashcookie])) return $this->Sanitize($_COOKIE[$this->hashcookie]);
		return false;
	}

	function GetGravitarHash($_email){
		return md5(strtolower(trim($_email)));
	}

	function GetStyledChat($h){
		return json_encode(array('gravitar'=>$this->GetGravitarHash($h[2]), 'email'=>$h[2], 'time'=>date('g:i a',$h[0]), 'chat'=>$h[1]));
	}

	function GetChats(){
		$result=array();

		// get all unique calls where im the receiver
		$query=mysql_query("SELECT DISTINCT sender FROM lc_chat WHERE receiver='".mysql_real_escape_string($this->GetHash())."'");
		while($row=mysql_fetch_assoc($query)){
			$chat=array();
			$_lastmsg='';

			//get all calls related to this where im the receiver
			$subquery1=mysql_query("SELECT * FROM lc_chat WHERE sender='".mysql_real_escape_string($row['sender'])."' AND receiver='".mysql_real_escape_string($this->GetHash())."'");
			while($row1=mysql_fetch_assoc($subquery1)){
				if(!@in_array($row1['id'], $_SESSION['juichat']['viewed_rows'][$row['sender']])){
					if($row1['chat']) $chat[]=array($row1['time'], $this->Sanitize($row1['chat']), $this->GetUserEmail($row['sender']));
					$_SESSION['juichat']['viewed_rows'][$row['sender']][]=$row1['id'];
				}
				$_lastmsg=$row1['time'];
			}

			// get all calls related to this where im the sender
			$subquery=mysql_query("SELECT * FROM lc_chat WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($row['sender'])."'");
			while($row2=mysql_fetch_assoc($subquery)){
				if(!@in_array($row2['id'], $_SESSION['juichat']['viewed_rows'][$this->GetHash()]) && $row2['chat']) {
					$chat[]=array($row2['time'], $this->Sanitize($row2['chat']), $this->GetUserEmail($this->GetHash()));
					$_SESSION['juichat']['viewed_rows'][$this->GetHash()][]=$row2['id'];
				}
			}
			
			// sort the chat records by time
			usort($chat, array('LiveChat', 'SortByTime'));

			// display the chat records as html
			$output=array();
			foreach($chat as $c=>$h) $output[]=$this->GetStyledChat($h);
						
			$minimized=$this->GetState($row['sender'], 'minimized');
			$closed=$this->GetState($row['sender'], 'closed');
			if($_lastmsg >= $closed[1]){
				$closed=false;
			} else $closed=$closed[0];

			$result[]=array('user' => $this->GetUserName($row['sender']), 'hash' => $row['sender'], 'receiver' => @$row['receiver'], 'chat'=>$output, 'minimized' => $minimized[0], 'closed' => $closed, 'sort'=> $this->GetPosition($row['sender']), 'lastmsg' => (time()-$_lastmsg), 'online' => $this->GetViewer($row['sender']));
		}

		// get all unique calls where im the sender and no reply has been sent from the receiver
		$query=mysql_query("SELECT DISTINCT receiver, chat FROM lc_chat WHERE sender='".mysql_real_escape_string($this->GetHash())."'");
		while($row=mysql_fetch_assoc($query)){
			$_lastmsg='';
			$subquery1=mysql_query("SELECT * FROM lc_chat WHERE sender='".mysql_real_escape_string($row['receiver'])."' AND receiver='".mysql_real_escape_string($this->GetHash())."'");
			if(mysql_num_rows($subquery1) == 0){
				// i sent a message that got no reply!

				// duplicated code from above!
				$chat=array();
				$subquery=mysql_query("SELECT * FROM lc_chat WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($row['receiver'])."'");
				while($row2=mysql_fetch_assoc($subquery)){
					if(!@in_array($row2['id'], $_SESSION['juichat']['viewed_rows'][$this->GetHash()])){
						if($row2['chat']) $chat[]=array($row2['time'], $this->Sanitize($row2['chat']), $this->GetUserEmail($this->GetHash()));
						$_SESSION['juichat']['viewed_rows'][$this->GetHash()][]=$row2['id'];
					}
					$_lastmsg=$row2['time'];
				}
				
				// sort the chat records by time
				usort($chat, array('LiveChat', 'SortByTime'));

				// display the chat records as html
				$output=array();
				foreach($chat as $c=>$h) $output[]=$this->GetStyledChat($h);

				$minimized=$this->GetState($row['receiver'], 'minimized');
				$closed=$this->GetState($row['receiver'], 'closed');
				if($_lastmsg >= $closed[1]){
					$closed=false;
				} else $closed=$closed[0];

				$result[]=array('user' => $this->GetUserName($row['receiver']), 'hash' => $row['receiver'], 'receiver' => $row['receiver'], 'chat'=>$output, 'minimized' => $minimized[0], 'closed' => $closed, 'sort'=>$this->GetPosition($row['receiver']), 'online' => $this->GetViewer($row['receiver']));
			}
		}

		usort($result, array('LiveChat', 'SortByPosition'));
		return $result;
	}

	function SortByPosition($a,$b) {
		return $a['sort']>$b['sort'];
    }

	function GetPosition($_hash){
		if(isset($_SESSION['juichat']['positions'][$_hash])){
			return $_SESSION['juichat']['positions'][$_hash];
		} else return false;
	}

	function SetPositions($_positions){
		if(!is_array($_positions)) return false;
		$_SESSION['juichat']['positions']=array();
		foreach($_positions as $_hash => $_position) $_SESSION['juichat']['positions'][$this->Sanitize($_position[0])]=$this->Sanitize($_position[1]);
	}

	function StartChat($_hash, $_positions){
		$result=mysql_query("SELECT * FROM lc_chat WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($this->Sanitize($_hash))."' AND chat=''");
		if(mysql_num_rows($result) == 0){
			mysql_query("INSERT INTO lc_chat SET sender='".mysql_real_escape_string($this->GetHash())."', receiver='".mysql_real_escape_string($this->Sanitize($_hash))."', chat='', time='".time()."'");
			$_SESSION['juichat']['viewed_rows'][$this->GetHash()][]=mysql_insert_id();
		} else mysql_query("UPDATE lc_chat SET time='".time()."' WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($_hash)."' AND chat=''");

		$this->CloseChat($_hash, false, $_positions);
	}

	function SendChat($_hash, $_chat){
		if(preg_match('/\/me/s', $_chat)) $_chat='<i>'.str_replace('/me', $this->GetUserName($this->GetHash()), $_chat).'</i>';
		mysql_query("INSERT INTO lc_chat SET sender='".mysql_real_escape_string($this->GetHash())."', receiver='".mysql_real_escape_string($this->Sanitize($_hash))."', chat='".mysql_real_escape_string($this->Sanitize($_chat))."', time='".time()."'");
		$_SESSION['juichat']['viewed_rows'][$this->GetHash()][]=mysql_insert_id();
	}

	function SetState($_hash, $_state, $_option){
		$_states=array('closed', 'minimized');
		if(!in_array($_state, $_states)) return false;
		$result=mysql_query("SELECT * FROM lc_states WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($this->Sanitize($_hash))."' AND state='".mysql_real_escape_string($_state)."'");
		if(mysql_num_rows($result) == 0){
			mysql_query("INSERT INTO lc_states SET sender='".mysql_real_escape_string($this->GetHash())."', receiver='".mysql_real_escape_string($this->Sanitize($_hash))."', state='".mysql_real_escape_string($_state)."', `option`='".mysql_real_escape_string($this->Sanitize($_option))."', time='".time()."'");
		} else mysql_query("UPDATE lc_states SET time='".time()."', `option`='".mysql_real_escape_string($this->Sanitize($_option))."' WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($_hash)."' AND state='".mysql_real_escape_string($_state)."'");
	}

	function GetState($_hash, $_state){
		$_states=array('closed', 'minimized');
		if(!in_array($_state, $_states)) return false;
		$query=mysql_query("SELECT * FROM lc_states WHERE sender='".mysql_real_escape_string($this->GetHash())."' AND receiver='".mysql_real_escape_string($this->Sanitize($_hash))."' AND state='".mysql_real_escape_string($_state)."' LIMIT 1");
		$result=mysql_fetch_assoc($query);
		return array($result['option'], $result['time']);
	}

	function CloseChat($_hash, $_option, $_positions){
		if(isset($_SESSION['juichat']['positions'][$this->Sanitize($_hash)])) unset($_SESSION['juichat']['positions'][$this->Sanitize($_hash)]);

		$this->SetState($_hash, 'closed', $_option);
		$this->MinimizeChat($_hash, false);
		$this->SetPositions($_positions);

	}

	function MinimizeChat($_hash, $_option){
		$this->SetState($_hash, 'minimized', $_option);
	}

	function ClearIdlers(){
		mysql_query("DELETE FROM lc_visitors WHERE time < '".(time() - $this->idleLimit)."' OR hash=''");
	}

	function FlushData(){
		if($this->flushTime){
			mysql_query("DELETE FROM lc_chat WHERE time < '".(time() - $this->flushTime)."'");
			mysql_query("DELETE FROM lc_states WHERE time < '".(time() - $this->flushTime)."'");
			mysql_query("DELETE FROM lc_users WHERE time < '".(time() - $this->flushTime)."'");
		}
	}

	function GetUserName($_hash){
		$query=mysql_query("SELECT user FROM lc_users WHERE hash='".mysql_real_escape_string($this->Sanitize($_hash))."' LIMIT 1");
		$result = mysql_fetch_assoc($query);
		if(!$result['user']) return $this->defaultuser;
		return $result['user'];
	}	

	function GetUserEmail($_hash){
		$query=mysql_query("SELECT email FROM lc_users WHERE hash='".mysql_real_escape_string($this->Sanitize($_hash))."' LIMIT 1");
		$result = mysql_fetch_assoc($query);
		if(!$result['email']) return $this->defaultemail;
		return $result['email'];
	}

	function SetUserName($_user, $_email){
		if(!$_user) $_user=$this->defaultuser;
		$result=mysql_query("SELECT * FROM lc_users WHERE hash='".mysql_real_escape_string($this->GetHash())."'");
		if(mysql_num_rows($result) == 0){
			mysql_query("INSERT INTO lc_users SET hash='".mysql_real_escape_string($this->GetHash())."', user='".mysql_real_escape_string($_user)."', email='".mysql_real_escape_string($_email)."', time='".time()."'");
		} else mysql_query("UPDATE lc_users SET time='".time()."', user='".mysql_real_escape_string($_user)."', email='".mysql_real_escape_string($_email)."' WHERE hash='".mysql_real_escape_string($this->GetHash())."'");
	}

	function GetViewers(){
		$this->ClearIdlers();
		$result=array();
		$query=mysql_query("SELECT * FROM lc_visitors WHERE hash != '".mysql_real_escape_string($this->GetHash())."'");
		while($row = mysql_fetch_assoc($query)){
			$row['user']=$this->GetUserName($row['hash']);
			$row['email']=$this->GetUserEmail($row['hash']);
			$row['gravitar']=$this->GetGravitarHash($row['email']);
			if(!$row['user']) $row['user']=$this->defaultuser;

			$result[]=$row;
		}
		return $result;
	}	

	function GetViewer($_hash){
		$this->ClearIdlers();
		$query=mysql_query("SELECT * FROM lc_visitors WHERE hash = '".mysql_real_escape_string($this->Sanitize($_hash))."'");
		if(mysql_num_rows($query) == 0){
			return false;
		} else return true;
	}

	function SetViewer(){
		$query=mysql_query("SELECT * FROM lc_visitors WHERE hash='".mysql_real_escape_string($this->GetHash())."'");
		if(mysql_num_rows($query) == 0){
			mysql_query("INSERT INTO lc_visitors SET hash='".mysql_real_escape_string($this->GetHash())."', ip='".mysql_real_escape_string($this->GetIP())."', time='".time()."', browser='".mysql_real_escape_string($this->GetBrowser())."'");
		} else mysql_query("UPDATE lc_visitors SET time='".time()."', browser='".mysql_real_escape_string($this->GetBrowser())."' WHERE hash='".mysql_real_escape_string($this->GetHash())."'");

		$this->ClearIdlers();
		$this->FlushData();
	}
}

?>