<?
	class ForumManager {
		public $currentForum;
		protected $forumsData = array();
		public $forums = array();
		protected $lastRead = array();

		const NO_CHILDREN = 1;
		const NO_NEWPOSTS = 2;
		const ADMIN_FORUMS = 4;

		public function __construct($forumID, $options = 0) {
			global $loggedIn, $currentUser, $mongo;
			$forumID = (int) $forumID;

			if ($loggedIn) {
				$showPubGames = $currentUser->showPubGames;
				if ($showPubGames === null) {
					$showPubGames = 1;
					$currentUser->updateUsermeta('showPubGames', '1', true);
				}
			} else
				$showPubGames = 1;

			$this->currentForum = intval($forumID);
			if ($this->currentForum < 0) { header('Location: /forums/'); exit; }

			$currentForum = $mongo->forums->findOne(['forumID' => $forumID], ['heritage' => true]);
			$forumConds = [];
			$forumConds[] = ['forumID' => ['$in' => $currentForum['heritage']]];
			if ($forumID == 0)
				$forumConds[0]['forumID'] = 0;
			if (bindec($options&$this::NO_CHILDREN) == 0)
				$forumConds[] = ['heritage' => $forumID];
			$forumConds = sizeof($forumConds) > 1?['$or' => $forumConds]:$forumConds[0];
			if ($forumID == 0 || $forumID == 2)
				$forumConds['gameID'] = null;
			$forumsR = $mongo->forums->find($forumConds)->sort(['depth' => 1]);
			foreach ($forumsR as $forum)
				$this->forumsData[$forum['forumID']] = $forum;
			if (($this->currentForum == 0 || $this->currentForum == 2) && bindec($options&$this::NO_CHILDREN) == 0) {
				$forumConds = ['$or' => []];
				if ($showPubGames)
					$forumConds['$or'][] = ['public' => true];
				if ($loggedIn)
					$forumConds['$or'][] = ['players' => [
						'$elemMatch' => [
							'user.userID' => $currentUser->userID,
							'approved' => true
						]
					], 'retired' => null];
				if (sizeof($forumConds['$or']) == 1)
					$forumConds = $forumConds['$or'][0];
				$gameForums = $mongo->games->find($forumConds, ['forumID' => true]);
				$gameForumIDs = array();
				foreach ($gameForums as $game)
					$gameForumIDs[] = $game['forumID'];
				$gameForums = $mongo->forums->find(['forumID' => ['$in' => $gameForumIDs]]);
				foreach ($gameForums as $forum)
					$this->forumsData[$forum['forumID']] = $forum;
			}
			$permissions = ForumPermissions::getPermissions($currentUser->userID, array_keys($this->forumsData), null, $this->forumsData);
			foreach ($permissions as $pForumID => $permission)
				$this->forumsData[$pForumID]['permissions'] = $permission;
			foreach ($this->forumsData as $forumID => $forumData)
				$this->spawnForum($forumID);
			if ($options&$this::ADMIN_FORUMS)
				$this->pruneByPermissions(0, 'admin');
			else
				$this->pruneByPermissions();
			foreach ($this->forums as $forumID => $forum) {
				$this->forums[$forumID]->sortChildren();
				if ($this->forums[$forumID]->latestPost['threadID'] != null)
					foreach ($this->forums[$forumID]->getHeritage() as $hForumID)
						if ($this->forums[$hForumID]->latestPost['datePosted'] == null || $this->forums[$forumID]->latestPost['datePosted'] > $this->forums[$hForumID]->latestPost['datePosted'])
							$this->forums[$hForumID]->latestPost = $this->forums[$forumID]->latestPost;
			}

			$rForumReadData = $mongo->forumsReadData->find([
				'userID' => $currentUser->userID,
				'forumID' => ['$in' => array_keys($this->forums)],
				'type' => 'forum'
			], ['forumID' => true, 'markedRead' => true]);
			foreach ($rForumReadData as $readData)
				$this->forums[$readData['forumID']]->markedRead = $readData['markedRead']->sec;
			$getThreadRD = [];
			foreach ($this->forums as $forum) {
				foreach ($forum->getHeritage() as $hForumID)
					if ($this->forums[$hForumID]->getMarkedRead() > $forum->getMarkedRead())
						$this->forums[$forum->getForumID()]->markedRead = $this->forums[$hForumID]->getMarkedRead();
				if ($forum->latestPost['datePosted'] > $this->forums[$forum->getForumID()]->getMarkedRead()) {
					$getThreadRD[] = $forum->getForumID();
				}
			}
			if (!($options&$this::NO_NEWPOSTS) && sizeof($getThreadRD)) {
				$rThreadReadData = $mongo->forumsReadData->find([
					'userID' => $currentUser->userID,
					'forumID' => ['$in' => $getThreadRD],
					'type' => 'thread',
					'lastRead' => ['$gt' => new MongoDate($this->forums[$this->currentForum]->getMarkedRead())]
				], ['threadID' => true, 'forumID' => true, 'lastRead' => true]);
				$threadReadData = [];
				$getThreadData = [];
				foreach ($rThreadReadData as $thread) {
					$threadReadData[$thread['threadID']] = $thread;
					$getThreadData[] = $thread['forumID'];
				}
				foreach (array_diff($getThreadRD, $getThreadData) as $forumID)
					$this->forums[$forumID]->setNewPosts(true);
				$rThreadData = $mongo->threads->find([
					'forumID' => ['$in' => $getThreadData],
					'lastPost.datePosted' => ['$gt' => new MongoDate($this->forums[$this->currentForum]->getMarkedRead())]
				], ['threadID' => true, 'forumID' => true, 'lastPost' => true]);
				foreach ($rThreadData as $thread) {
					if ($this->forums[$thread['forumID']]->getNewPosts())
						continue;
					if (!isset($threadReadData[$thread['threadID']]) || $threadReadData[$thread['threadID']]['lastRead']->sec < $thread['lastPost']['datePosted']->sec) {
						$this->forums[$thread['forumID']]->setNewPosts(true);
						foreach ($this->forums[$thread['forumID']]->getHeritage() as $hForumID)
							$this->forums[$hForumID]->setNewPosts(true);
					}
				}
			}
		}

		protected function spawnForum($forumID) {
			if (isset($this->forums[$forumID]))
				return null;

			$this->forums[$forumID] = new Forum($forumID, $this->forumsData[$forumID]);
			if ($forumID == 0)
				return null;
			$parentID = $this->forums[$forumID]->parentID;
			if (!isset($this->forums[$parentID]))
				$this->spawnForum($parentID);
			$this->forums[$parentID]->setChild($forumID, $this->forums[$forumID]->order);
		}

		protected function pruneByPermissions($forumID = 0, $permission = 'read') {
			foreach ($this->forums[$forumID]->children as $childID)
				$this->pruneByPermissions($childID, $permission);
			if (sizeof($this->forums[$forumID]->children) == 0 && $this->forums[$forumID]->permissions[$permission] == false) {
				$parentID = $this->forums[$forumID]->parentID;
				unset($this->forums[$forumID]);
				if (isset($this->forums[$parentID]))
					$this->forums[$parentID]->unsetChild($forumID);
			}
		}

		public function getAccessableForums($validForums = null) {
			if ($validForums == null)
				$validForums = array();

			$forums = array();
			foreach ($this->forums as $forum) {
				if ($forum->getPermissions('read') && ((sizeof($validForums) && in_array($forum->getForumID(), $validForums)) || sizeof($validForums) == 0))
					$forums[] = $forum->getForumID();
			}
			return $forums;
		}

		public function getForumsVars($forumID = null) {
			if ($forumID == null) {
				$forums = array();
				foreach ($this->forums as $forumID => $forum)
					$forums[$forumID] = $forum->getForumVars();
				return $forums;
			} else
				return get_object_vars($this->forums[$forumID]);
		}

		public function getAllChildren($forumID = 0, $read = false) {
			$forums = array($forumID);
			if (!isset($this->forums[$forumID]))
				return array();
			$forum = $this->forums[$forumID];
			foreach ($forum->getChildren() as $childID) {
				if (!in_array($childID, $forums) && (!$read || ($read && $this->forums[$childID]->getPermissions('read'))))
					$forums[] = $childID;
				$children = $this->getAllChildren($childID);
				$forums = array_merge($forums, $children);
			}
			return $forums;
		}

		public function getForumProperty($forumID, $property) {
			if (preg_match('/(\w+)\[(\w+)\]/', $property, $matches))
				return $this->forums[$forumID]->{$matches[1]}[$matches[2]];
			elseif (preg_match('/(\w+)->(\w+)/', $property, $matches))
				return $this->forums[$forumID]->$matches[1]->$matches[2];
			else
				return $this->forums[$forumID]->$property;
		}

		public function displayCheck($forumID = null) {
			if ($forumID == null) $forumID = $this->currentForum;

			if (sizeof($this->forums[$forumID]->children) || $this->forums[$forumID]->permissions['read']) return true;
			else return false;
		}

		public function getTotalThreadCount($forumID) {
			$forum = $this->forums[$forumID];

			$total = 0;
			if (sizeof($forum->children))
				foreach ($forum->children as $cForumID)
					$total += $this->getTotalThreadCount($cForumID);
			if ($forum->permissions['read']) $total += $forum->threadCount;
			return $total;
		}

		public function getTotalPostCount($forumID) {
			$forum = $this->forums[$forumID];

			$total = 0;
			if (sizeof($forum->children))
				foreach ($forum->children as $cForumID)
					$total += $this->getTotalPostCount($cForumID);
			if ($forum->permissions['read'])
				$total += $forum->postCount;
			return $total;
		}

		public function updatePostCount($increment) {
			global $mongo;

			$increment = (int) $increment;
			$mongo->forums->update(['forumID' => $this->currentForum], ['$inc' => ['postCount' => $increment]]);

			return true;
		}

		public function maxRead($forumID) {
			$maxRead = 0;
			foreach ($this->forums[$forumID]->getHeritage() as $heritageID) {
				if ($this->forums[$heritageID]->getMarkedRead() > $maxRead)
					$maxRead = $this->forums[$heritageID]->getMarkedRead();
			}

			return $maxRead;
		}

		public function newPosts($forumID) {
			global $loggedIn;
			if (!$loggedIn) return false;

			$forum = $this->forums[$forumID];

			if (sizeof($forum->children)) { foreach ($forum->children as $childID) {
				if ($this->newPosts($childID)) return true;
			} }
			if ($forum->newPosts) return true;
			else return false;
		}

		public function getLastPost($forumID) {
			$forum = $this->forums[$forumID];

			$lastPost = new stdClass();
			$lastPost->postID = 0;
			if (sizeof($forum->children)) {
				foreach ($forum->children as $cForumID) {
					$cLastPost = $this->getLastPost($cForumID);
					if ($cLastPost && $cLastPost->postID > $lastPost->postID)
						$lastPost = $cLastPost;
				}
			}
			if ($forum->permissions['read'] && $forum->lastPost->postID > $lastPost->postID) return $forum->lastPost;
			elseif ($lastPost->postID != 0) return $lastPost;
			else return null;
		}

		public function updateLatestPost($updateObj = null) {
			global $mongo;

			if ($updateObj)
				$mongo->forums->update(['forumID' => $this->currentForum], ['$set' => ['latestPost' => $updateObj]]);
			else {
				$latestPost = $mongo->threads->find(['forumID' => $this->currentForum], ['threadID', 'lastPost'])->sort(['lastPost.datePosted' => -1])->limit(1);
				$latestPost = $latestPost->getNext();
				$mongo->forums->update(['forumID' => $this->currentForum], ['$set' => array_merge(['threadID' => $latestPost['threadID']], $latestPost['lastPost'])]);
			}
		}

		public function getHeritage() {
			$heritage = [];
			if (isset($this->forums[$this->currentForum])) {
				foreach ($this->forums[$this->currentForum]->heritage as $hForumID) {
					$heritage[] = [
						'forumID' => $hForumID,
						'title' => $this->forums[$hForumID]->title
					];
				}
			}
			return $heritage;
		}

		public function displayBreadcrumbs() {
?>
				<div id="breadcrumbs">
<?
			if ($this->currentForum != 0) {
				$heritage = $this->forums[$this->currentForum]->heritage;
				$fCounter = 0;
				foreach ($heritage as $hForumID) {
					echo "\t\t\t\t\t<a href=\"/forums/{$hForumID}\">".printReady($this->forums[$hForumID]->title)."</a>".($fCounter != sizeof($heritage) - 1?' > ':'')."\n";
					$fCounter++;
				}
			}
?>
				</div>
<?
		}

		public function getThreads($page = 1) {
			return isset($this->forums[$this->currentForum])?$this->forums[$this->currentForum]->getThreads($page):[];
		}

		public function displayThreads() {
			$forum = $this->forums[$this->currentForum];
			if (!$forum->permissions['read']) return false;

?>
		<div class="tableDiv threadTable">
<?			if ($forum->permissions['createThread']) { ?>
			<div class="hbdMargined"><a href="/forums/newThread/<?=$forum->forumID?>/" class="fancyButton">New Thread</a></div>
<? 			} ?>
			<div class="tr headerTR headerbar hbDark">
				<div class="td icon">&nbsp;</div>
				<div class="td threadInfo">Thread</div>
				<div class="td numPosts"># of Posts</div>
				<div class="td lastPost">Last Post</div>
			</div>
			<div class="sudoTable threadList hbdMargined">
<?
			if (sizeof($forum->threads)) { foreach ($forum->threads as $thread) {
				$maxRead = $this->maxRead($forum->getForumID());
?>
				<div class="tr">
					<div class="td icon"><div class="forumIcon<?=$thread->getStates('sticky')?' sticky':''?><?=$thread->getStates('locked')?' locked':''?><?=$thread->newPosts($maxRead)?' newPosts':''?>" title="<?=$thread->newPosts($maxRead)?'New':'No new'?> posts in thread" alt="<?=$thread->newPosts($maxRead)?'New':'No new'?> posts in thread"></div></div>
					<div class="td threadInfo">
<?				if ($thread->newPosts($maxRead)) { ?>
						<a href="/forums/thread/<?=$thread->threadID?>/?view=newPost#newPost"><img src="/images/forums/newPost.png" title="View new posts" alt="View new posts"></a>
<?				} ?>
						<div class="paginateDiv">
<?
				if ($thread->postCount > PAGINATE_PER_PAGE) {
					$url = '/forums/thread/'.$thread->threadID.'/';
					$numPages = ceil($thread->postCount / PAGINATE_PER_PAGE);
					if ($numPages <= 4) { for ($count = 1; $count <= $numPages; $count++) {
?>
							<a href="<?=$url?>?page=<?=$count?>"><?=$count?></a>
<?
					} } else {
?>
							<a href="<?=$url?>?page=1">1</a>
							<div>...</div>
<?						for ($count = ($numPages - 1); $count <= $numPages; $count++) { ?>
							<a href="<?=$url?>?page=<?=$count?>"><?=$count?></a>
<?
						}
					}
				}
?>
							<a href="/forums/thread/<?=$thread->threadID?>/?view=lastPost#lastPost"><img src="/images/downArrow.png" title="Last post" alt="Last post"></a>
						</div>
						<a href="/forums/thread/<?=$thread->threadID?>/"><?=$thread->title?></a><br>
						<span class="threadAuthor">by <a href="/user/<?=$thread->authorID?>/" class="username"><?=$thread->authorUsername?></a> on <span class="convertTZ"><?=date('M j, Y g:i a', strtotime($thread->datePosted))?></span></span>
					</div>
					<div class="td numPosts"><?=$thread->postCount?></div>
					<div class="td lastPost">
						<a href="/user/<?=$thread->lastPost->authorID?>" class="username"><?=$thread->lastPost->username?></a><br><span class="convertTZ"><?=date('M j, Y g:i a', strtotime($thread->lastPost->datePosted))?></span>
					</div>
				</div>
<?
			} } else echo "\t\t\t\t<div class=\"tr noThreads\">No threads yet</div>\n";
		echo "			</div>
		</div>\n";
		}

		public function getAdminForums($forumID = 0, $currentForum = 0) {
			if (!isset($this->forums[$forumID]))
				return null;

			$forum = $this->forums[$forumID];
			$details = array(
				'forumID' => (int) $forumID,
				'title' => $forum->getTitle(true),
				'admin' => true,
				'children' => array()
			);
			if (!$forum->getPermissions('admin'))
				$details['admin'] = false;
			if (sizeof($forum->getChildren())) {
				foreach ($forum->getChildren() as $childID)
					if ($child = $this->getAdminForums($childID, $currentForum))
						$details['children'][] = $child;
			} elseif (!$details['admin'])
				return null;

			return $details;
		}
	}
?>
