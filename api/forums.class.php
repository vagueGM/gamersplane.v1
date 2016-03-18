<?
	class forums {
		function __construct() {
			global $pathOptions;

			addPackage('forum');

			if ($pathOptions[0] == 'acp') {
				require(APIROOT.'/forumACP.class.php');
				$subcontroller = new forumACP();
			} elseif ($pathOptions[0] == 'getForum') 
				$this->getForum();
			elseif ($pathOptions[0] == 'getThreads') 
				$this->getThreads();
			elseif ($pathOptions[0] == 'markAsRead') 
				$this->markAsRead();
			elseif ($pathOptions[0] == 'getSubscriptions') 
				$this->getSubscriptions();
			elseif ($pathOptions[0] == 'toggleSub') 
				$this->toggleSub();
			elseif ($pathOptions[0] == 'unsubscribe') 
				$this->unsubscribe();
			else 
				displayJSON(array('failed' => true));
		}

		public function getForum() {
			global $currentUser, $mongo;

			$forumID = (int) $_POST['forumID'];
			$forumManager = new ForumManager($forumID);
			$forums = $forumManager->getForumsVars();
			foreach ($forums as $iForumID => $forum) {
				$forums[$iForumID]['totalThreadCount'] = 0;
				$forums[$iForumID]['totalPostCount'] = 0;
				$forum['latestPost']['datePosted'] = $forum['latestPost']['datePosted']->sec;
				foreach ($forum['heritage'] as $hForumID) {
					$forums[$hForumID]['totalThreadCount'] += $forum['threadCount'];
					$forums[$hForumID]['totalPostCount'] += $forum['postCount'];
					if ($forums[$hForumID]['latestPost']['datePosted'] == null || $forum['latestPost']['datePosted'] > $forums[$hForumID]['latestPost']['datePosted']) 
						$forums[$hForumID]['latestPost'] = $forum['latestPost'];
				}
			}
			$forums[$forumID]['subscribed'] = (bool) $mongo->forumSubs->findOne(['userID' => $currentUser->userID, 'type' => 'f', 'forumID' => $forumID], ['_id' => true]);
			$returns = array('success' => true, 'forums' => $forums);
			if (isset($_POST['getThreads']) && $_POST['getThreads']) 
				$returns['threads'] = $this->getAndProcessThreads($forumID, $forumManager);

			displayJSON($returns);
		}

		public function getThreads() {
			$forumID = (int) $_POST['forumID'];
			$forumManager = new ForumManager($forumID, ForumManager::NO_CHILDREN);
			$page = intval($page) > 0?intval($page):1;
			$offset = ($page - 1) * PAGINATE_PER_PAGE;

			$threads = $this->getAndProcessThreads($forumID, $forumManager);
			displayJSON(['success' => true, 'threads' => $threads]);
		}

		private function getAndProcessThreads($forumID, $forumManager) {
			$forums = $forumManager->getForumsVars();
			$threads = $forumManager->getThreads($_POST['page']);
			$markedRead = $forums[$forumID]['markedRead'];
			foreach ($threads as &$thread) {
				$thread = $thread->getThreadVars();
				$thread['lastPost']['datePosted'] = $thread['lastPost']['datePosted']->sec;
				$maxRead = $markedRead > $thread['lastRead']?$markedRead:$thread['lastRead'];
				$thread['newPosts'] = $thread['lastPost']['datePosted'] > $maxRead?true:false;
			}
			return $threads;
		}

		public function markAsRead() {
			global $currentUser, $mongo;
			$forumID = (int) $_POST['forumID'];

			$rChildren = $mongo->forums->find(['heritage' => $forumID], ['forumID' => true]);
			$children = [];
			foreach ($rChildren as $child) 
				if ($child['forumID'] != $forumID) 
					$children[] = $child['forumID'];

			$mongo->forumsReadData->remove(['userID' => $currentUser->userID, 'forumID' => ['$in' => $children]]);
			$markedRead = new MongoDate();
			$mongo->forumsReadData->update(['userID' => $currentUser->userID, 'type' => 'forum', 'forumID' => $forumID], ['userID' => $currentUser->userID, 'type' => 'forum', 'forumID' => $forumID, 'markedRead' => $markedRead], ['upsert' => true]);

			displayJSON(['success' => true, 'markedRead' => $markedRead->sec]);
		}

		public function getSubscriptions() {
			global $mysql;

			if (isset($_POST['userID'])) {
				$userID = (int) $_POST['userID'];
				$rForums = $mysql->query("SELECT p.forumID, p.title, p.heritage, p.parentID, p.order, IF(s.ID = p.forumID, 1, 0) isSubbed FROM forumSubs s INNER JOIN forums f ON s.ID = f.forumID INNER JOIN forums p ON f.heritage LIKE CONCAT(p.heritage, '%') WHERE p.forumID != 0 AND s.userID = {$userID} AND s.type = 'f' ORDER BY LENGTH(p.heritage), `order`");
				$forums = array();
				foreach ($rForums as $forum) {
					if (!isset($forums[$forum['forumID']])) {
						$forum['forumID'] = (int) $forum['forumID'];
						$forum['title'] = printReady($forum['title']);
						$forum['heritage'] = array_map('intval', explode('-', $forum['heritage']));
						$forum['parentID'] = (int) $forum['parentID'];
						$forum['order'] = (int) $forum['order'];
						$forum['isSubbed'] = (bool) $forum['isSubbed'];
						$forums[(int) $forum['forumID']] = $forum;
					} else 
						if ($forum['isSubbed']) 
							$forums[(int) $forum['forumID']]['isSubbed'] = true;
				}

				$rThreads = $mysql->query("SELECT f.forumID, f.title forumTitle, t.threadID, p.title threadTitle FROM forumSubs s INNER JOIN threads t ON s.ID = t.threadID INNER JOIN forums f ON t.forumID = f.forumID INNER JOIN posts p ON t.firstPostID = p.postID WHERE s.userID = {$userID} AND s.type = 't' ORDER BY LENGTH(f.heritage), `order`");
				$threads = array();
				foreach ($rThreads as $thread) {
					if (!isset($threads[$thread['forumID']])) 
						$threads[(int) $thread['forumID']] = array(
							'forumID' => (int) $thread['forumID'],
							'title' => printReady($thread['forumTitle']),
							'threads' => array()
						);
					$threads[(int) $thread['forumID']]['threads'][] = array(
						'threadID' => (int) $thread['threadID'],
						'forumID' => (int) $thread['forumID'],
						'title' => printReady($thread['threadTitle'])
					);
				}

				displayJSON(array('success' => true, 'forums' => array_values($forums), 'threads' => array_values($threads)));
			}
		}

		public function toggleSub() {
			global $currentUser, $mongo;

			$sub = ['type' => $_POST['type'], 'userID' => $currentUser->userID];
			if (!is_string($sub['type']) || !in_array($sub['type'], ['f', 't'])) 
				displayJSON(['failed' => true, 'error' => 'invalidType']);
			if ($sub['type'] == 'f') 
				$sub['forumID'] = (int) $_POST['typeID'];
			else 
				$sub['threadID'] = (int) $_POST['typeID'];

			$exists = $mongo->forumSubs->findOne($sub, ['_id' => true]);
			if ($exists) {
				$mongo->forumSubs->remove(['_id' => $exists['_id']]);
				displayJSON(['success' => true, 'subbed' => false]);
			} else {
				$mongo->forumSubs->insert($sub);
				displayJSON(['success' => true, 'subbed' => true]);
			}
		}

		public function unsubscribe() {
			global $mysql;

			$userID = (int) $_POST['userID'];
			if ($_POST['type'] == 'f' || $_POST['type'] == 't') 
				$type = $_POST['type'];
			else 
				displayJSON(array('failed' => true, 'errors' => array('invalidType')));
			$typeID = (int) $_POST['id'];

			$mysql->query("DELETE FROM forumSubs WHERE userID = {$userID} AND type = '{$type}' AND ID = {$typeID} LIMIT 1");

			displayJSON(array('success' => true));
		}
	}
?>