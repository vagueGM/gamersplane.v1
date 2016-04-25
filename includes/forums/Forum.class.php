<?
	class Forum {
		protected $forumID;
		protected $title;
		protected $description;
		protected $type;
		protected $parentID;
		protected $heritage;
		protected $order;
		protected $gameID = null;
		protected $threadCount;

		protected $postCount;
		protected $latestPost = null;
		protected $markedRead = 0;
		protected $newPosts = false;

		protected $permissions = array();
		protected $children = array();
		protected $threads = array();

		public function __construct($forumID = null, $forumData = null) {
			if ($forumID === null) 
				return true;

				$this->forumID = (int) $forumID;
			foreach (get_object_vars($this) as $key => $value) {
				if (in_array($key, array('children'))) 
					continue;
				if (!array_key_exists($key, $forumData)) 
					continue;//throw new Exception('Missing data for '.$this->forumID.': '.$key);
				$this->__set($key, $forumData[$key]);
			}
			if ($this->latestPost['threadID'] != null) 
				$this->latestPost['datePosted'] = $this->latestPost['datePosted']->sec;
		}

		public function __set($key, $value) {
			if ($key == 'forumID' && intval($value)) 
				$this->forumID = intval($value);
			elseif (in_array($key, array('title', 'description', 'heritage', 'permissions'))) 
				$this->$key = $value;
			elseif ($key == 'type' && in_array(strtolower($value), array('f', 'c'))) 
				$this->type = strtolower($value);
			elseif (in_array($key, array('parentID', 'order', 'threadCount', 'postCount', 'markedRead'))) 
				$this->$key = intval($value);
			elseif ($key == 'newPosts') 
				$this->newPosts = $value?true:false;
			elseif ($key == 'gameID' && (intval($value) || $value == null)) $this->gameID = $value != null?intval($value):null;
			else 
				$this->$key = $value;
		}

		public function __get($key) {
			if (isset($this->$key)) 
				return $this->$key;
		}

		public function getForumVars() {
			return get_object_vars($this);
		}

		public function getForumID() {
			return $this->forumID;
		}

		public function getTitle($pr = false) {
			if ($pr) 
				return printReady($this->title);
			else 
				return $this->title;
		}

		public function getDescription($pr = false) {
			if ($pr) 
				return printReady($this->description);
			else 
				return $this->description;
		}

		public function getType() {
			return $this->type;
		}

		public function getParentID() {
			return $this->parentID;
		}

		public function getHeritage($string = false) {
			if ($string) {
				$heritage = array();
				foreach ($this->heritage as $forumID) 
					if ($forumID != 0) 
						$heritage[] = sql_forumIDPad($forumID);
				return implode('-', $heritage);
			} else 
				return $this->heritage;
		}

		public function getPermissions($permission = null) {
			if (array_key_exists($permission, $this->permissions)) 
				return $this->permissions[$permission];
			else 
				return $this->permissions;
		}

		public function setChild($childID, $order) {
			$this->children[$order] = $childID;
		}

		public function unsetChild($forumID) {
			unset($this->children[array_search($forumID, $this->children)]);
		}

		public function getChildren() {
			return $this->children;
		}

		public function sortChildren() {
			$children = $this->children;
			ksort($children);
			$this->children = [];
			foreach ($children as $forumID) 
				$this->children[] = $forumID;
		}

		public function getGameID() {
			return $this->gameID;
		}

		public function isGameForum() {
			return $this->gameID?true:false;
		}

		public function getLatestPost($key = null) {
			if ($key == null) 
				return $this->latestPost;
			elseif (array_key_exists($key, $this->latestPost)) 
				return $this->latestPost[$key];
			else 
				return null;
		}

		public function getMarkedRead() {
			return $this->markedRead;
		}

		public function setNewPosts($state) {
			$this->newPosts = (bool) $state;
		}

		public function getNewPosts() {
			return $this->newPosts;
		}

		public function getThreads($page = 1) {
			global $currentUser, $mongo;

			$page = intval($page) > 0?intval($page):1;
			$offset = ($page - 1) * PAGINATE_PER_PAGE;

			$threads = [];
			$threadIDs = [];
			$rThreads = $mongo->threads->find(['forumID' => $this->forumID])->sort(['lastPost.datePosted' => -1])->skip($offset)->limit(PAGINATE_PER_PAGE);
			foreach ($rThreads as $thread) 
				$threadIDs[] = $thread['threadID'];
			$readData = [];
			$rReadData = $mongo->forumsReadData->find([
				'userID' => $currentUser->userID,
				'type' => 'thread',
				'threadID' => ['$in' => $threadIDs]
			], ['threadID' => true, 'lastRead' => true]);
			foreach ($rReadData as $threadRD) 
				$readData[$threadRD['threadID']] = $threadRD['lastRead'];
			foreach ($rThreads as $thread) {
				$thread['lastRead'] = isset($readData[$thread['threadID']])?$readData[$thread['threadID']]:0;
				$threads[] = new Thread($thread);
			}
			$this->threads = $threads;

			return $threads;
		}

		public function deleteForum() {
			global $mysql;

			$mysql->query("DELETE f, c, t, p, po, popt, pv, pge, pgr, pu, rdf, rdt, r, d FROM forums f INNER JOIN forums c ON c.heritage LIKE CONCAT(f.heritage, '%') LEFT JOIN threads t ON c.forumID = t.forumID LEFT JOIN posts p ON t.threadID = p.threadID LEFT JOIN forums_polls po ON t.threadID = po.threadID LEFT JOIN forums_pollOptions popt ON t.threadID = popt.threadID LEFT JOIN forums_pollVotes pv ON popt.pollOptionID = pv.pollOptionID LEFT JOIN forums_permissions_general pge ON c.forumID = pge.forumID LEFT JOIN forums_permissions_groups pgr ON c.forumID = pgr.forumID LEFT JOIN forums_permissions_users pu ON c.forumID = pu.forumID LEFT JOIN forums_readData_forums rdf ON c.forumID = rdf.forumID LEFT JOIN forums_readData_threads rdt ON t.threadID = rdt.threadID LEFT JOIN rolls r ON p.postID = r.postID LEFT JOIN deckDraws d ON p.postID = d.postID WHERE f.forumID = {$this->forumID}");
		}
	}
?>