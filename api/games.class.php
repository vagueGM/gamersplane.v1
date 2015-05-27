<?
	class games {
		function __construct() {
			global $loggedIn, $pathOptions;

			if ($pathOptions[0] == 'details') 
				$this->details($_POST['gameID']);
			elseif ($pathOptions[0] == 'apply') 
				$this->apply();
			elseif ($pathOptions[0] == 'invite' && sizeof($pathOptions) == 1 && intval($_POST['gameID']) && strlen($_POST['user'])) 
				$this->invite($_POST['gameID'], $_POST['user']);
			elseif ($pathOptions[0] == 'invite' && ($pathOptions[1] == 'withdraw' || $pathOptions[1] == 'reject') && intval($_POST['gameID']) && strlen($_POST['userID'])) 
				$this->removeInvite($_POST['gameID'], $_POST['userID']);
			elseif ($pathOptions[0] == 'invite' && $pathOptions[1] == 'accept' && intval($_POST['gameID'])) 
				$this->acceptInvite($_POST['gameID']);
/*			elseif ($pathOptions[0] == 'view' && intval($_POST['pmID'])) 
				$this->displayPM($_POST['pmID']);
			elseif ($pathOptions[0] == 'delete' && intval($_POST['pmID'])) 
				$this->deletePM($_POST['pmID']);*/
			else 
				displayJSON(array('failed' => true));
		}

		public function details($gameID) {
			require_once(FILEROOT.'/../javascript/markItUp/markitup.bbcode-parser.php');
			global $mysql, $mongo, $currentUser;

			$gameID = intval($gameID);
			if (!$gameID) 
				displayJSON(array('failed' => true));
			$gameInfo = $mysql->query("SELECT g.gameID, g.title, g.system, g.gmID, u.username gmUsername, g.created, g.postFrequency, g.numPlayers, g.charsPerPlayer, g.description, g.charGenInfo, g.forumID, p.`read` readPermissions, g.groupID, g.status FROM games g INNER JOIN users u ON g.gmID = u.userID INNER JOIN forums_permissions_general p ON g.forumID = p.forumID WHERE g.gameID = $gameID");
			if (!$gameInfo->rowCount()) 
				displayJSON(array('failed' => true, 'noGame' => true));
			$gameInfo = $gameInfo->fetch();
			$isGM = $gameInfo['gmID'] == $currentUser->userID?true:false;
			$gameInfo['gameID'] = (int) $gameInfo['gameID'];
			$gameInfo['title'] = printReady($gameInfo['title']);
			$system = $mongo->systems->findOne(array('_id' => $gameInfo['system']), array('name' => 1));
			$gameInfo['system'] = array('_id' => $gameInfo['system'], 'name' => $system['name']);
			$gameInfo['gm'] = array('userID' => (int) $gameInfo['gmID'], 'username' => $gameInfo['gmUsername']);
			unset($gameInfo['gmID'], $gameInfo['gmUsername']);
			$gameInfo['created'] = date('F j, Y g:i a', strtotime($gameInfo['created']));
			$gameInfo['postFrequency'] = explode('/', $gameInfo['postFrequency']);
			$gameInfo['postFrequency'][0] = (int) $gameInfo['postFrequency'][0];
			$gameInfo['postFrequency'][1] = $gameInfo['postFrequency'][1] == 'd'?'day':'week';
			$gameInfo['numPlayers'] = (int) $gameInfo['numPlayers'];
			$gameInfo['charsPerPlayer'] = (int) $gameInfo['charsPerPlayer'];
			$gameInfo['description'] = strlen($gameInfo['description'])?printReady($gameInfo['description']):'None Provided';
			$gameInfo['charGenInfo'] = strlen($gameInfo['charGenInfo'])?printReady($gameInfo['charGenInfo']):'None Provided';
			$gameInfo['forumID'] = (int) $gameInfo['forumID'];
			$gameInfo['readPermissions'] = (bool) $gameInfo['readPermissions'];
			$gameInfo['groupID'] = (int) $gameInfo['groupID'];
			$gameStatus = array('o' => 'Open', 'p' => 'Private', 'c' => 'Closed');
			$gameInfo['status'] = $gameStatus[$gameInfo['status']];
			$players = $mysql->query("SELECT p.userID, u.username, p.approved, p.isGM, p.primaryGM FROM players p INNER JOIN users u ON p.userID = u.userID WHERE p.gameID = {$gameID} ORDER BY p.approved, u.username")->fetchAll();
			$gameInfo['approvedPlayers'] = 0;
			array_walk($players, function (&$player, $key) {
				$player['approved'] = $player['approved']?true:false;
				$player['isGM'] = $player['isGM']?true:false;
				$player['primaryGM'] = $player['primaryGM']?true:false;
				$player['characters'] = array();
				if ($player['approved']) 
					$gameInfo['approvedPlayers']++;
			});
			$characters = $mysql->query("SELECT characterID, userID, label, approved FROM characters WHERE gameID = {$gameID} ORDER BY label");
			foreach ($characters as $character) {
				$character['characterID'] = (int) $character['characterid'];
				$character['userID'] = (int) $character['userID'];
				$character['approved'] = (bool) $character['approved'];
				$players[$character['userID']]['characters'][] = $character;
			}
			$invites = $mysql->query("SELECT u.userID, u.username FROM gameInvites i INNER JOIN users u ON i.invitedID = u.userID WHERE i.gameID = {$gameID}")->fetchAll();
			array_walk($invites, function (&$invite, $key) {
				$invite['userID'] = (int) $invite['userID'];
			});
			displayJSON(array('details' => $gameInfo, 'players' => $players, 'invites' => $invites));
		}

		public function apply() {
			global $loggedIn, $currentUser, $mysql;
			if (!$loggedIn) 
				displayJSON(array('failed' => true, 'loggedOut' => true), true);

			$gameID = intval($_POST['gameID']);
			list($numPlayers, $playerCount) = $mysql->query("SELECT g.numPlayers, COUNT(*) playerCount FROM games g INNER JOIN players p ON g.gameID = p.gameID WHERE g.gameID = {$gameID} AND p.approved = 0")->fetch(PDO::FETCH_NUM);
			if ($numPlayers > $playerCount - 1) 
				$mysql->query("INSERT INTO players SET gameID = {$gameID}, userID = {$currentUser->userID}");
			else 
				displayJSON(array('failed' => true, 'gameFull' => true));

			displayJSON(array('success' => true));
		}

		public function invite($gameID, $user) {
			global $mysql, $currentUser;

			$gameID = intval($gameID);
			$isGM = $mysql->query("SELECT isGM FROM players WHERE userID = {$currentUser->userID} AND gameID = {$gameID}");
			if ($isGM->rowCount()) {
				$userCheck = $mysql->prepare("SELECT u.userID, u.username, u.email, p.approved FROM users u LEFT JOIN players p ON u.userID = p.userID AND p.gameID = {$gameID} WHERE u.username = :username LIMIT 1");
				$userCheck->execute(array(':username' => $user));
				if (!$userCheck->rowCount())
					displayJSON(array('failed' => true, 'errors' => array('invalidUser')), true);
				$user = $userCheck->fetch();
				if ($user['approved']) 
					displayJSON(array('failed' => true, 'errors' => array('alreadyInGame')), true);
				try {
					$mysql->query("INSERT INTO gameInvites SET gameID = {$gameID}, invitedID = {$user['userID']}");
				} catch (Exception $e) {
					displayJSON(array('failed' => true, 'errors' => 'alreadyInvited'), true);
				}
				$gameInfo = $mysql->query("SELECT g.title, g.system, s.fullName FROM games g INNER JOIN systems s ON g.system = s.shortName WHERE g.gameID = {$gameID}")->fetch();
				ob_start();
				include('emails/gameInviteEmail.php');
				$email = ob_get_contents();
				ob_end_clean();
				@mail($user['email'], "Game Invite", $email, "Content-type: text/html\r\nFrom: Gamers Plane <contact@gamersplane.com>");
				addGameHistory($gameID, 'playerInvited', $currentUser->userID, 'NOW()', 'user', $user['userID']);
				displayJSON(array('success' => true, 'user' => array('userID' => (int) $user['userID'], 'username' => $user['username'])));
			} else 
				displayJSON(array('failed' => true, 'errors' => 'notGM'));
		}

		public function removeInvite($gameID, $userID) {
			global $mysql, $currentUser;

			$gameID = intval($gameID);
			$userID = intval($userID);
			$isGM = $mysql->query("SELECT primaryGM FROM players WHERE isGM = 1 AND userID = {$currentUser->userID} AND gameID = {$gameID}");
			if ($isGM->rowCount() || $currentUser->userID == $userID) {
				$mysql->query("DELETE FROM gameInvites WHERE gameID = {$gameID} AND invitedID = {$userID}");
				addGameHistory($gameID, 'inviteRemoved', $currentUser->userID, 'NOW()', 'user', $userID);
				displayJSON(array('success' => true, 'userID' => (int) $userID));
			} else 
				displayJSON(array('failed' => true, 'errors' => 'noPermission'));
		}

		public function acceptInvite($gameID) {
			global $mysql, $currentUser;

			$gameID = intval($gameID);
			$userID = (int) $currentUser->userID;
			$validGame = $mysql->query("SELECT g.groupID FROM gameInvites i INNER JOIN games g ON i.gameID = g.gameID WHERE i.gameID = {$gameID} AND i.invitedID = {$userID}");
			if ($validGame->rowCount()) {
				$mysql->query("INSERT INTO players SET gameID = {$gameID}, userID = {$userID}, approved = 1");
				$groupID = $validGame->fetchColumn();
				$mysql->query("INSERT INTO forums_groupMemberships SET groupID = {$groupID}, userID = {$currentUser->userID}");
				$mysql->query("DELETE FROM gameInvites WHERE gameID = {$gameID} AND invitedID = {$userID}");
				addGameHistory($gameID, 'inviteAccepted', $currentUser->userID, 'NOW()', 'user', $playerID);
				displayJSON(array('success' => true, 'userID' => (int) $userID));
			} else 
				displayJSON(array('failed' => true, 'errors' => 'noPermission'));
		}

		public function checkAllowed($pmID) {
			global $mongo, $currentUser;

			$pmID = intval($pmID);
			$pm = $mongo->pms->findOne(array('pmID' => $pmID, '$or' => array(array('sender.userID' => $currentUser->userID), array('recipient.userID' => $currentUser->userID, 'deleted' => false))));
			displayJSON(array('allowed' => $pm?true:false));
		}

		public function sendPM() {
			global $mysql, $mongo, $currentUser;

			$sender = (object) array('userID' => $currentUser->userID, 'username' => $currentUser->username);
			$recipient = sanitizeString(preg_replace('/[^\w.]/', '', $_POST['username']));
			$recipient = $mysql->query("SELECT userID, username FROM users WHERE username = '{$recipient}'")->fetch(PDO::FETCH_OBJ);
			$recipient->userID = (int) $recipient->userID;
			$recipient->read = false;
			$recipient->deleted = false;
			$replyTo = intval($_POST['replyTo']) > 0?intval($_POST['replyTo']):null;
			if ($sender->userID == $recipient->userID) 
				displayJSON(array('mailingSelf' => true));
			else {
				$history = null;
				if ($replyTo) {
					$parent = $mongo->pms->findOne(array('pmID' => $replyTo));
					$history = array($replyTo);
					if ($parent['history']) 
						$history = array_merge($history, $parent['history']);
				}
				$mongo->pms->insert(array('pmID' => mongo_getNextSequence('pmID'), 'sender' => $sender, 'recipients' => array($recipient), 'title' => sanitizeString($_POST['title']), 'message' => sanitizeString($_POST['message']), 'datestamp' => date('Y-m-d H:i:s'), 'replyTo' => $replyTo, 'history' => $history));
				displayJSON(array('sent' => true));
			}
		}

		public function deletePM($pmID) {
			global $mongo, $currentUser;

			$pmID = intval($pmID);
			$pm = $mongo->pms->findOne(array('pmID' => $pmID, '$or' => array(array('sender.userID' => $currentUser->userID), array('recipients.userID' => $currentUser->userID))));
			if ($pm === null) 
				displayJSON(array('noMatch' => true));
			elseif ($pm['sender']['userID'] == $currentUser->userID) {
				$allowDelete = true;
				foreach ($pm['recipients'] as $recipient) 
					if ($recipient['read'] && !$recipient['deleted']) 
						$allowDelete = false;

				if ($allowDelete) 
					$mongo->pms->remove(array('pmID' => $pmID));

				displayJSON(array('deleted' => true));
			} else {
				$mongo->pms->update(array('pmID' => $pmID, 'recipients.userID' => $currentUser->userID), array('$set' => array('recipients.$.deleted' => true)));

				displayJSON(array('deleted' => true));
			}
		}
	}
?>