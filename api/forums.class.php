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
			elseif ($pathOptions[0] == 'toggleThreadState') 
				$this->toggleThreadState();
			elseif ($pathOptions[0] == 'savePost') 
				$this->savePost();
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
				$thread['datePosted'] = $thread['datePosted']* 1000;
				$thread['lastPost']['datePosted'] = $thread['lastPost']['datePosted']->sec * 1000;
				$maxRead = $markedRead > $thread['lastRead']?$markedRead:$thread['lastRead'];
				$thread['newPosts'] = $thread['lastPost']['datePosted'] / 1000 > $maxRead?true:false;
			}
			return $threads;
		}

		public function getThread() {
			global $currentUser, $mongo;

			$threadID = (int) $_POST['threadID'];
			$threadManager = new ThreadManager($threadID);
			if (!$threadManager || !$threadManager->getPermissions('read')) 
				displayJSON(['failed' => true, 'noPermission' => true]);
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
				if ($threadManager->getLastPost('postID') == $post['postID']) {
					$post['lastPost'] = true;
					if (!$newPost) 
						$post['newPost'] = true;
				}
				$post['datePosted'] *= 1000;
				$posts[] = $post;

				if ($maxPost < $post['datePosted']) 
					$maxPost = $post['datePosted'];
			}
			if ($maxPost > $lastRead) 
				$threadManager->updateLastRead($maxPost / 1000);
				// $mongo->forumsReadData->update(['threadID' => $threadID], [
				// 	'userID' => $currentUser->userID,
				// 	'type' => 'thread',
				// 	'threadID' => $threadID,
				// 	'forumID' => $threadManager->getThreadProperty('forumID'),
				// 	'lastRead' => new MongoDate($maxPost / 1000)
				// ], ['upsert' => true]);
			$rPostAsChars = $mongo->characters->find(['characterID' => ['$in' => $getChars]], ['characterID' => true, 'system' => true, 'name' => true]);
			$postAsChars = [];
			foreach ($rPostAsChars as $character) {
				$charObj = CharacterFactory::getCharacter($character['system']);
				$postAsChars[$character['characterID']] = [
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
				$rCharacters = $mongo->characters->find(array('game.gameID' => $gameID, 'game.approved' => true, 'user.userID' => $currentUser->userID), array('characterID' => true, 'name' => true));
				$characters = array();
				foreach ($rCharacters as $character)
					if (strlen($character['name'])) 
						$characters[$character['characterID']] = $character['name'];
				if (sizeof($characters) == 0) 
					$characters = null;
			} else 
				$game = null;

			foreach ($posts as &$post) {
				if ($post['postAs'] && $postAsChars[$post['postAs']]) 
					$post['postAs'] = $postAsChars[$post['postAs']];
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
				'firstPostID' => (int) $threadManager->getThreadProperty('firstPostID'),
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
				'page' => $threadManager->page,
				'characters' => isset($characters)?$characters:null,
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

		public function toggleThreadState() {
			global $mysql, $mongo;

			$threadID = (int) $_POST['threadID'];
			$state = $_POST['state'];

			if (($state != 'locked' && $state != 'sticky') || $threadID <= 0) 
				displayJSON(['failed' => true]);

			$threadManager = new ThreadManager($threadID);
			if (!$threadManager || !$threadManager->getPermissions('moderate')) 
				displayJSON(['failed' => true, 'noPermission' => true]);
			$stateVal = $threadManager->toggleThreadState($state);

			displayJSON(['success' => true, $state => $stateVal]);
		}

		public function savePost() {
			global $currentUser, $mysql, $mongo;

			if ($_POST['threadID']) {
				$threadID = intval($_POST['threadID']);
				$threadManager = new ThreadManager($threadID);
				if (!$threadManager->getPermissions('write') || ($locked && $threadManager->getPermissions('moderate'))) 
					displayJSON(['failed' => true, 'noPermission' => true]);
			} else 
				$forumManager = new ForumManager((int) $_POST['new']);

			if ($_POST['edit']) {
				$postID = intval($_POST['edit']);
				$post = new Post($postID);
				$threadID = intval($post->threadID);
			} else 
				$post = new Post();
			$post->setTitle($_POST['title']);
			$post->setPostAs($_POST['postAs']);
			$message = $_POST['message'];
			if (isset($threadID)) 
				$gameID = $threadManager->getForumProperty('gameID');
			elseif (isset($_POST['new'])) 
				$gameID = $forumManager->getForumProperty((int) $_POST['new'], 'gameID');

			if (preg_match_all('/\[note="?(\w[\w +;,]+?)"?](.*?)\[\/note\]/ms', $message, $matches, PREG_SET_ORDER)) {
				$allUsers = array();
				foreach ($matches as $match) 
					foreach (preg_split('/[^\w]+/', $match[1]) as $eachUser) 
						$allUsers[] = $eachUser;
				$allUsers = array_unique($allUsers);
				$userCheck = $mysql->prepare('SELECT username FROM users WHERE LOWER(username) = :username');
				foreach ($allUsers as $key => $username) {
					$userCheck->bindValue(':username', strtolower($username));
					$userCheck->execute();
					if (!$userCheck->rowCount()) 
						unset($allUsers[$key]);
					else 
						$allUsers[$key] = $userCheck->fetchColumn();
				}
				foreach ($matches as $match) {
					$matchUsers = preg_split('/[^\w]+/', $match[1]);
					$validUsers = array();
					foreach ($matchUsers as $user) {
						foreach ($allUsers as $realUser) {
							if (strtolower($user) == strtolower($realUser)) 
								$validUsers[] = $realUser;
						}
					}
					$validNote = preg_replace('/\[note.*?\]/', '[note="'.implode(',', $validUsers).'"]', $match[0]);
					$message = str_replace($match[0], $validNote, $message);
				}
			}
			$post->setMessage($message);
			
			$rolls = array();
			$draws = array();

			if (sizeof($_POST['rolls'])) { foreach ($_POST['rolls'] as $num => $roll) {
				$cleanedRoll = array();
				if (strlen($roll['roll'])) {
					$rollObj = RollFactory::getRoll($roll['type']);
					if (!isset($roll['options'])) $roll['options'] = array();
					$rollObj->newRoll($roll['roll'], $roll['options']);
					$rollObj->roll();
					$rollObj->setReason($roll['reason']);
					$rollObj->setVisibility($roll['visibility']);
					$post->addRollObj($rollObj);
				}
			} }

			if (sizeof($_POST['decks'])) {
				$returnFields = array('players' => true);
				if (sizeof($_POST['decks'])) 
					$returnFields['decks'] = true;
				$game = $mongo->games->findOne(array('gameID' => $gameID, 'players.user.userID' => $currentUser->userID), $returnFields);
				if ($game) {
					$rDecks = $game['decks'];
					$decks = array();
					$draws = array_filter($_POST['decks'], function($value) { return intval($value['draw']) > 0?true:false; });
					foreach ($rDecks as $deck) 
						if (array_key_exists((int) $deck['deckID'], $draws) && in_array($currentUser->userID, $deck['permissions'])) 
							$decks[$deck['deckID']] = $deck;
					$isGM = null;
					foreach ($game['players'] as $player) {
						if ($player['user']['userID'] == $currentUser->userID) {
							$isGM = $player['isGM'];
							break;
						}
					}
					foreach ($draws as $deckID => $draw) {
						if ($draw['draw'] > 0) {
							$deck = $decks[$deckID]['deck'];
							if (strlen($draw['reason']) == 0) {
								$_SESSION['errors']['noDrawReason'] = 1;
								break;
							} elseif ($decks[$deckID]['position'] + $draw['draw'] - 1 > sizeof($deck)) {
								$_SESSION['errors']['overdrawn'] = 1;
								break;
							}

							$draw['cardsDrawn'] = array();
							for ($count = $decks[$deckID]['position']; $count <= $decks[$deckID]['position'] + $draw['draw'] - 1; $count++) 
								$draw['cardsDrawn'][] = $deck[$count - 1];
							$draw['cardsDrawn'] = implode('~', $draw['cardsDrawn']);
							$draw['reason'] = sanitizeString($draw['reason']);
							$draw['type'] = $decks[$deckID]['type'];
							$post->addDraw($deckID, $draw);
						}
					}
				}
			}

			$errors = [];
			if ($_POST['new']) {
				$forumID = intval($_POST['new']);
				$threadManager = new ThreadManager(null, $forumID);
				$threadManager->thread->title = $post->getTitle();

				if (!$threadManager->getPermissions('createThread')) 
					displayJSON(['failed' => true, 'noPermission' => true]);
				$threadManager->thread->setState('sticky', isset($_POST['sticky']) && $threadManager->getPermissions('moderate')?true:false);
				$threadManager->thread->setState('locked', isset($_POST['locked']) && $threadManager->getPermissions('moderate')?true:false);
				$threadManager->thread->setAllowRolls(isset($_POST['allowRolls']) && $threadManager->getPermissions('addRolls')?true:false);
				$threadManager->thread->setAllowDraws(isset($_POST['allowDraws']) && $threadManager->getPermissions('addRolls')?true:false);
				
				if (strlen($post->getTitle()) == 0) 
					$errors[] = 'noTitle';
				if (strlen($post->getMessage()) == 0) 
					$errors[] = 'noMessage';
				
				$threadManager->thread->poll->setQuestion($_POST['poll']);
				$threadManager->thread->poll->parseOptions($_POST['pollOptions']);
				if (strlen($threadManager->thread->poll->getQuestion()) && sizeof($threadManager->thread->poll->getOptions())) {
					if (strlen($threadManager->thread->poll->getQuestion()) == 0 && sizeof($threadManager->thread->poll->getOptions()) != 0) 
						$errors[] = 'noQuestion';
					if (strlen($threadManager->thread->poll->getQuestion()) && sizeof($threadManager->thread->poll->getOptions()) <= 1) 
						$errors[] = 'noOptions';
					$threadManager->thread->poll->setOptionsPerUser($_POST['optionsPerUser']);
					if ($threadManager->thread->poll->getOptionsPerUser() == 0) 
						$errors[] = 'noOptionsPerUser';
					$threadManager->thread->poll->setAllowRevoting($_POST['allowRevoting']);
				}

				if (sizeof($errors)) 
					displayJSON(['failed' => true, 'errors' => $errors]);
				else 
					$postID = $threadManager->saveThread($post);
			} elseif ($_POST['threadID']) {
				$threadID = intval($_POST['threadID']);

				$post->setThreadID($threadID);
				if (strlen($post->getTitle()) == 0) 
					$post->setTitle('Re: '.$threadManager->getThreadProperty('title'));
				if (strlen($post->getMessage()) == 0) 
					$errors[] = 'noMessage';

				if (sizeof($errors)) 
					displayJSON(['failed' => true, 'errors' => $errors]);
				else {
					$postID = $post->savePost();
					$threadManager->updateLastPost($post->postID, ['userID' => $currentUser->userID, 'username' => $currentUser->username], $post->getDatePosted());
					$threadManager->updatePostCount();
					$threadManager->updateLastRead($post->getDatePosted());
				}
			} elseif ($_POST['edit']) {
				$threadManager = new ThreadManager($post->getThreadID());
				$firstPost = $threadManager->getThreadProperty('firstPostID') == $post->getPostID()?true:false;

				if (!(($post->getAuthor('userID') == $currentUser->userID && $threadManager->getPermissions('editPost') && !$threadManager->thread->getStates('locked')) || $threadManager->getPermissions('moderate'))) 
					displayJSON(['failed' => true, 'cantEdit' => true]);

				if ($firstPost && strlen($post->getTitle()) == 0) 
					$errors[] = 'noTitle';
				if (strlen($post->getMessage()) == 0) 
					$errors[] = 'noMessage';

				if ($firstPost) {
					$threadManager->thread->setState('sticky', isset($_POST['sticky']) && $threadManager->getPermissions('moderate')?true:false);
					$threadManager->thread->setState('locked', isset($_POST['locked']) && $threadManager->getPermissions('moderate')?true:false);
					$threadManager->thread->setAllowRolls(isset($_POST['allowRolls']) && $threadManager->getPermissions('addRolls')?true:false);
					$threadManager->thread->setAllowDraws(isset($_POST['allowDraws']) && $threadManager->getPermissions('addRolls')?true:false);

					if (!isset($_POST['deletePoll'])) {
						$threadManager->thread->poll->setQuestion($_POST['poll']);
						$threadManager->thread->poll->parseOptions($_POST['pollOptions']);
						if (strlen($threadManager->thread->poll->getQuestion()) && sizeof($threadManager->thread->poll->getOptions())) {
							if (strlen($threadManager->thread->poll->getQuestion()) == 0 && sizeof($threadManager->thread->poll->getOptions()) != 0) 
								$errors[] = 'noQuestion';
							if (strlen($threadManager->thread->poll->getQuestion()) && sizeof($threadManager->thread->poll->getOptions()) <= 1) 
								$errors[] = 'noOptions';
							$threadManager->thread->poll->setOptionsPerUser($_POST['optionsPerUser']);
							if ($threadManager->thread->poll->getOptionsPerUser() == 0) 
								$errors[] = 'noOptionsPerUser';
							$threadManager->thread->poll->setAllowRevoting($_POST['allowRevoting']);
						}
					}
				}

				if (sizeof($errors)) 
					displayJSON(['failed' => true, 'errors' => $errors]);
				else {
					if (((time() + 300) > strtotime($post->getDatePosted()) || (time() + 60) > strtotime($post->getLastEdit())) && !$threadManager->getPermissions('moderate') && $post->getModified()) {
						$edited = true;
						$post->updateEdited();
					}

					if ($firstPost) {
						$threadManager->thread->setState('sticky', isset($_POST['sticky']) && $threadManager->getPermissions('moderate')?true:false);
						$threadManager->thread->setState('locked', isset($_POST['locked']) && $threadManager->getPermissions('moderate')?true:false);
						$threadManager->thread->setAllowRolls(isset($_POST['allowRolls']) && $threadManager->getPermissions('addRolls')?true:false);
						$threadManager->thread->setAllowDraws(isset($_POST['allowDraws']) && $threadManager->getPermissions('addDraws')?true:false);
						
						if (isset($_POST['deletePoll'])) $threadManager->deletePoll();

						$threadManager->saveThread($post);
					} else {
						$allowRolls = $postInfo['allowRolls'];
						$allowDraws = $postInfo['allowDraws'];

						$post->savePost();
					}
					
					foreach ($_POST['nVisibility'] as $rollID => $nVisibility) {
						if (intval($nVisibility) != intval($_POST['oVisibility'][$rollID])) $mysql->query('UPDATE rolls SET visibility = '.intval($nVisibility)." WHERE rollID = $rollID");
					}
				}
			}

			if (!isset($_POST['edit'])) {
				$subbedUsers = $mongo->forumSubs->find(['$or' => [
					['forumID' => $threadManager->getThreadProperty('forumID')],
					['threadID' => $threadManager->getThreadID()]
				]], ['userID' => true]);
				$userIDs = [];
				foreach ($subbedUsers as $user) 
					$userIDs[] = $user['userID'];
				$userIDs = array_unique($userIDs);
				$subbedUsers = $mysql->query("SELECT email FROM users WHERE userID IN (".implode(',', $userIDs).")");
				$subs = [];
				if ($subbedUsers->rowCount()) 
					foreach ($subbedUsers as $user) 
						$subs[] = $user['email'];
				if (sizeof($subs)) {
					$subs = array_unique($subs);
					ob_start();
					include('forums/process/threadSubEmail.php');
					$email = ob_get_contents();
					ob_end_clean();
					foreach ($subs as $sub) 
						@mail($sub, "New Posts", $email, "Content-type: text/html\r\nFrom: Gamers Plane <contact@gamersplane.com>");
				}
			}

			displayJSON(['success' => true, 'postID' => $postID]);
		}
	}
?>