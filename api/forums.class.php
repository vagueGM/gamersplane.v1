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
			elseif ($pathOptions[0] == 'getThread') 
				$this->getThread();
			elseif ($pathOptions[0] == 'markAsRead') 
				$this->markAsRead();
			elseif ($pathOptions[0] == 'getSubscriptions') 
				$this->getSubscriptions();
			elseif ($pathOptions[0] == 'toggleSub') 
				$this->toggleSub();
			elseif ($pathOptions[0] == 'unsubscribe') 
				$this->unsubscribe();
			elseif ($pathOptions[0] == 'toggleCardVis') 
				$this->toggleCardVis();
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
				$forum['latestPost']['datePosted'] = $forum['latestPost']['datePosted']->sec * 1000;
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

		public function getThread() {
			global $currentUser, $mongo;

			$threadID = (int) $_POST['threadID'];
			$threadManager = new ThreadManager($threadID);
			$threadManager->setPage();
			$posts = [];
			$getChars = [];
			$maxPost = 0;
			$lastPost = $threadManager->thread->getLastPost('postID');
			$lastRead = $threadManager->getThreadProperty('lastRead');
			$newPost = false;
			foreach ($threadManager->getPosts() as $post) {
				$post = $post->getPostVars();
				$post['title'] = printReady(BBCode2Html($post['title']));
				$post['author']['lastActivity'] *= 1000;
				if ($post['postAs'] && !in_array($post['postAs'], $getChars)) 
					$getChars[] = $post['postAs'];
				if ($post['lastEdit'] != null) 
					$post['lastEdit'] *= 1000;
				$post['newPost'] = false;
				if (!$newPost && $post['datePosted'] > $lastRead) {
					$post['newPost'] = true;
					$newPost = true;
				}
				$post['lastPost'] = false;
				if ($post['datePosted'] == $post['postID']) 
					$post['lastPost'] = true;
				$post['datePosted'] *= 1000;
				$posts[] = $post;

				if ($maxPost < $post['datePosted']) 
					$maxPost = $post['datePosted'];
			}
			if ($maxPost > $lastRead) 
				$mongo->forumsReadData->update(['threadID' => $threadID], [
					'userID' => $currentUser->userID,
					'type' => 'thread',
					'threadID' => $threadID,
					'forumID' => $threadManager->getThreadProperty('forumID'),
					'lastRead' => new MongoDate($lastRead)
				], ['upsert' => true]);
			$rCharacters = $mongo->characters->find(['characterID' => ['$in' => $getChars]], ['characterID' => true, 'system' => true, 'name' => true]);
			$characters = [];
			foreach ($rCharacters as $character) {
				$charObj = CharacterFactory::getCharacter($character['system']);
				$characters[$character['characterID']] = [
					'characterID' => $character['characterID'],
					'name' => $character['name'],
					'system' => $character['system'],
					'avatar' => file_exists(FILEROOT."/characters/avatars/{$character['characterID']}.jpg")?"/characters/avatars/{$character['characterID']}.jpg":false,
					'permissions' => $charObj->checkPermissions()
				];
			}

			$markedRead = $threadManager->forumManager->forums[$threadManager->getThreadProperty('forumID')]->getMarkedRead();
			$subscribed = false;
			$forumSub = (bool) $mongo->forumSubs->findOne(['userID' => $currentUser->userID, 'type' => 'f', 'forumID' => $threadManager->getThreadProperty('forumID')], ['_id' => true]);
			if (!$forumSub) {
				$threadSub = (bool) $mongo->forumSubs->findOne(['userID' => $currentUser->userID, 'type' => 't', 'threadID' => $threadID], ['_id' => true]);
				if ($threadSub) 
					$subscribed = 't';
			} else 
				$subscribed = 'f';
			$poll = null;
			try {
				$poll = new ForumPoll($threadID);
				if ($poll->threadID == null) 
					$poll = null;
				else {
					$poll = $poll->getPollVars();
					$poll['question'] = printReady($poll['question']);
				}
			} catch (Exception $e) {}

			if ($threadManager->isGameForum()) {
				$gameID = (int) $threadManager->getForumProperty('gameID');
				$game = $mongo->games->findOne(array('gameID' => $gameID), array('system' => true, 'players' => true));
				$isGM = false;
				foreach ($game['players'] as $player) {
					if ($player['user']['userID'] == $currentUser->userID) 
						if ($player['isGM']) 
							$isGM = true;
					if ($player['isGM']) 
						$gms[] = $player['user']['userID'];
				}
				$game = [
					'gameID' => $gameID,
					'isGM' => $isGM,
					'gms' => $gms
				];
			} else 
				$game = null;

			foreach ($posts as &$post) {
				if ($post['postAs'] && $characters[$post['postAs']]) 
					$post['postAs'] = $characters[$post['postAs']];
				else 
					$post['postAs'] = false;

				if ($game) 
					$post['author']['isGM'] = in_array($post['author']['userID'], $game['gms']);

				$post['userIsAuthor'] = $currentUser->userID == $post['author']['userID'];

				$post['message'] = printReady(BBCode2Html($post['message'], $post));

				if ($post['rolls']) 
					$post['showRollHidden'] = ($game && $game['isGM']) || $post['userIsAuthor'];
			}

			$thread = [
				'threadID' => $threadID,
				'title' => $threadManager->getThreadProperty('title'),
				'forumID' => (int) $threadManager->getThreadProperty('forumID'),
				'forumHeritage' => $threadManager->forumManager->getHeritage(),
				'sticky' => (bool) $threadManager->thread->getStates('sticky'),
				'locked' => (bool) $threadManager->thread->getStates('locked'),
				'allowRolls' => (bool) $threadManager->getThreadProperty('allowRolls'),
				'allowDraws' => (bool) $threadManager->getThreadProperty('allowDraws'),
				'postCount' => $threadManager->getThreadProperty('postCount'),
				'lastRead' => $threadManager->getThreadProperty('lastRead'),
				'subscribed' => $subscribed,
				'permissions' => $threadManager->getPermissions(),
				'poll' => $poll,
				'posts' => $posts,
				'characters' => $characters,
				'game' => $game
			];

			displayJSON(['success' => true, 'thread' => $thread]);
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

		public function toggleCardVis() {
			global $mongo;

			$postID = (int) $_POST['postID'];
			$deckID = (int) $_POST['deckID'];
			$card = (int) $_POST['card'];

			$draws = $mongo->posts->findOne(['postID' => $postID], ['draws' => true]);
			$draws = $draws['draws'];
			foreach ($draws as &$draw) {
				if ($draw['deckID'] == $deckID) {
					$visible = false;
					foreach ($draw['cards'] as &$iCard) {
						if ($iCard['card'] == $card) {
							$visible = !$iCard['visible'];
							$iCard['visible'] = $visible;
							$mongo->posts->update(['postID' => $postID], ['$set' => ['draws' => $draws]]);
							return displayJSON(['success' => true, 'draw' => $draw]);
						}
					}
				}
			}
		}
	}
?>