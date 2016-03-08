<?
	class ForumManager {
		protected $currentForum;
		protected $forumsData = array();
		public $forums = array();
		protected $lastRead = array();

		const NO_CHILDREN = 1;
		const NO_NEWPOSTS = 2;
		const ADMIN_FORUMS = 4;

		public function __construct($forumID, $options = 0) {
			global $mysql, $loggedIn, $currentUser, $mongo;

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
			$forumsR = $mongo->forums->find($forumConds);
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
			foreach (array_keys($this->forumsData) as $forumID) 
				$this->forums[$forumID]->sortChildren();
			if ($options&$this::ADMIN_FORUMS) 
				$this->pruneByPermissions(0, 'admin');
			else 
				$this->pruneByPermissions();

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
				if ($forum->latestPost->threadID == null || $forum->latestPost->datePosted->sec < $this->forums[$forum->getForumID()]->getMarkedRead()) 
					$getThreadRD[] = $forum->getForumID();
			}
			if (!($options&$this::NO_NEWPOSTS) && sizeof($getThreadRD)) {
				$rThreadReadData = $mongo->forumsReadData->find([
					'userID' => $currentUser->userID,
					'forumID' => ['$in' => $getThreadRD],
					'type' => 'thread',
					'lastRead' => ['$gt' => new MongoDate($this->forums[$forumID]->getMarkedRead())]
				], ['threadID' => true, 'forumID' => true, 'lastRead' => true]);
				$threadReadData = [];
				$getThreadData = [];
				foreach ($rThreadReadData as $thread) {
					$threadReadData[$thread['threadID']] = $thread;
					$getThreadData[] = $thread['forumID'];
				}
				$rThreadData = $mongo->threads->find([
					'forumID' => ['$in' => array_keys($getThreadData)],
					'lastPost.datePosted' => ['$gt' => new MongoDate($this->forums[$forumID]->getMarkedRead())]
				], ['threadID' => true, 'forumID' => true, 'lastPost' => true]);
				foreach ($rThreadData as $thread) {
					if ($this->forums[$thread['forumID']]->getNewPosts()) 
						continue;
					if (!isset($threadReadData[$thread['threadID']])) 
						$this->forums[$thread['forumID']]->setNewPosts(true);
					elseif ($threadReadData[$thread['threadID']]['lastRead'] < $thread->lastPost->datePosted->sec) 
						$this->forums[$thread['forumID']]->setNewPosts(true);
				}
			}
				// $lastRead = $mysql->query("SELECT f.forumID, unread.markedRead, unread.numUnread newPosts FROM forums f LEFT JOIN (SELECT t.forumID, SUM(t.lastPostID > IFNULL(rdt.lastRead, 0) AND t.lastPostID > IFNULL(crdf.markedRead, 0)) numUnread, MAX(t.lastPostID) latestPost, crdf.markedRead FROM threads t LEFT JOIN forums_readData_threads rdt ON t.threadID = rdt.threadID AND rdt.userID = {$currentUser->userID} LEFT JOIN (SELECT f.forumID, MAX(rdf.markedRead) markedRead FROM forums f LEFT JOIN forums p ON f.heritage LIKE CONCAT(p.heritage, '%') LEFT JOIN forums_readData_forums rdf ON p.forumID = rdf.forumID AND rdf.userID = {$currentUser->userID} GROUP BY f.forumID) crdf ON t.forumID = crdf.forumID GROUP BY t.forumID) unread ON f.forumID = unread.forumID WHERE f.forumID IN (".implode(',', array_keys($this->forumsData)).")");
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
			if (preg_match('/(\w+)\[(\w+)\]/', $property, $matches)) return $this->forums[$forumID]->{$matches[1]}[$matches[2]];
			elseif (preg_match('/(\w+)->(\w+)/', $property, $matches)) return $this->forums[$forumID]->$matches[1]->$matches[2];
			else return $this->forums[$forumID]->$property;
		}

		public function displayCheck($forumID = null) {
			if ($forumID == null) $forumID = $this->currentForum;

			if (sizeof($this->forums[$forumID]->children) || $this->forums[$forumID]->permissions['read']) return true;
			else return false;
		}

		public function displayForum() {
			global $loggedIn, $currentUser;

			if (sizeof($this->forums[$this->currentForum]->children) == 0) 
				return false;

			$tableOpen = false;
			$lastType = 'f';
			foreach ($this->forums[$this->currentForum]->children as $childID) {
				if ($tableOpen && ($lastType == 'c' || $this->forums[$childID]->forumType == 'c')) {
					$tableOpen = false;
					echo "\t\t\t</div>\n\t\t</div>\n";
				}
				if (!$tableOpen) {
?>
		<div class="tableDiv">
			<div class="clearfix">
<?					if ($loggedIn && $childID == 2) { ?>
				<div class="pubGameToggle hbdMargined">
					<span>Show public games: </span>
					<a href="/forums/process/togglePubGames/" class="ofToggle disable<?=$currentUser->showPubGames?' on':''?>"></a>
				</div>
<?					} ?>
				<h2 class="trapezoid redTrapezoid"><?=$this->forums[$childID]->forumType == 'c'?$this->forums[$childID]->title:'Subforums'?></h2>
			</div>
			<div class="tr headerTR headerbar hbDark">
				<div class="td icon">&nbsp;</div>
				<div class="td name">Forum</div>
				<div class="td numThreads"># of Threads</div>
				<div class="td numPosts"># of Posts</div>
				<div class="td lastPost">Last Post</div>
			</div>
			<div class="sudoTable forumList hbdMargined">
<?
					$tableOpen = true;
				}
				if ($this->forums[$childID]->forumType == 'f') 
					$this->displayForumRow($childID);
				elseif (is_array($this->forums[$childID]->children))
					foreach ($this->forums[$childID]->children as $cChildID) 
						$this->displayForumRow($cChildID);
				$lastType = $this->forums[$childID]->forumType;
			}
			echo "\t\t\t</div>\n\t\t</div>\n";
		}

		public function displayForumRow($forumID) {
			$forum = $this->forums[$forumID];
?>
				<div class="tr<?=$this->newPosts($forumID)?'':' noPosts'?>">
					<div class="td icon"><div class="forumIcon<?=$this->newPosts($forumID)?' newPosts':''?>" title="<?=$this->newPosts($forumID)?'New':'No new'?> posts in forum" alt="<?=$this->newPosts($forumID)?'New':'No new'?> posts in forum"></div></div>
					<div class="td name">
						<a href="/forums/<?=$forum->forumID?>/"><?=printReady($forum->title)?></a>
<?=($forum->description != '')?"\t\t\t\t\t\t<div class=\"description\">".printReady($forum->description)."</div>\n":''?>
					</div>
					<div class="td numThreads"><?=$this->getTotalThreadCount($forumID)?></div>
					<div class="td numPosts"><?=$this->getTotalPostCount($forumID)?></div>
					<div class="td lastPost">
<?
			$lastPost = $this->getLastPost($forumID);
			if ($lastPost) echo "\t\t\t\t\t\t<a href=\"/user/{$lastPost->userID}/\" class=\"username\">{$lastPost->username}</a><br><span class=\"convertTZ\">".date('M j, Y g:i a', strtotime($lastPost->datePosted))."</span>\n";
			else echo "\t\t\t\t\t\t</span>No Posts Yet!</span>\n";
?>
					</div>
				</div>
<?
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
			if (sizeof($forum->children)) {
				foreach ($forum->children as $cForumID) 
					$total += $this->getTotalPostCount($cForumID);
			}
			if ($forum->permissions['read']) $total += $forum->postCount;
			return $total;
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
			$this->forums[$this->currentForum]->getThreads($page);
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