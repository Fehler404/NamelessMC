<?php
class User {
	private $_db,
			$_data,
			$_sessionName,
			$_cookieName,
			$_isLoggedIn,
			$_admSessionName,
			$_isAdmLoggedIn;
	
	public function __construct($user = null) {
		$this->_db = DB::getInstance();
		$this->_sessionName = Config::get('session/session_name');
		$this->_cookieName = Config::get('remember/cookie_name');
		$this->_admSessionName = Config::get('session/admin_name');
		
		if(!$user) {
			if(Session::exists($this->_sessionName)) {
				$user = Session::get($this->_sessionName);
				if($this->find($user)){
					$this->_isLoggedIn = true;
				} else {
					// process logout
				}
			}
			if(Session::exists($this->_admSessionName)) {
				$user = Session::get($this->_admSessionName);
				if($this->find($user)){
					$this->_isAdmLoggedIn = true;
				} else {
					// process logout
				}
			}
		} else {
			$this->find($user);
		}
		
	}

	public function getGroupName($group_id) {
		$data = $this->_db->get('groups', array('id', '=', $group_id));
		if($data->count()) {
			$results = $data->results();
			return htmlspecialchars($results[0]->name);
		} else {
			return false;
		}
	}
	
	public function getIP() {
		if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
		  $ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
		  $ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	public function addfriend($user1id, $user2id) {	
		$this->_db->insert('friends', array(
			'user_id' => $user1id,
			'friend_id' => $user2id
		));
		$this->_db->insert('friends', array(
			'user_id' => $user2id,
			'friend_id' => $user1id
		));
	}
	
	public function removefriend($user1, $user2) {	
		$data = $this->_db->get('friends', array('user_id', '=', $user1));
		if($data->count()) {
			$numrows = (count($data->results()));
			$no = 0;
			$finalno = 0;
			while ($no < $numrows) {
				$results = $data->results();
				$isfriend = $results[$no]->friend_id;
					if ($isfriend == $user2) {
						$results = $data->results();
						$finalno = $results[$no]->id;
						$no = ($numrows + 1);
					}
				$no = ($no + 1);
			}
		}
		$this->_db->delete('friends', array('id', '=', $finalno));
		
		$data = $this->_db->get('friends', array('user_id', '=', $user2));
		if($data->count()) {
			$numrows = (count($data->results()));
			$no = 0;
			$finalno = 0;
			while ($no < $numrows) {
				$results = $data->results();
				$isfriend = $results[$no]->friend_id;
					if ($isfriend == $user1) {
						$results = $data->results();
						$finalno = $results[$no]->id;
						$no = ($numrows + 1);
					}
				$no = ($no + 1);
			}
		}
		$this->_db->delete('friends', array('id', '=', $finalno));

	}
	
	public function isfriend($user1, $user2) {
		$returnbool = 0;
		$data = $this->_db->get('friends', array('user_id', '=', $user1));
		if($data->count()) {
			$numrows = (count($data->results()));
			$no = 0;
			while ($no < $numrows) {
				$results = $data->results();
				$isfriend = $results[$no]->friend_id;
					if ($isfriend == $user2) {
						$returnbool = 1;
						$no = ($numrows + 1);
					}
				$no = ($no + 1);
			}
		}
		return $returnbool;
	}
	
	public function listFriends($user_id) {
		$data = $this->_db->get('friends', array('user_id', '=', $user_id));
		if($data->count()) {
			return $data->results();
		} else { 
		return false;
		}
	}
	
	public function update($fields = array(), $id = null) {
	
		if(!$id && $this->isLoggedIn()) {
			$id = $this->data()->id;
		}
	
		if(!$this->_db->update('users', $id, $fields)) {
			throw new Exception('There was a problem updating your details.');
		}
	}
	
	public function create($fields = array()) {
		if(!$this->_db->insert('users', $fields)) {
			throw new Exception('There was a problem creating an account.');
		}
	}
	
	public function find($user = null) {
		if ($user) {
			$field = (is_numeric($user)) ? 'id' : 'username';
			$data = $this->_db->get('users', array($field, '=', $user));
			
			if($data->count()) {
				$this->_data = $data->first();
				return true;
			}
		}
		return false;
	}
	
	public function IdToName($id = null) {
		if ($id) {
			$data = $this->_db->get('users', array('id', '=', $id));
			
			if($data->count()) {
				$results = $data->results();
				return $results[0]->username;
			}
		}
		return false;
	}
	
	public function IdToMCName($id = null) {
		if ($id) {
			$data = $this->_db->get('users', array('id', '=', $id));
			
			if($data->count()) {
				$results = $data->results();
				return $results[0]->mcname;
			}
		}
		return false;
	}
	
	public function login($username = null, $password = null, $remember = false) {
		if(!$username && !$password && $this->exists()){
			Session::put($this->_sessionName, $this->data()->id);
		} else {
			$user = $this->find($username);
			if($user){
				if($this->data()->pass_method == "default"){ // Default, use password_verify
					if(password_verify($password, $this->data()->password)) {
						Session::put($this->_sessionName, $this->data()->id);
					
						if($remember) {
							$hash = Hash::unique();
							$hashCheck = $this->_db->get('users_session', array('user_id', '=', $this->data()->id));
						
							if(!$hashCheck->count()) {
								$this->_db->insert('users_session', array(
									'user_id' => $this->data()->id,
									'hash' => $hash
								));
							} else {
								$hash = $hashCheck->first()->hash;
							}
						
							Cookie::put($this->_cookieName, $hash, Config::get('remember/cookie_expiry'));
						
						}
					
						return true;
					}
				} else if($this->data()->pass_method == "wordpress"){ // Use phpass
					$phpass = new PasswordHash(8, FALSE);
					if($phpass->CheckPassword($password, $this->data()->password)){
						Session::put($this->_sessionName, $this->data()->id);
					
						if($remember) {
							$hash = Hash::unique();
							$hashCheck = $this->_db->get('users_session', array('user_id', '=', $this->data()->id));
						
							if(!$hashCheck->count()) {
								$this->_db->insert('users_session', array(
									'user_id' => $this->data()->id,
									'hash' => $hash
								));
							} else {
								$hash = $hashCheck->first()->hash;
							}
						
							Cookie::put($this->_cookieName, $hash, Config::get('remember/cookie_expiry'));
						
						}
					
						return true;
					}
				} else if($this->data()->pass_method == "modernbb"){ // Use sha
					if(sha1($password) == $this->data()->password){
						Session::put($this->_sessionName, $this->data()->id);
					
						if($remember) {
							$hash = Hash::unique();
							$hashCheck = $this->_db->get('users_session', array('user_id', '=', $this->data()->id));
						
							if(!$hashCheck->count()) {
								$this->_db->insert('users_session', array(
									'user_id' => $this->data()->id,
									'hash' => $hash
								));
							} else {
								$hash = $hashCheck->first()->hash;
							}
						
							Cookie::put($this->_cookieName, $hash, Config::get('remember/cookie_expiry'));
						
						}
					
						return true;
					}
				}
			}
		}
		return false;
	}
	
	public function adminLogin($username = null, $password = null) {
		if(!$username && !$password && $this->exists()){
			Session::put($this->_admSessionName, $this->data()->id);
		} else {
			$user = $this->find($username);
			if($user){
				if(password_verify($password, $this->data()->password)) {
					Session::put($this->_admSessionName, $this->data()->id);

					$hash = Hash::unique();
					$hashCheck = $this->_db->get('users_admin_session', array('user_id', '=', $this->data()->id));
				
					if(!$hashCheck->count()) {
						$this->_db->insert('users_admin_session', array(
							'user_id' => $this->data()->id,
							'hash' => $hash
						));
					} else {
						$hash = $hashCheck->first()->hash;
					}
				
					Cookie::put($this->_cookieName . "_adm", $hash, 3600);

				
					return true;
				}
			}
		}
		return false;
	}
	
	public function hasPermission($key) {
		$group = $this->_db->get('groups', array('id', '=', $this->data()->group));
		
		if($group->count()) {
			$permissions = json_decode($group->first()->permissions, true);
			
			if($permissions[$key] == true) {
				return true;
			}
			return false;
		}
		
	}
	
	public function getGroup($id, $html = null, $large = null) {
		$data = $this->_db->get('users', array('id', '=', $id));
		if($html === null){
			if($large === null){
				$results = $data->results();
				return $results[0]->group_id;
			} else {
				$results = $data->results();
				$data = $this->_db->get('groups', array('id', '=', $results[0]->group_id));
				$results = $data->results();
				return $results[0]->group_html_lg;
			}
		} else {
			$results = $data->results();
			$data = $this->_db->get('groups', array('id', '=', $results[0]->group_id));
			$results = $data->results();
			return $results[0]->group_html;
		}
	}
	
	public function getSignature($id) {
		$data = $this->_db->get('users', array('id', '=', $id));
		$results = $data->results();
		if(!empty($results[0]->signature)){
			return $results[0]->signature;
		} else {
		return "";
		}
	}
	
	public function getAvatar($id, $path = null) {
		$exts = array('gif','png','jpg');
		foreach($exts as $ext) {
			if(file_exists(ROOT_PATH . "/avatars/" . $id . "." . $ext)){
				$avatar_path = "/avatars/" . $id . "." . $ext;
				break;
			}
		}
		if(isset($avatar_path)){
			return $avatar_path;
		} else {
			return false;
		}
	}
	
	public function hasInfraction($user_id){
		$data = $this->_db->get('infractions', array('punished', '=', $user_id))->results();
		if(empty($data)){
			return false;
		} else {
			$return = array();
			$n = 0;
			foreach($data as $infraction){
				if($infraction->acknowledged == '0'){
					$return[$n]["id"] = $infraction->id;
					$return[$n]["staff"] = $infraction->staff;
					$return[$n]["reason"] = $infraction->reason;
					$return[$n]["date"] = $infraction->infraction_date;
					$n++;
				}
			}
			return $return;
		}
	}

	public function exists() {
		return (!empty($this->_data)) ? true : false;
	}
	
	public function logout() {
		
		$this->_db->delete('users_session', array('user_id', '=', $this->data()->id));
		
		Session::delete($this->_sessionName);
		Cookie::delete($this->_cookieName);
	}
	
	// Process logout if user is admin
	public function admLogout() {
		
		$this->_db->delete('users_admin_session', array('user_id', '=', $this->data()->id));
		
		Session::delete($this->_admSessionName);
		Cookie::delete($this->_cookieName . "_adm");
	}
	
	// Returns the currently logged in user's data
	public function data() {
		return $this->_data;
	}
	
	// Returns true if the current user is logged in
	public function isLoggedIn() {
		return $this->_isLoggedIn;
	}
	
	// Returns true if the current user is authenticated as an administrator
	public function isAdmLoggedIn() {
		return $this->_isAdmLoggedIn;
	}
	
	// Return a comma separated string of all users - this is for the new private message dropdown
	public function listAllUsers() {
		$data = $this->_db->get('users', array('id', '<>', '0'))->results();
		$return = "";
		$i = 1;
		
		foreach($data as $item){
			if($i != count($data)){
				$return .= '"' . $item->username . '",';
			} else {
				$return .= '"' . $item->username . '"';
			}
			$i++;
		}
		return $return;
	}
	
	// Return an ID from a username
	public function NameToId($name = null){
		if($name){
			$data = $this->_db->get('users', array('username', '=', $name));
			
			if($data->count()){
				$results = $data->results();
				return $results[0]->id;
			}
		}
		return false;
	}
	
	// Get a list of PMs a user has access to
	public function listPMs($user_id = null){
		if($user_id){
			$return = array(); // Array to return containing info of PMs
			
			// First, get a list of PMs the user has created themselves
			$data = $this->_db->orderWhere('private_messages', 'author_id = ' . $user_id, 'sent_date', 'DESC');
			
			if($data->count()){
				$data = $data->results();
				foreach($data as $result){
					// Get a list of users who are in this conversation and return them as an array
					$pms = $this->_db->get('private_messages_users', array('pm_id', '=', $result->id))->results();
					$users = array(); // Array containing users with permission
					foreach($pms as $pm){
						$users[] = $pm->user_id;
					}
					$users[] = $result->author_id; // Don't forget the author!
					
					$return[$result->id]['id'] = $result->id;
					$return[$result->id]['title'] = $result->title;
					$return[$result->id]['date'] = $result->sent_date;
					$return[$result->id]['users'] = $users;
				}
			}
			
			// Next, get a list of PMs which the user has been added to
			$data = $this->_db->get('private_messages_users', array('user_id', '=', $user_id));
			
			if($data->count()){
				$data = $data->results();
				foreach($data as $result){
					// Get a list of users who are in this conversation and return them as an array
					$pms = $this->_db->get('private_messages_users', array('pm_id', '=', $result->pm_id))->results();
					$users = array(); // Array containing users with permission
					foreach($pms as $pm){
						$users[] = $pm->user_id;
					}
					
					// Get the PM data
					$pm = $this->_db->get('private_messages', array('id', '=', $result->pm_id))->results();
					$pm = $pm[0];
					
					$users[] = $pm->author_id; // Don't forget the author!
					
					$return[$pm->id]['id'] = $pm->id;
					$return[$pm->id]['title'] = $pm->title;
					$return[$pm->id]['date'] = $pm->sent_date;
					$return[$pm->id]['users'] = $users;
				}
			}
			return $return;
		}
		return false;
	}

	// Get a specific private message, and see if the user actually has permission to view it
	public function getPM($pm_id = null, $user_id = null){
		if($user_id && $pm_id){
			// Get the PM - is the user the author?
			$data = $this->_db->get('private_messages', array('id', '=', $pm_id));
			if($data->count()){
				$data = $data->results();
				$data = $data[0];
				if($data->author_id != $user_id){
					// User is not the author, do they have permission to view the PM?
					$pms = $this->_db->get('private_messages_users', array('pm_id', '=', $pm_id))->results();
					foreach($pms as $pm){
						if($pm->user_id == $user_id){
							$has_permission = true;
							$pm_user_id = $pm->id;
							break;
						}
					}

					if($has_permission != true){
						return false; // User doesn't have permission
					}
					// Set message to "read"
					if($pm->read == 0){
						$this->_db->update('private_messages_users', $pm_user_id, array(
							'`read`' => 1
						));
					}
				}
				// User has permission, return the PM information
				
				// Get a list of users in the conversation
				if(!isset($pms)){
					$pms = $this->_db->get('private_messages_users', array('pm_id', '=', $pm_id))->results();
				}
				
				$users = array(); // Array to store users
				foreach($pms as $pm){
					$users[] = $pm->user_id;
				}
				
				$users[] = $data->author_id; // Don't forget the author!
				
				return array($data, $users);
			}
		}
		return false;
	}
	
	// Delete a user's access to view the PM, or if they're the author, the PM itself
	public function deletePM($pm_id = null, $user_id = null){
		if($user_id && $pm_id){
			// Is the user the author?
			$data = $this->_db->get('private_messages', array('id', '=', $pm_id));
			if($data->count()){
				$data = $data->results();
				$data = $data[0];
				if($data->author_id != $user_id){
					// User is not the author, only delete 
					$pms = $this->_db->get('private_messages_users', array('pm_id', '=', $pm_id))->results();
					foreach($pms as $pm){
						if($pm->user_id == $user_id){
							// get the ID and delete
							$id = $pm->id;
							$this->_db->delete('private_messages_users', array('id', '=', $id));
							return true;
						}
					}
				} else {
					// User is the author, delete the PM altogether
					$this->_db->delete('private_messages_users', array('pm_id', '=', $pm_id));
					$this->_db->delete('private_messages', array('id', '=', $pm_id));
					return true;
				}
			}
		}
		return false;
	}
	
	// Get the number of unread PMs for the specified user
	public function getUnreadPMs($user_id = null){
		if($user_id){
			$pms = $this->_db->get('private_messages_users', array('user_id', '=', $user_id));
			if($pms->count()){
				$pms = $pms->results();
				$count = 0;
				foreach($pms as $pm){
					if($pm->read == 0){
						$count++;
					}
				}
				return $count;
			} else {
				return 0;
			}
		}
		return false;
	}
}