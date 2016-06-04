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

			switch ($_POST['view']) {
				case 'newPost':
					$numPrevPosts = $mongo->posts->find(['threadID' => $this->threadID, 'datePosted' => ['$lt' => new MongoDate($this->getThreadLastRead())]])->count() + 1;
					$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
					break;
				case 'lastPost':
					$numPrevPosts = $this->getThreadProperty('postCount');
					$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
					break;
				case 'post':
					$postID = intval($_POST['viewVal']);
					if ($postID <= 0) {
						$page = 1;
						break;
					}
					$datePosted = $mongo->posts->findOne(['postID' => $postID], ['datePosted']);
					$numPrevPosts = $mongo->posts->find(['threadID' => $this->threadID, 'datePosted' => ['$lte' => $datePosted['datePosted']]], ['_id' => true])->count();
					$page = $numPrevPosts?ceil($numPrevPosts / PAGINATE_PER_PAGE):1;
					break;
				default:
					$page = intval($_POST['viewVal']);
			}
			$this->page = intval($page) > 0?intval($page):1;
		}

		public function getPosts() {
			return $this->thread->getPosts($this->page);
		}

		public function getPost($postID, $postData = null) {
			return $this->thread->getPost($postID, $postData);
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

		public function updatePostCount($increment = 1) {
			global $mongo;

			$increment = (int) $increment;
			$mongo->threads->update(['threadID' => $this->threadID], ['$inc' => ['postCount' => $increment]]);
			$this->forumManager->updatePostCount($increment);

			return true;
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

		public function updateLastPost($postID, $author, $datePosted, $delete) {
			global $mongo;

			$datePosted = new MongoDate($datePosted);
			$mongo->threads->update(['threadID' => $this->threadID], ['$set' => ['lastPost' => [
				'postID' => $postID,
				'author' => $author,
				'datePosted' => $datePosted
			]]]);
			$this->forumManager->updateLatestPost(!$delete?[
				'threadID' => $this->threadID,
				'postID' => $postID,
				'author' => $author,
				'datePosted' => $datePosted
			]:null);
		}

		public function updateLastRead($datePosted) {
			global $loggedIn, $mongo, $currentUser;
			if ($loggedIn && $datePosted > $this->getThreadProperty('lastRead'))
				$mongo->forumsReadData->update([
					'userID' => $currentUser->userID,
					'type' => 'thread',
					'threadID' => $this->threadID
				], [
					'userID' => $currentUser->userID,
					'type' => 'thread',
					'threadID' => $this->threadID,
					'forumID' => $this->thread->forumID,
					'lastRead' => new MongoDate()
				], ['upsert' => true]);
		}

		public function displayPagination() {
			ForumView::displayPagination($this->getThreadProperty('postCount'), $this->page);
		}

		public function deletePost($post) {
			global $mysql, $mongo;

			if ($post->getPostID() == $this->getFirstPostID())
				$this->deleteThread();
			else {
				$post->delete();
				if ($post->getPostID() == $this->getLastPost('postID')) {
					$lastPost = $mongo->posts->find(['threadID' => $this->threadID], ['postID' => true, 'authorID' => true, 'datePosted' => true])->sort(['datePosted' => -1])->limit(1);
					$lastPost = $lastPost->getNext();
					$authorUsername = $mysql->query("SELECT username FROM users WHERE userID = {$lastPost['authorID']} LIMIT 1")->fetchColumn();
					$this->updateLastPost($lastPost['postID'], ['userID' => $lastPost['authorID'], 'username' => $authorUsername], $lastPost['datePosted']->sec, true);
				}
			}
			$this->updatePostCount();

			return true;
		}

		public function deleteThread() {
			global $mongo;

			$this->thread->delete();
			$this->forumManager->updateLatestPost();

			return true;
		}
	}
?>
