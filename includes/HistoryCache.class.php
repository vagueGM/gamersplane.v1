<?
	class HistoryCache {
		private static $instance;
		protected $users = array();
		protected $characters = array();
		protected $games = array();

		private function __construct() {
		}

		public static function getInstance() {
			if (empty(self::$instance)) 
				self::$instance = new HistoryCache();
			return self::$instance;
		}

		public function fetchUser($userID) {
			global $mysql;

			$userID = (int) $userID;
			if (!isset($this->users[$userID])) {
				$username = $mysql->query("SELECT username FROM users WHERE userID = ".$userID)->fetchColumn();
				$this->users[$userID] = array(
					'userID' => $userID,
					'username' => $username
				);
			}

			return $this->users[$userID];
		}

		public function fetchCharacter($characterID, $addGame = true) {
			global $mysql, $mongo;

			$characterID = (int) $characterID;
			if (!isset($this->characters[$characterID])) {
				$charInfo = $mongo->characters->findOne(array('characterID' => $characterID), array('characterID', 'label', 'userID', 'game'));
				$charInfo['username'] = $this->fetchUser($charInfo['userID'])['username'];
				$this->characters[$characterID] = array(
					'characterID' => $charInfo['characterID'],
					'label' => $charInfo['label'],
					'user' => array(
						'userID' => (int) $charInfo['userID'],
						'username' => $charInfo['username']
					)
				);
				if ($addGame && $charInfo['game'] != null && $charInfo['game']['gameID']) 
					$this->characters[$characterID]['gameID'] = $charInfo['game']['gameID'];
			}

			return $this->characters[$characterID];
		}

		public function fetchGame($gameID) {
			global $mysql;

			$gameID = (int) $gameID;
			if (!isset($this->games[$gameID])) {
				$gameInfo = $mysql->query("SELECT g.title, u.userID, u.username FROM games g INNER JOIN users u ON g.gmID = u.userID WHERE g.gameID = {$gameID}")->fetch();
				$this->games[$gameID] = array(
					'gameID' => $gameID,
					'title' => $gameInfo['title'],
					'gm' => array(
						'userID' => (int) $gameInfo['userID'],
						'username' => $gameInfo['username']
					)
				);
				$gms = $mysql->query("SELECT userID FROM players WHERE gameID = {$gameID} AND isGM = 1")->fetchAll(PDO::FETCH_COLUMN);
				$this->games[$gameID]['gms'] = $gms;
			}

			return $this->games[$gameID];
		}
	}
?>