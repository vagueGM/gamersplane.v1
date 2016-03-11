<?
	class Post {
		protected $postID;
		protected $threadID;
		protected $title;
		protected $author;
		protected $message;
		protected $datePosted;
		protected $lastEdit = null;
		protected $timesEdited = 0;
		protected $postAs;

		protected $rolls = array();
		protected $draws = array();

		protected $modified = false;
		protected $edited = false;
		
		public function __construct($loadData = null) {
			if ($loadData == null) 
				return true;

			if ((int) $loadData == $loadData) {
				global $mysql, $mongo;

				$loadData = $mongo->posts->findOne(['postID' => $loadData]);
				$user = $mysql->query("SELECT u.userID, u.username, um.metaValue avatarExt, u.lastActivity FROM users u LEFT JOIN usermeta um ON u.userID = um.userID AND um.metaKey = 'avatarExt' WHERE u.userID = {$loadData}")->fetch();
				$loadData = array_merge($loadData, ['author' => $user]);
			}
			if (is_array($loadData)) {
				foreach (get_object_vars($this) as $key => $value) {
					if (in_array($key, array('authorID', 'rolls', 'draws', 'modified', 'edited'))) 
						continue;
					if (!array_key_exists($key, $loadData)) 
						continue;//throw new Exception('Missing data for '.$this->forumID.': '.$key);
					$this->$key = $loadData[$key];
				}
				$this->datePosted = $this->datePosted->sec;
				$this->lastEdit = $this->lastEdit->sec;
				$this->author = new stdClass();
				$this->author->userID = $loadData['authorID'];
				$this->author->username = $loadData['author']['username'];
				$this->author->avatarExt = $loadData['author']['avatarExt'];
				$this->author->lastActivity = $loadData['author']['lastActivity'];

				if (sizeof($loadData['rolls'])) {
					foreach ($loadData['rolls'] as $roll) {
						$rollObj = RollFactory::getRoll($roll['type']);
						$rollObj->forumLoad($roll);
						$this->rolls[] = $rollObj;
					}
				}

				if (sizeof($loadData['draws'])) 
					foreach ($loadData['draws'] as $draw) 
						$this->draws[$draw['deckID']] = $draw;
			}
		}

		public function __set($key, $value) {
			if (property_exists($this, $key)) $this->$key = $value;
		}

		public function __get($key) {
			if (property_exists($this, $key)) return $this->$key;
		}

		public function getPostID() {
			return $this->postID;
		}

		public function setThreadID($threadID) {
			if (intval($threadID)) 
				$this->threadID = intval($threadID);
		}

		public function getThreadID() {
			return $this->threadID;
		}

		public function setTitle($value) {
			$title = sanitizeString(htmlspecialchars_decode($value));
			if ($title != $this->getTitle()) 
				$this->modified = true;
			$this->title = $title;
		}

		public function getTitle($pr = false) {
			if ($pr) 
				return printReady($this->title);
			else 
				return $this->title;
		}

		public function getAuthor($key = null) {
			if (property_exists($this->author, $key)) 
				return $this->author->$key;
			else 
				return $this->author;
		}

		public function setMessage($value) {
			global $mysql, $currentUser;

			$isForumAdmin = $mysql->query("SELECT userID FROM forumAdmins WHERE userID = {$currentUser->userID} AND forumID = 0");
			$message = sanitizeString($value, $isForumAdmin->rowCount()?'!strip_tags':'');
			if ($message != $this->getMessage()) 
				$this->modified = true;
			$this->message = $message;
		}

		public function getMessage($pr = false) {
			if ($pr) 
				return printReady($this->message);
			else 
				return $this->message;
		}

		public static function cleanNotes($message) {
			global $currentUser;

			preg_match_all('/\[note="?(\w[\w\. +;,]+?)"?](.*?)\[\/note\][\n\r]*/ms', $message, $matches, PREG_SET_ORDER);
			if (sizeof($matches)) {
				foreach ($matches as $match) {
					$noteTo = preg_split('/[^\w\.]+/', $match[1]);
					if (!in_array($currentUser->username, $noteTo)) 
						$message = str_replace($match[0], '', $message);
				}
			}
			return trim($message);
		}

		public function getDatePosted($format = null) {
			if ($format != null) 
				return date($format, strtotime($this->datePosted));
			else 
				return $this->datePosted;
		}

		public function getLastEdit() {
			return $this->lastEdit;
		}

		public function setPostAs($value) {
			$this->postAs = intval($value)?intval($value):null;
		}

		public function addRollObj($rollObj) {
			$this->rolls[] = $rollObj;
		}

		public function updateEdited() {
			$this->edited = true;
			$this->timesEdited += 1;
		}

		public function getPostAs() {
			return $this->postAs;
		}

		public function savePost() {
			global $currentUser, $mysql, $mongo;

			if ($this->postID == null) {
				$addPost = $mysql->prepare("INSERT INTO posts SET threadID = {$this->threadID}, title = :title, authorID = {$currentUser->userID}, message = :message, datePosted = :datePosted, postAs = ".($this->postAs?$this->postAs:'NULL'));
				$addPost->bindValue(':title', $this->title);
				$addPost->bindValue(':message', $this->message);
				$addPost->bindValue(':datePosted', date('Y-m-d H:i:s'));
				$addPost->execute();
				$this->postID = $mysql->lastInsertId();
			} else {
				$updatePost = $mysql->prepare("UPDATE posts SET title = :title, message = :message, postAs = ".($this->postAs?$this->postAs:'NULL').($this->edited?", lastEdit = NOW(), timesEdited = {$this->timesEdited}":'')." WHERE postID = {$this->postID}");
				$updatePost->bindValue(':title', $this->title);
				$updatePost->bindValue(':message', $this->message);
				$updatePost->execute();
			}

			foreach ($this->rolls as $roll) 
				$roll->forumSave($this->postID);

			if (sizeof($this->draws)) {
				$addDraw = $mysql->prepare("INSERT INTO deckDraws SET postID = {$this->postID}, deckID = :deckID, type = :type, cardsDrawn = :cardsDrawn, reveals = :reveals, reason = :reason");
				foreach($this->draws as $deckID => $draw) {
					$gameID = (int) $mysql->query("SELECT f.gameID FROM threads t INNER JOIN forums f ON f.forumID = t.forumID WHERE t.threadID = {$this->threadID} LIMIT 1")->fetchColumn();
					$mongo->games->update(array('gameID' => $gameID, 'decks.deckID' => (int) $deckID), array('$inc' => array('decks.$.position' => (int) $draw['draw'])));
					$addDraw->bindValue('deckID', $deckID);
					$addDraw->bindValue('type', $draw['type']);
					$addDraw->bindValue('cardsDrawn', $draw['cardsDrawn']);
					$addDraw->bindValue('reveals', str_repeat('0', $draw['draw']));
					$addDraw->bindValue('reason', $draw['reason']);
					$addDraw->execute();
				}
			}

			return $this->postID;
		}

		public function getModified() {
			return $this->modified;
		}

		public function delete() {
			global $mysql;

			$mysql->query('DELETE FROM posts, rolls, deckDraws USING posts LEFT JOIN rolls ON posts.postID = rolls.postID LEFT JOIN deckDraws ON posts.postID = deckDraws.postID WHERE posts.postID = '.$this->postID);
		}

		public function dumpObj() {
			var_dump($this);
		}
	}
?>