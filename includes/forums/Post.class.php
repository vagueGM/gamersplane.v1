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

				$loadData = $mongo->posts->findOne(['postID' => (int) $loadData]);
				$user = $mysql->query("SELECT u.userID, u.username, um.metaValue avatarExt, u.lastActivity FROM users u LEFT JOIN usermeta um ON u.userID = um.userID AND um.metaKey = 'avatarExt' WHERE u.userID = {$loadData['authorID']}")->fetch();
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
//				if ($this->author['avatarExt']) {
					$avatar = User::getAvatar($this->author['userID'], $this->author['avatarExt']);
					$userAvatarSize = getimagesize(FILEROOT.$avatar);
					$this->author['avatar'] = [
						'path' => $avatar,
						'width' => (int) $userAvatarSize[0],
						'height' => (int) $userAvatarSize[1]
					];
					unset($this->author['avatarExt']);
//				} else 
//					$this->author['avatarExt'] = null;
				$this->datePosted = $this->datePosted->sec;
				$this->lastEdit = $this->lastEdit->sec;

				if (sizeof($loadData['rolls'])) {
					foreach ($loadData['rolls'] as $roll) {
						$rollObj = RollFactory::getRoll($roll['type']);
						$rollObj->forumLoad($roll);
						$this->rolls[] = $rollObj;
					}
				}

				if (sizeof($loadData['draws'])) 
					$this->draws = $loadData['draws'];
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
			global $mongo, $currentUser;

			$isForumAdmin = $mongo->forums->findOne(['forumID' => 0, 'admins' => $currentUser->userID], ['forumID' => true]);
			$message = sanitizeString($value, $isForumAdmin != null?'!strip_tags':'');
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

		public function savePost($datePosted = null) {
			global $currentUser, $mysql, $mongo;

			$postData = [
				'postID' => $this->postID?$this->postID:null,
				'threadID' => $this->threadID,
				'title' => $this->title,
				'authorID' => $this->author->userID,
				'message' => $this->message,
				'datePosted' => $datePosted,
				'lastEdit' => $this->lastEdit?$this->lastEdit:null,
				'timesEdited' => $this->timesEdited,
				'rolls' => null,
				'draws'	=> sizeof($this->draws)?$this->draws:null
			];
			if ($this->postAs) 
				$postData['postAs'] = $this->postAs;
			if (sizeof($this->rolls)) 
				foreach ($this->rolls as $roll) 
					$postData['rolls'][] = $roll->mongoFormat();

			if ($this->postID == null) {
				$postData['postID'] = mongo_getNextSequence('postID');
				$postData['authorID'] = $currentUser->userID;
				$postData['datePosted'] = new MongoDate();
				$mongo->posts->insert($postData);
				$this->postID = $postData['postID'];
				$this->datePosted = $postData['datePosted']->sec;
			} else {
				unset($postData['postID'], $postData['threadID'], $postData['authorID'], $postData['datePosted']);
				$mongo->posts->update(['postID' => $this->postID], ['$set' => $postData]);
			}

			return $this->postID;
		}

		public function getModified() {
			return $this->modified;
		}

		public function delete() {
			global $mongo;

			$mongo->posts->remove(['postID' => (int) $this->postID]);
		}

		public function getPostVars() {
			$post = get_object_vars($this);
			if (sizeof($post['rolls'])) {
				$post['rolls'] = [];
				foreach ($this->rolls as $roll) 
					$post['rolls'][] = $roll->apiFormat();
			} else 
				$post['rolls'] = null;

			if (sizeof($post['draws']) == 0) 
				$post['draws'] = null;
			else {
				foreach ($post['draws'] as &$draw) {
					foreach ($draw['cards'] as &$card) {
					}
				}
			}

			return $post;
		}
	}
?>