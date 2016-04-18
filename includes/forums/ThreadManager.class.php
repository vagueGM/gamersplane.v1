<?
	class ThreadManager {
		protected $threadID;
		protected $thread;
		protected $forumManager;
		protected $page = 1;

		public function __construct($threadID = null, $forumID = null) {
			if (intval($threadID))	{
				global $mongo, $currentUser;

				$this->threadID = intval($threadID);
				$this->thread = $mongo->threads->findOne(['threadID' => $threadID]);
				if (!$this->thread) 
					return false;
				$lastRead = $mongo->forumsReadData->findOne(['userID' => $currentUser->userID, 'threadID' => $this->threadID], ['lastRead' => true]);
				if ($lastRead) 
					$this->thread['lastRead'] = $lastRead['lastRead'];
				$this->thread = new Thread($this->thread);

				$this->forumManager = new ForumManager($this->thread->forumID, ForumManager::NO_CHILDREN|ForumManager::NO_NEWPOSTS);
				$markedRead = $this->forumManager->forums[$this->getThreadProperty('forumID')]->getMarkedRead();
				$this->lastRead = $markedRead > $this->getThreadProperty('lastRead')?$markedRead:$this->getThreadProperty('lastRead');
			} elseif (intval($forumID)) {
				$this->thread = new Thread();
				$this->thread->forumID = $forumID;
				$this->forumManager = new ForumManager($forumID, ForumManager::NO_CHILDREN|ForumManager::NO_NEWPOSTS);
			}
		}

		public function __get($key) {
			if (property_exists($this, $key)) 
				return $this->$key;
		}

		public function __set($key, $value) {
			if (property_exists($this, $key)) 
				$this->$key = $value;
		}

		public function getThreadID() {
			return $this->threadID;
		}

		public function getThreadProperty($property) {
			if (preg_match('/(\w+)\[(\w+)\]/', $property, $matches)) 
				return $this->thread->{$matches[1]}[$matches[2]];
			elseif (preg_match('/(\w+)->(\w+)/', $property, $matches)) 
				return $this->thread->$matches[1]->$matches[2];
			else 
				return $this->thread->$property;
		}

		public function getForumProperty($key) {
			return $this->forumManager->getForumProperty($this->thread->forumID, $key);
		}

		public function getFirstPostID() {
			return $this->thread->getFirstPostID();
		}

		public function getLastPost($key = null) {
			return $this->thread->getLastPost($key);
		}

		public function isGameForum() {
			return $this->forumManager->forums[$this->thread->forumID]->isGameForum();
		}

		public function getPermissions($permission = null) {
			return $this->forumManager->getForumProperty($this->thread->forumID, 'permissions'.($permission != null?"[{$permission}]":''));
		}

		public function getThreadLastRead() {
			if ($this->forumManager->maxRead($this->thread->forumID) > $this->getThreadProperty('lastRead')) 
				return $this->forumManager->maxRead($this->thread->forumID);
			else 
				return $this->getThreadProperty('lastRead');
		}

		public function setPage() {
			global $mongo;

			if (isset($_GET['view']) && $_GET['view'] == 'newPost') {
				$numPrevPosts = $mongo->posts->find(['threadID' => $this->threadID, 'datePosted' => ['$lt' => new MongoDate($this->getThreadLastRead())]])->count() + 1;
				$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
			} elseif (isset($_GET['view']) && $_GET['view'] == 'lastPost') {
				$numPrevPosts = $this->getThreadProperty('postCount');
				$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
			} elseif (isset($_GET['p']) && intval($_GET['p'])) {
				$postID = intval($_GET['p']);
				$datePosted = $mongo->posts->findOne(['postID' => $postID], ['datePosted']);
				$numPrevPosts = $mongo->posts->find(['threadID' => $this->threadID, 'datePosted' => ['$lt' => $datePosted['datePosted']]])->count();
				$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
			} else 
				$page = intval($_POST['page']);
			$this->page = intval($page) > 0?intval($page):1;
		}

		public function getPosts() {
			return $this->thread->getPosts($this->page);
		}

		public function getKeyPost() {
			global $mongo;

			$posts = $this->thread->getPosts($this->page);
			$checkFor = '';
			if (isset($_GET['view']) && $_GET['view'] == 'newPost') 
				$checkFor = 'newPost';
			elseif (isset($_GET['p']) && intval($_GET['p'])) 
				$checkFor = intval($_GET['p']);
			elseif ($this->page != 1) 
				return $mongo->posts->findOne(['postID' => $this->thread->firstPostID], ['message' => true])['message'];
			else 
				return $posts[$this->thread->firstPostID];

			foreach ($posts as $post) {
				if ($checkFor == 'newPost' && ($post->getDatePosted() > $this->getThreadLastRead() || $this->thread->getLastPost('postID') == $post->getPostID())) 
					return $post;
				elseif ($post->getPostID == $checkFor) 
					return $post;
			}
		}

		public function updatePostCount() {
			global $mongo;

			$count = $mongo->posts->find(['threadID' => $this->threadID], ['_id' => true])->count();
			$mongo->threads->update(['threadID' => $this->threadID], ['$set' => ['postCount' => $count]]);
		}

		public function getPoll() {
			return $this->thread->getPoll();
		}

		public function getPollProperty($key) {
			return $this->thread->getPollProperty($key);
		}

		public function deletePoll() {
			return $this->thread->deletePoll();
		}

		public function getVotesCast() {
			return $this->thread->getVotesCast();
		}

		public function getVoteTotal() {
			return $this->thread->getVoteTotal();
		}

		public function getVoteMax() {
			return $this->thread->getVoteMax();
		}

		public function toggleThreadState($state) {
			global $mongo;

			if ($state != 'locked' && $state != 'sticky') 
				return false;
			$stateVal = (bool) $this->thread->getStates($state);
			$mongo->threads->update(['threadID' => $this->threadID], ['$set' => [$state => !$stateVal]]);

			return !$stateVal;
		}

		public function saveThread($post) {
			global $mysql;

			$threadData = [
				'threadID' => $this->threadID?$this->threadID:mongo_getNextSequence('threadID'),
				'forumID' => $this->thread->forumID,
				'sticky' => $this->thread->getStates('sticky'),
				'locked' => $this->thread->getStates('locked'),
				'allowRolls' => $this->thread->getAllowRolls(),
				'allowDraws' => $this->thread->getAllowDraws(),
				'postCount' => 1
			];
			if ($this->threadID == null) {
				$threadData['title'] = $post->getTitle();
				$datePosted = time();
				$threadData['datePosted'] = new MongoDate($datePosted);
				$threadData['authorID'] = $post->getAuthor('userID');
/*				$threadData['lastPost'] = [
					'postID' =>
				];*/
				$post->setThreadID($this->threadID);
				$post->setDatePosted($datePosted);
				$postID = $post->savePost($datePosted);

				$mysql->query("UPDATE threads SET firstPostID = {$postID}, lastPostID = {$postID} WHERE threadID = {$this->threadID}");
				$mysql->query("UPDATE forums SET threadCount = threadCount + 1 WHERE forumID = {$this->thread->forumID}");

				$this->updateLastRead($postID);
			} else {
				$mysql->query("UPDATE threads SET forumID = {$this->thread->forumID}, sticky = ".($this->thread->getStates('sticky')?1:0).", locked = ".($this->thread->getStates('locked')?1:0).", allowRolls = ".($this->thread->getAllowRolls()?1:0).", allowDraws = ".($this->thread->getAllowDraws()?1:0)." WHERE threadID = ".$this->threadID);
				$postID = $post->savePost();

				if (intval($this->thread->getLastPost('postID')) < $postID) 
					$mysql->query("UPDATE threads SET lastPostID = {$postID} WHERE threadID = {$this->threadID}");
			}

			$this->thread->savePoll($this->threadID);

			return $postID;
		}

		public function updateLastPost($postID, $author, $datePosted) {
			global $mongo;

			$datePosted = new MongoDate($datePosted);
			$mongo->threads->update(['threadID' => $this->threadID], ['$set' => ['lastPost' => [
				'postID' => $postID,
				'author' => $author,
				'datePosted' => $datePosted
			]]]);
			$mongo->forums->update(['forumID' => $this->thread->forumID], ['$set' => ['lastPost' => [
				'threadID' => $this->threadID,
				'postID' => $postID,
				'author' => $author,
				'datePosted' => $datePosted
			]]]);
		}

		public function updateLastRead($postID) {
			global $loggedIn, $mongo, $currentUser;
			if ($loggedIn && $postID > $this->getThreadProperty('lastRead')) 
				$mongo->forumsReadData->update([
					'userID' => $currentUser->userID,
					'type' => 'thread',
					'threadID' => $this->threadID
				], [
					'userID' => $currentUser->userID,
					'type' => 'thread',
					'threadID' => $this->threadID,
					'forumID' => $this->thread->forumID,
					'markedRead' => new MongoDate()
				], ['upsert' => true]);
		}

		public function displayPagination() {
			ForumView::displayPagination($this->getThreadProperty('postCount'), $this->page);
		}

		public function deletePost($post) {
			global $mysql;

			$post->delete();
			if ($post->getPostID() == $this->getLastPost('postID')) {
				$newLPID = $mysql->query("SELECT postID FROM posts WHERE threadID = {$this->threadID} ORDER BY datePosted DESC LIMIT 1")->fetchColumn();
				$mysql->query("UPDATE threads SET lastPostID = {$newLPID} WHERE threadID = {$this->threadID}");
			}
			$this->updatePostCount();
		}

		public function deleteThread() {
			global $mysql;

			$mysql->query("DELETE FROM threads, posts, rolls, deckDraws USING threads LEFT JOIN posts ON threads.threadID = posts.threadID LEFT JOIN rolls ON posts.postID = rolls.postID LEFT JOIN deckDraws ON posts.postID = deckDraws.postID WHERE threads.threadID = {$this->threadID}");
			$mysql->query("UPDATE forums SET threadCount = threadCount - 1 WHERE forumID = {$this->thread->forumID}");
		}
	}
?>