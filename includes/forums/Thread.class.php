<?
	class Thread {
		protected $threadID;
		protected $forumID;
		protected $title;
		protected $author;
		protected $datePosted;
		protected $states = array('sticky' => false, 'locked' => false);
		protected $allowRolls = false;
		protected $allowDraws = false;
		protected $postCount = 0;
		protected $lastPost = null;
		protected $lastRead = 0;

		protected $posts = array();
		protected $poll = null;

		protected $loaded = array();
		
		public function __construct($loadData = null) {
			$this->poll = new ForumPoll();

			if ($loadData == null) 
				return true;

			if (!isset($loadData['threadID'], $loadData['title'])) 
				throw new Exception('Need more thread info');
			foreach ($loadData as $key => $value) 
				if (property_exists($this, $key)) 
					$this->$key = $loadData[$key];
			$this->datePosted = $this->datePosted->sec;
			$this->states['sticky'] = $loadData['sticky'];
			$this->states['locked'] = $loadData['locked'];
			$this->lastRead = $this->lastRead?$this->lastRead->sec:0;
		}

		public function toggleValue($key) {
			if (in_array($key, array('sticky', 'locked', 'allowRolls', 'allowDraws'))) {
				if ($key == 'sticky' || $key == 'locked') $this->states[$key] = !$this->states[$key];
				else $this->$key = !$this->$key;
			}
		}

		public function __get($key) {
			if (property_exists($this, $key)) return $this->$key;
		}

		public function __set($key, $value) {
			if (property_exists($this, $key)) $this->$key = $value;
		}

		public function getStates($key = null) {
			if (array_key_exists($key, $this->states)) 
				return $this->states[$key];
			else 
				return $this->states;
		}

		public function setState($key, $value) {
			if (array_key_exists($key, $this->states) && is_bool($value)) $this->states[$key] = $value;
		}

		public function setAllowRolls($value) {
			if (is_bool($value)) $this->allowRolls = $value;
		}

		public function getAllowRolls() {
			return $this->allowRolls;
		}

		public function setAllowDraws($value) {
			if (is_bool($value)) $this->allowDraws = $value;
		}

		public function getAllowDraws() {
			return $this->allowDraws;
		}

		public function getFirstPostID() {
			return $this->firstPostID;
		}

		public function getLastPost($key = null) {
			if (array_key_exists($key, $this->lastPost)) 
				return $this->lastPost[$key];
			else 
				return $this->lastPost;
		}

		public function newPosts($markedRead) {
			global $loggedIn;
			if (!$loggedIn) 
				return false;

			if ($this->lastPost->postID > $this->lastRead && $this->lastPost->postID > $markedRead) 
				return true;
			else 
				return false;
		}

		public function getPosts($page) {
			if (sizeof($this->posts)) 
				return $this->posts;

			global $loggedIn, $currentUser, $mysql, $mongo;

			if ($page > ceil($this->postCount / PAGINATE_PER_PAGE)) 
				$page = ceil($this->postCount / PAGINATE_PER_PAGE);
			$start = ($page - 1) * PAGINATE_PER_PAGE;
			$posts = $mongo->posts->find(['threadID' => $this->threadID])->sort(['datePosted' => 1])->skip($start)->limit(PAGINATE_PER_PAGE);
			// $posts = $mysql->query("SELECT p.postID, p.threadID, p.title, u.userID, u.username, um.metaValue avatarExt, u.lastActivity, p.message, p.postAs, p.datePosted, p.lastEdit, p.timesEdited FROM posts p LEFT JOIN users u ON p.authorID = u.userID LEFT JOIN usermeta um ON u.userID = um.userID AND um.metaKey = 'avatarExt' WHERE p.threadID = {$this->threadID} ORDER BY p.datePosted LIMIT {$start}, ".PAGINATE_PER_PAGE);
			$getUsers = [];
			foreach ($posts as $post) 
				$getUsers[] = $post['authorID'];
			$rUsers = $mysql->query("SELECT u.userID, u.username, um.metaValue avatarExt, u.lastActivity FROM users u INNER JOIN usermeta um ON u.userID = um.userID AND um.metaKey = 'avatarExt' WHERE u.userID IN (".implode(',', $getUsers).")");
			$users = [];
			foreach ($rUsers as $user) 
				$users[$user['userID']] = [
					'username' => $user['username'],
					'avatarExt' => $user['avatarExt'],
					'lastActivity' => time($user['lastActivity'])
				];
			foreach ($posts as $post) 
				$this->posts[$post['postID']] = new Post(array_merge($post, ['author' => $users[$post['authorID']]]));

			return $this->posts;
		}

		public function getPoll() {
			if (in_array('poll', $this->loaded)) 
				return true;
			try {
				$this->poll = new ForumPoll($this->threadID);
				$this->loaded[] = 'poll';
				return true;
			} catch (Exception $e) { return false; }
		}

		public function getPollProperty($key) {
			return $this->poll->$key;
		}

		public function savePoll($theadID = null) {
			$this->poll->savePoll($theadID);
		}

		public function deletePoll() {
			$this->poll->delete();
			$this->poll = new ForumPoll();
			return true;
		}

		public function getVotesCast() {
			return $this->poll->getVotesCast();
		}

		public function getVoteTotal() {
			return $this->poll->getVoteTotal();
		}

		public function getVoteMax() {
			return $this->poll->getVoteMax();
		}

		public function getThreadVars() {
			return get_object_vars($this);
		}
	}
?>